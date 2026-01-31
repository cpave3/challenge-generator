<?php

declare(strict_types=1);

namespace RateLimiter\Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RateLimiter\Algorithm\FixedWindowAlgorithm;
use RateLimiter\RateLimitConfig;

/**
 * Tests for configuration validation.
 */
final class ConfigValidationTest extends TestCase
{
    public function testZeroLimitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be positive');

        new RateLimitConfig(
            limit: 0,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );
    }

    public function testNegativeLimitThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Limit must be positive');

        new RateLimitConfig(
            limit: -5,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );
    }

    public function testZeroWindowThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Window seconds must be positive');

        new RateLimitConfig(
            limit: 10,
            windowSeconds: 0,
            algorithm: new FixedWindowAlgorithm(),
        );
    }

    public function testNegativeWindowThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Window seconds must be positive');

        new RateLimitConfig(
            limit: 10,
            windowSeconds: -1,
            algorithm: new FixedWindowAlgorithm(),
        );
    }

    public function testPositiveValuesAccepted(): void
    {
        $config = new RateLimitConfig(
            limit: 1,
            windowSeconds: 1,
            algorithm: new FixedWindowAlgorithm(),
        );

        $this->assertEquals(1, $config->limit);
        $this->assertEquals(1, $config->windowSeconds);
    }

    public function testKeyPrefixing(): void
    {
        $configWithPrefix = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
            keyPrefix: 'tenant-abc',
        );

        $this->assertEquals('tenant-abc:user:123', $configWithPrefix->getKey('user:123'));

        $configNoPrefix = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        $this->assertEquals('user:123', $configNoPrefix->getKey('user:123'));
    }

    public function testFailClosedDefault(): void
    {
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
        );

        $this->assertTrue($config->failClosed);
    }

    public function testFailOpenCanBeSet(): void
    {
        $config = new RateLimitConfig(
            limit: 10,
            windowSeconds: 60,
            algorithm: new FixedWindowAlgorithm(),
            failClosed: false,
        );

        $this->assertFalse($config->failClosed);
    }
}
