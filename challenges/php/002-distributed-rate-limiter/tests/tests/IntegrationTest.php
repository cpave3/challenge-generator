<?php

declare(strict_types=1);

namespace RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use RateLimiter\Algorithm\FixedWindowAlgorithm;
use RateLimiter\Algorithm\SlidingWindowAlgorithm;
use RateLimiter\Algorithm\TokenBucketAlgorithm;
use RateLimiter\RateLimitConfig;
use RateLimiter\RateLimiter;
use RateLimiter\Storage\InMemoryStorage;

/**
 * Integration tests simulating real-world scenarios.
 */
final class IntegrationTest extends TestCase
{
    private InMemoryStorage $storage;
    private RateLimiter $limiter;

    protected function setUp(): void
    {
        $this->storage = new InMemoryStorage();
        $this->limiter = new RateLimiter($this->storage);
    }

    protected function tearDown(): void
    {
        $this->storage->clear();
    }

    /**
     * API endpoint protection scenario: burst then sustained rate.
     */
    public function testApiEndpointBurstThenSustained(): void
    {
        // Token bucket: allow burst of 20, then 10/second sustained
        $config = new RateLimitConfig(
            limit: 20,
            windowSeconds: 2, // 10 tokens per second refill
            algorithm: new TokenBucketAlgorithm(),
        );

        $baseTime = 1000.0;

        // Phase 1: Burst of 20 should all succeed
        $this->storage->setTimeOverride(fn() => $baseTime);
        $burstResults = [];
        for ($i = 0; $i < 25; $i++) {
            $decision = $this->limiter->check('api:endpoint', $config);
            $burstResults[] = $decision->allowed;
        }

        $this->assertEquals(
            array_merge(array_fill(0, 20, true), array_fill(0, 5, false)),
            $burstResults,
            'Should allow exactly 20 burst requests'
        );

        // Phase 2: After 0.1 seconds, ~1 token refilled
        $this->storage->setTimeOverride(fn() => $baseTime + 0.1);
        $decision = $this->limiter->check('api:endpoint', $config);
        $this->assertTrue($decision->allowed, 'Should allow 1 refilled token');

        // Phase 3: Immediate next should fail (no tokens)
        $decision = $this->limiter->check('api:endpoint', $config);
        $this->assertFalse($decision->allowed, 'Should deny when no tokens');
    }

    /**
     * User-specific rate limits with different tiers.
     */
    public function testTieredRateLimits(): void
    {
        $freeTier = new RateLimitConfig(
            limit: 10,
            windowSeconds: 3600,
            algorithm: new FixedWindowAlgorithm(),
            keyPrefix: 'free',
        );

        $proTier = new RateLimitConfig(
            limit: 1000,
            windowSeconds: 3600,
            algorithm: new FixedWindowAlgorithm(),
            keyPrefix: 'pro',
        );

        // Same user ID, different tiers
        $userId = 'user:123';

        // Free tier exhausts quickly
        $freeAllowed = 0;
        for ($i = 0; $i < 15; $i++) {
            $decision = $this->limiter->check($userId, $freeTier);
            if ($decision->allowed) {
                $freeAllowed++;
            }
        }
        $this->assertEquals(10, $freeAllowed, 'Free tier should be limited to 10');

        // Pro tier allows many more
        $proAllowed = 0;
        for ($i = 0; $i < 15; $i++) {
            $decision = $this->limiter->check($userId, $proTier);
            if ($decision->allowed) {
                $proAllowed++;
            }
        }
        $this->assertEquals(15, $proAllowed, 'Pro tier should allow all 15');
    }

