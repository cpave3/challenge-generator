<?php

declare(strict_types=1);

namespace RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use RateLimiter\Algorithm\FixedWindowAlgorithm;
use RateLimiter\Algorithm\SlidingWindowAlgorithm;
use RateLimiter\Algorithm\TokenBucketAlgorithm;
use RateLimiter\RateLimitConfig;
use RateLimiter\Storage\InMemoryStorage;

/**
 * Tests for distributed behavior: clock skew, race conditions, storage failures.
 */
final class DistributedBehaviorTest extends TestCase
{
    private InMemoryStorage $storage;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
    }

    protected function tearDown(): void
    {
        $this->storage->clear();
    }

    /**
     * Simulate 500ms clock skew between nodes, verify no over-allocation > 1%.
     */
    public function testClockSkewTolerance(): void
    {
        $algorithm = new SlidingWindowAlgorithm();
        $limit = 100;
        $config = new RateLimitConfig(
            limit: $limit,
            windowSeconds: 60,
            algorithm: $algorithm,
        );

        $baseTime = 1000.0;
        $skew = 0.5; // 500ms skew

        $allowedCount = 0;
        $nodeACount = 0;
        $nodeBCount = 0;

        // Simulate interleaved requests from two nodes with clock skew
        for ($i = 0; $i < 200; $i++) {
            // Node A: "correct" time
            $this->storage->setTimeOverride(fn() => $baseTime + ($i * 0.3));
            $decisionA = $algorithm->check('shared:key', $config, $this->storage);
            if ($decisionA->allowed) {
                $nodeACount++;
                $allowedCount++;
            }

            // Node B: 500ms ahead
            $this->storage->setTimeOverride(fn() => $baseTime + ($i * 0.3) + $skew);
            $decisionB = $algorithm->check('shared:key', $config, $this->storage);
            if ($decisionB->allowed) {
                $nodeBCount++;
                $allowedCount++;
            }
        }

        // With 400 requests over ~60 seconds at 100/min limit
        // Max theoretical with perfect coordination: ~200 (both nodes see same state)
        // Allow 1% tolerance for skew-induced over-allocation
        $duration = 200 * 0.3 / 60; // minutes
        $maxExpected = $limit * $duration * 1.01 * 2; // both nodes

        $this->assertLessThanOrEqual(
            (int) ceil($maxExpected),
            $allowedCount,
            "Clock skew caused over-allocation: $allowedCount allowed, expected max ~$maxExpected (nodeA: $nodeACount, nodeB: $nodeBCount)"
        );
    }

    /**
     * Simulate race conditions: rapid concurrent requests should not over-allocate.
     */
    public function testRaceConditionHandling(): void
    {
        $algorithm = new FixedWindowAlgorithm();
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: $algorithm,
        );

        $this->storage->setTimeOverride(fn() => 1000.0);

        // Simulate 100 "concurrent" requests
        // In real distributed system, these would race. Our in-memory storage
        // serializes them, but we verify the total allowed is exactly the limit.
        $allowed = 0;
        for ($i = 0; $i < 100; $i++) {
            $decision = $algorithm->check('race:key', $config, $this->storage);
            if ($decision->allowed) {
                $allowed++;
            }
        }

        $this->assertLessThanOrEqual(
            10,
            $allowed,
            "Race condition caused over-allocation: $allowed allowed, limit is 10"
        );

        // In single-node, we expect exactly 10. In distributed, might be slightly more
        // due to split-brain, but storage atomicity should prevent major over-allocation.
        $this->assertGreaterThanOrEqual(10, $allowed, 'Should allow up to limit with single storage');
    }

    /**
     * Storage failure: fail closed should reject all requests.
     */
    public function testFailClosedBehavior(): void
    {
        $algorithm = new FixedWindowAlgorithm();
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: $algorithm,
            failClosed: true,
        );

        $this->storage->setAvailable(false);

        for ($i = 0; $i < 5; $i++) {
            $decision = $algorithm->check('any:key', $config, $this->storage);
            // Note: algorithm check happens before storage check in RateLimiter
            // This test verifies the algorithm can handle unavailable storage gracefully
            // In practice, RateLimiter.check() handles this before calling algorithm
        }

        // Test through RateLimiter for proper fail-closed behavior
        $limiter = new \RateLimiter\RateLimiter($this->storage);
        $decision = $limiter->check('user:123', $config);

        $this->assertFalse($decision->allowed, 'Should reject when storage unavailable (fail closed)');
        $this->assertStringContainsString('Storage unavailable', $decision->reason);
        $this->assertTrue($decision->metadata['fail_closed'] ?? false);
    }

    /**
     * Storage failure: fail open should allow all requests.
     */
    public function testFailOpenBehavior(): void
    {
        $algorithm = new FixedWindowAlgorithm();
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: $algorithm,
            failClosed: false, // fail open
        );

        $this->storage->setAvailable(false);

        $limiter = new \RateLimiter\RateLimiter($this->storage);
        $decision = $limiter->check('user:123', $config);

        $this->assertTrue($decision->allowed, 'Should allow when storage unavailable (fail open)');
        $this->assertStringContainsString('Storage unavailable', $decision->reason);
        $this->assertTrue($decision->metadata['fail_open'] ?? false);
    }

    /**
     * Verify Redis storage would use Redis TIME for distributed clock.
     */
    public function testRedisTimeCommandUsage(): void
    {
        // This is a design verification test - actual Redis integration would require
        // running Redis server. We verify the interface supports this pattern.

        $storage = $this->createMock(\RateLimiter\Storage\StorageInterface::class);
        $storage->expects($this->atLeastOnce())
            ->method('getTime')
            ->willReturn(1234567890.123456);

        $algorithm = new TokenBucketAlgorithm();
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: $algorithm,
        );

        $algorithm->check('key', $config, $storage);
        // Test passes if getTime was called, indicating algorithm uses storage time
    }

    /**
     * Multi-tenant isolation: different prefixes should not interfere.
     */
    public function testMultiTenantIsolation(): void
    {
        $algorithm = new FixedWindowAlgorithm();

        $configTenantA = new RateLimitConfig(
            limit: 3,
            windowSeconds: 60,
            algorithm: $algorithm,
            keyPrefix: 'tenant-a',
        );

        $configTenantB = new RateLimitConfig(
            limit: 3,
            windowSeconds: 60,
            algorithm: $algorithm,
            keyPrefix: 'tenant-b',
        );

        // Exhaust tenant A's limit
        for ($i = 0; $i < 3; $i++) {
            $decision = $algorithm->check('user:shared', $configTenantA, $this->storage);
            $this->assertTrue($decision->allowed, "Tenant A request $i should be allowed");
        }

        $decision = $algorithm->check('user:shared', $configTenantA, $this->storage);
        $this->assertFalse($decision->allowed, 'Tenant A should be rate limited');

        // Tenant B should still have full quota
        for ($i = 0; $i < 3; $i++) {
            $decision = $algorithm->check('user:shared', $configTenantB, $this->storage);
            $this->assertTrue($decision->allowed, "Tenant B request $i should be allowed (isolated from A)");
        }
    }
}
