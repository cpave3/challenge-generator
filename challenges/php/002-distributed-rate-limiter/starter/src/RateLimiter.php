<?php

declare(strict_types=1);

namespace RateLimiter;

use RateLimiter\Storage\StorageInterface;

/**
 * Main rate limiter coordinating storage, algorithms, and observability.
 */
final class RateLimiter
{
    /** @var array<callable(RateLimitDecision, float): void> */
    private array $metricsHooks = [];

    public function __construct(
        private readonly StorageInterface $storage,
    ) {
    }

    /**
     * Register a callback for metrics/observability.
     * Callback receives decision and processing time in milliseconds.
     *
     * @param callable(RateLimitDecision, float): void $callback
     */
    public function onDecision(callable $callback): void
    {
        $this->metricsHooks[] = $callback;
    }

    /**
     * Check if request should be allowed.
     */
    public function check(string $identifier, RateLimitConfig $config): RateLimitDecision
    {
        // TODO: Implement
        // 1. Check storage availability, handle fail open/closed
        // 2. Get qualified key from config
        // 3. Delegate to algorithm
        // 4. Emit metrics
        // 5. Return decision

        throw new \RuntimeException('Not implemented');
    }
}