    /**
     * Algorithm switching: same key, different algorithms over time.
     */
    public function testAlgorithmSwitching(): void
    {
        $key = 'user:switch';

        // Start with fixed window
        $fixedConfig = new RateLimitConfig(
            limit: 5,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        for ($i = 0; $i < 5; $i++) {
            $decision = $this->limiter->check($key, $fixedConfig);
            $this->assertTrue($decision->allowed, "Fixed window request $i");
        }

        // Switch to sliding window (different key internally due to algorithm)
        $slidingConfig = new RateLimitConfig(
            limit: 3,
            windowSeconds: 60,
            algorithm: new SlidingWindowAlgorithm(),
        );

        // Sliding window has its own state, may allow or deny based on its tracking
        $decision = $this->limiter->check($key, $slidingConfig);
        // This verifies the switch happens without error - exact behavior depends on implementation
        $this->assertInstanceOf(\RateLimiter\RateLimitDecision::class, $decision);
        $this->assertEquals('sliding_window', $decision->algorithm);
    }

    /**
     * High cardinality: many unique keys should not cause memory issues.
     */
    public function testHighCardinalityKeys(): void
    {
        $config = new RateLimitConfig(
            limit: 100,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        // 10,000 unique keys, 1 request each
        for ($i = 0; $i < 10000; $i++) {
            $decision = $this->limiter->check("user:$i", $config);
            $this->assertTrue($decision->allowed, "First request for user:$i should be allowed");
        }

        // All should still work for second request
        $secondAllowed = 0;
        for ($i = 0; $i < 10000; $i++) {
            $decision = $this->limiter->check("user:$i", $config);
            if ($decision->allowed) {
                $secondAllowed++;
            }
        }

        $this->assertEquals(10000, $secondAllowed, 'All second requests should be allowed (under limit)');
    }

    /**
     * Sliding window memory usage: old entries should be cleaned up.
     */
    public function testSlidingWindowCleanup(): void
    {
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 10,
            algorithm: new SlidingWindowAlgorithm(),
        );

        $baseTime = 0.0;

        // Add entries over time
        for ($t = 0; $t < 100; $t += 1) {
            $this->storage->setTimeOverride(fn() => $baseTime + $t);
            $this->limiter->check('cleanup:test', $config);
        }

        // At t=100, entries before t=90 should be cleaned
        // We can't directly inspect storage, but we can verify behavior
        // by checking that we can still make requests (limit not artificially inflated)

        $this->storage->setTimeOverride(fn() => $baseTime + 100);

        // Should be able to make up to 10 new requests
        $newAllowed = 0;
        for ($i = 0; $i < 15; $i++) {
            $decision = $this->limiter->check('cleanup:test', $config);
            if ($decision->allowed) {
                $newAllowed++;
            }
        }

        // With cleanup working, should allow fresh 10 (or close to it)
        // Without cleanup, might incorrectly count old entries
        $this->assertGreaterThanOrEqual(8, $newAllowed, 'Cleanup should prevent memory leak inflation');
    }

    /**
     * End-to-end: Simulate API gateway protecting multiple endpoints.
     */
    public function testApiGatewaySimulation(): void
    {
        $metrics = [];
        $this->limiter->onDecision(function ($decision, $duration) use (&$metrics): void {
            $metrics[] = [
                'allowed' => $decision->allowed,
                'algorithm' => $decision->algorithm,
                'duration_ms' => $duration,
            ];
        });

        // Different endpoints with different policies
        $endpoints = [
            'GET /api/v1/users' => new RateLimitConfig(
                limit: 100,
                windowSeconds: 60,
                algorithm: new FixedWindowAlgorithm(),
                keyPrefix: 'users',
            ),
            'POST /api/v1/users' => new RateLimitConfig(
                limit: 10,
                windowSeconds: 60,
                algorithm: new SlidingWindowAlgorithm(),
                keyPrefix: 'users_create',
            ),
            'GET /api/v1/search' => new RateLimitConfig(
                limit: 30,
                windowSeconds: 60,
                algorithm: new TokenBucketAlgorithm(),
                keyPrefix: 'search',
            ),
        ];

        // Simulate traffic mix
        $traffic = [
            ['GET /api/v1/users', 'user:a'],
            ['GET /api/v1/users', 'user:b'],
            ['POST /api/v1/users', 'user:a'],
            ['GET /api/v1/search', 'user:a'],
            ['GET /api/v1/search', 'user:a'], // burst
            ['GET /api/v1/search', 'user:a'], // burst
        ];

        foreach ($traffic as [$endpoint, $userId]) {
            $decision = $this->limiter->check($userId, $endpoints[$endpoint]);
            // All should succeed with this light traffic
            $this->assertTrue($decision->allowed, "$endpoint for $userId should be allowed");
        }

        // Verify metrics captured all decisions
        $this->assertCount(6, $metrics, 'All decisions should be metrics-logged');

        // Verify algorithm diversity
        $algorithmsUsed = array_unique(array_column($metrics, 'algorithm'));
        $this->assertContains('fixed_window', $algorithmsUsed);
        $this->assertContains('sliding_window', $algorithmsUsed);
        $this->assertContains('token_bucket', $algorithmsUsed);
    }
}
