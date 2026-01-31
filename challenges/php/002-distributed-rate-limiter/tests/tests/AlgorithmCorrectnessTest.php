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
 * Tests for algorithm correctness under single-node conditions.
 */
final class AlgorithmCorrectnessTest extends TestCase
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
     * Token Bucket: Should allow burst up to capacity, then rate limit.
     */
    public function testTokenBucketAllowsBurstThenLimits(): void
    {
        $algorithm = new TokenBucketAlgorithm();
        $config = new RateLimitConfig(
            limit: 5,
            windowSeconds: 60,
            algorithm: $algorithm,
        );

        // Consume all 5 tokens in burst
        $allowed = [];
        for ($i = 0; $i < 5; $i++) {
            $decision = $algorithm->check('user:123', $config, $this->storage);
            $allowed[] = $decision->allowed;
        }

        $this->assertSame([true, true, true, true, true], $allowed, 'Should allow burst up to capacity');

        // 6th request should be denied
        $decision = $algorithm->check('user:123', $config, $this->storage);
        $this->assertFalse($decision->allowed, 'Should deny when bucket empty');
        $this->assertGreaterThan(0, $decision->getRetryAfter(), 'Should provide retry-after');
    }

    /**
     * Token Bucket: Should never exceed capacity even with slow requests.
     */
    public function testTokenBucketNeverExceedsCapacity(): void
    {
        $algorithm = new TokenBucketAlgorithm();
        $config = new RateLimitConfig(
            limit: 3,
            windowSeconds: 10,
            algorithm: $algorithm,
        );

        $startTime = 1000.0;
        $this->storage->setTimeOverride(fn() => $startTime);

        // Make requests over time, checking we never exceed 3 in any burst
        $maxTokensConsumed = 0;
        $currentBurst = 0;

        for ($t = 0; $t < 100; $t += 0.5) {
            $currentTime = $startTime + $t;
            $this->storage->setTimeOverride(fn() => $currentTime);

            $decision = $algorithm->check('user:burst', $config, $this->storage);

            if ($decision->allowed) {
                $currentBurst++;
                $maxTokensConsumed = max($maxTokensConsumed, $currentBurst);
            } else {
                $currentBurst = 0;
            }
        }

        $this->assertLessThanOrEqual(3, $maxTokensConsumed, 'Should never exceed bucket capacity in any burst');
    }

    /**
     * Sliding Window: Should enforce precise window boundaries.
     */
    public function testSlidingWindowPreciseBoundaries(): void
    {
        $algorithm = new SlidingWindowAlgorithm();
        $config = new RateLimitConfig(
            limit: 3,
            windowSeconds: 10,
            algorithm: $algorithm,
        );

        $baseTime = 1000.0;

        // Requests at t=0, 1, 2
        for ($i = 0; $i < 3; $i++) {
            $this->storage->setTimeOverride(fn() => $baseTime + $i);
            $decision = $algorithm->check('user:sw', $config, $this->storage);
            $this->assertTrue($decision->allowed, "Request $i should be allowed");
        }

        // 4th request at t=2 should fail
        $this->storage->setTimeOverride(fn() => $baseTime + 2);
        $decision = $algorithm->check('user:sw', $config, $this->storage);
        $this->assertFalse($decision->allowed, '4th request should be denied');

        // At t=9.9, still in window, should fail
        $this->storage->setTimeOverride(fn() => $baseTime + 9.9);
        $decision = $algorithm->check('user:sw', $config, $this->storage);
        $this->assertFalse($decision->allowed, 'Request at t=9.9 should still be denied');

        // At t=10.1, first request has slid out, should allow
        $this->storage->setTimeOverride(fn() => $baseTime + 10.1);
        $decision = $algorithm->check('user:sw', $config, $this->storage);
        $this->assertTrue($decision->allowed, 'Request at t=10.1 should be allowed (window slid)');
    }

    /**
     * Sliding Window: Precision test - should not over-allocate by more than 1%.
     */
    public function testSlidingWindowPrecision(): void
    {
        $algorithm = new SlidingWindowAlgorithm();
        $limit = 100;
        $config = new RateLimitConfig(
            limit: $limit,
            windowSeconds: 60,
            algorithm: $algorithm,
        );

        $baseTime = 0.0;
        $allowedCount = 0;
        $totalRequests = 0;

        // Simulate sustained load at 2x the limit rate
        for ($t = 0; $t < 300; $t += 0.3) {
            $this->storage->setTimeOverride(fn() => $baseTime + $t);
            $decision = $algorithm->check('user:precision', $config, $this->storage);
            $totalRequests++;
            if ($decision->allowed) {
                $allowedCount++;
            }
        }

        // In 300 seconds with 100/min limit, max allowed is 500 (with some window overlap)
        // Allow 1% over-allocation
        $maxExpected = $limit * (300 / 60) * 1.01;
        $this->assertLessThanOrEqual(
            (int) ceil($maxExpected),
            $allowedCount,
            "Allowed $allowedCount/$totalRequests, expected at most $maxExpected (1% tolerance)"
        );
    }

    /**
     * Fixed Window: Should allow up to limit, reset at window boundary.
     */
    public function testFixedWindowCounterReset(): void
    {
        $algorithm = new FixedWindowAlgorithm();
        $config = new RateLimitConfig(
            limit: 3,
            windowSeconds: 10,
            algorithm: $algorithm,
        );

        // Window 0: t=[0, 10)
        $this->storage->setTimeOverride(fn() => 5.0);

        for ($i = 0; $i < 3; $i++) {
            $decision = $algorithm->check('user:fw', $config, $this->storage);
            $this->assertTrue($decision->allowed, "Request $i in window 0 should be allowed");
        }

        $decision = $algorithm->check('user:fw', $config, $this->storage);
        $this->assertFalse($decision->allowed, '4th request in window 0 should be denied');

        // Window 1: t=[10, 20), counter resets
        $this->storage->setTimeOverride(fn() => 10.0);
        $decision = $algorithm->check('user:fw', $config, $this->storage);
        $this->assertTrue($decision->allowed, 'First request in window 1 should be allowed');
    }

    /**
     * Fixed Window: Should allow 2x traffic at window boundary (known limitation).
     */
    public function testFixedWindowBoundarySpike(): void
    {
        $algorithm = new FixedWindowAlgorithm();
        $config = new RateLimitConfig(
            limit: 5,
            windowSeconds: 60,
            algorithm: $algorithm,
        );

        // End of window 0: use all 5
        $this->storage->setTimeOverride(fn() => 59.0);
        for ($i = 0; $i < 5; $i++) {
            $decision = $algorithm->check('user:spike', $config, $this->storage);
            $this->assertTrue($decision->allowed, "End-of-window request $i should be allowed");
        }

        // Start of window 1: can use another 5 immediately
        $this->storage->setTimeOverride(fn() => 60.0);
        $spikeAllowed = 0;
        for ($i = 0; $i < 5; $i++) {
            $decision = $algorithm->check('user:spike', $config, $this->storage);
            if ($decision->allowed) {
                $spikeAllowed++;
            }
        }

        $this->assertEquals(5, $spikeAllowed, 'Fixed window allows 2x traffic at boundary');
    }

    /**
     * All algorithms: Should return correct headers.
     */
    public function testHeadersPresentAndCorrect(): void
    {
        $algorithms = [
            new TokenBucketAlgorithm(),
            new SlidingWindowAlgorithm(),
            new FixedWindowAlgorithm(),
        ];

        foreach ($algorithms as $algorithm) {
            $config = new RateLimitConfig(
                limit: 10,
                windowSeconds: 60,
                algorithm: $algorithm,
            );

            $this->storage->clear();
            $this->storage->clearTimeOverride();

            $decision = $algorithm->check('user:headers', $config, $this->storage);
            $headers = $decision->getHeaders();

            $this->assertArrayHasKey('X-RateLimit-Limit', $headers, $algorithm->getName() . ': should have limit header');
            $this->assertArrayHasKey('X-RateLimit-Remaining', $headers, $algorithm->getName() . ': should have remaining header');
            $this->assertArrayHasKey('X-RateLimit-Reset', $headers, $algorithm->getName() . ': should have reset header');

            $this->assertEquals('10', $headers['X-RateLimit-Limit'], $algorithm->getName() . ': limit should match config');
            $this->assertGreaterThanOrEqual(0, (int) $headers['X-RateLimit-Remaining'], $algorithm->getName() . ': remaining should be non-negative');
            $this->assertGreaterThan(time(), (int) $headers['X-RateLimit-Reset'], $algorithm->getName() . ': reset should be in future');
        }
    }
}
