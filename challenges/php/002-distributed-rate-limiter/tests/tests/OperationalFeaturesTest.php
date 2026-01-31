<?php

declare(strict_types=1);

namespace RateLimiter\Tests;

use PHPUnit\Framework\TestCase;
use RateLimiter\Algorithm\FixedWindowAlgorithm;
use RateLimiter\RateLimitConfig;
use RateLimiter\RateLimitDecision;
use RateLimiter\RateLimiter;
use RateLimiter\Storage\InMemoryStorage;

/**
 * Tests for operational features: metrics, headers, retry-after accuracy.
 */
final class OperationalFeaturesTest extends TestCase
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
     * Metrics hooks should receive correct data for allowed requests.
     */
    public function testMetricsHookForAllowedRequest(): void
    {
        $captured = null;
        $capturedDuration = null;

        $this->limiter->onDecision(function (RateLimitDecision $decision, float $durationMs) use (&$captured, &$capturedDuration): void {
            $captured = $decision;
            $capturedDuration = $durationMs;
        });

        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        $decision = $this->limiter->check('user:metrics', $config);

        $this->assertNotNull($captured, 'Metrics hook should be called');
        $this->assertTrue($captured->allowed, 'Metrics should show allowed');
        $this->assertEquals('fixed_window', $captured->algorithm);
        $this->assertGreaterThanOrEqual(0, $capturedDuration, 'Duration should be non-negative');
        $this->assertLessThan(100, $capturedDuration, 'Duration should be reasonable (< 100ms)');
    }

    /**
     * Metrics hooks should receive correct data for denied requests.
     */
    public function testMetricsHookForDeniedRequest(): void
    {
        $capturedDecisions = [];

        $this->limiter->onDecision(function (RateLimitDecision $decision, float $durationMs) use (&$capturedDecisions): void {
            $capturedDecisions[] = [
                'allowed' => $decision->allowed,
                'algorithm' => $decision->algorithm,
                'duration' => $durationMs,
            ];
        });

        $config = new RateLimitConfig(
            limit: 2,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        // 3 requests: 2 allowed, 1 denied
        $this->limiter->check('user:deny', $config);
        $this->limiter->check('user:deny', $config);
        $this->limiter->check('user:deny', $config);

        $this->assertCount(3, $capturedDecisions, 'Should have 3 metrics events');
        $this->assertTrue($capturedDecisions[0]['allowed'], 'First should be allowed');
        $this->assertTrue($capturedDecisions[1]['allowed'], 'Second should be allowed');
        $this->assertFalse($capturedDecisions[2]['allowed'], 'Third should be denied');
    }

    /**
     * Multiple metrics hooks should all be called.
     */
    public function testMultipleMetricsHooks(): void
    {
        $callCount = 0;

        $this->limiter->onDecision(function () use (&$callCount): void {
            $callCount++;
        });
        $this->limiter->onDecision(function () use (&$callCount): void {
            $callCount++;
        });

        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        $this->limiter->check('user:multi', $config);

        $this->assertEquals(2, $callCount, 'Both hooks should be called');
    }

    /**
     * Retry-after calculation should be accurate within 1 second.
     */
    public function testRetryAfterAccuracy(): void
    {
        $baseTime = 1000;
        $this->storage->setTimeOverride(fn() => (float) $baseTime);

        $config = new RateLimitConfig(
            limit: 5,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        // Exhaust limit at t=50 (within window ending at 60)
        for ($i = 0; $i < 5; $i++) {
            $this->limiter->check('user:retry', $config);
        }

        // At t=55, retry-after should be ~5 seconds
        $this->storage->setTimeOverride(fn() => (float) $baseTime + 55);
        $decision = $this->limiter->check('user:retry', $config);

        $this->assertFalse($decision->allowed);
        $retryAfter = $decision->getRetryAfter();

        // Window ends at 1060 (1000 + 60), current time is 1055, so retry-after should be ~5
        $this->assertGreaterThanOrEqual(4, $retryAfter, 'Retry-after should be at least 4 seconds');
        $this->assertLessThanOrEqual(6, $retryAfter, 'Retry-after should be at most 6 seconds (1s tolerance)');
    }

    /**
     * Decision metadata should explain why request was denied.
     */
    public function testDecisionExplanationForDenial(): void
    {
        $config = new RateLimitConfig(
            limit: 1,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        $this->limiter->check('user:explain', $config); // allowed
        $decision = $this->limiter->check('user:explain', $config); // denied

        $this->assertFalse($decision->allowed);
        $this->assertNotEmpty($decision->reason, 'Should have human-readable reason');
        $this->assertStringContainsStringIgnoringCase('limit', $decision->reason, 'Reason should mention limit');
        $this->assertArrayHasKey('window_count', $decision->metadata, 'Should have window count metadata');
    }

    /**
     * Decision metadata should explain why request was allowed.
     */
    public function testDecisionExplanationForAllow(): void
    {
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        $decision = $this->limiter->check('user:allow', $config);

        $this->assertTrue($decision->allowed);
        $this->assertNotEmpty($decision->reason, 'Should have human-readable reason');
        $this->assertArrayHasKey('window_index', $decision->metadata, 'Should have window metadata');
    }

    /**
     * Headers should be formatted correctly for HTTP response.
     */
    public function testHeaderFormatting(): void
    {
        $config = new RateLimitConfig(
            limit: 100,
            windowSeconds: 3600,
            algorithm: new FixedWindowAlgorithm(),
        );

        $decision = $this->limiter->check('user:headers', $config);
        $headers = $decision->getHeaders();

        // All values should be string integers
        foreach ($headers as $name => $value) {
            $this->assertMatchesRegularExpression('/^\d+$/', $value, "$name should be numeric string");
        }

        $this->assertEquals('100', $headers['X-RateLimit-Limit']);
        $this->assertEquals('99', $headers['X-RateLimit-Remaining']);
        $this->assertGreaterThan(time(), (int) $headers['X-RateLimit-Reset']);
    }

    /**
     * Test with storage unavailable and fail closed - metrics should still fire.
     */
    public function testMetricsWithStorageFailure(): void
    {
        $captured = null;
        $this->limiter->onDecision(function (RateLimitDecision $decision, float $durationMs) use (&$captured): void {
            $captured = $decision;
        });

        $this->storage->setAvailable(false);

        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
            failClosed: true,
        );

        $this->limiter->check('user:fail', $config);

        $this->assertNotNull($captured, 'Metrics should fire even on storage failure');
        $this->assertEquals('unknown', $captured->algorithm, 'Algorithm should be unknown when storage fails');
    }
}
