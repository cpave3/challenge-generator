<?php

declare(strict_types=1);

namespace RateLimiter;

use RateLimiter\Storage\StorageInterface;

/**
 * Main rate limiter coordinating storage, algorithms, and observability.
 */
final class RateLimiter
{
    /** @var list<callable(RateLimitDecision, float): void> */
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
        $startTime = hrtime(true);

        // Handle storage unavailability
        if (!$this->storage->isAvailable()) {
            $decision = $this->handleStorageUnavailable($config);
            $this->emitMetrics($decision, $startTime);
            return $decision;
        }

        $key = $config->getKey($identifier);
        $decision = $config->algorithm->check($key, $config, $this->storage);

        $this->emitMetrics($decision, $startTime);
        return $decision;
    }

    private function handleStorageUnavailable(RateLimitConfig $config): RateLimitDecision
    {
        $now = time();

        if ($config->failClosed) {
            return new RateLimitDecision(
                allowed: false,
                limit: $config->limit,
                remaining: 0,
                resetTime: $now + $config->windowSeconds,
                algorithm: 'unknown',
                reason: 'Storage unavailable - fail closed',
                metadata: ['fail_closed' => true],
            );
        }

        return new RateLimitDecision(
            allowed: true,
            limit: $config->limit,
            remaining: $config->limit,
            resetTime: $now + $config->windowSeconds,
            algorithm: 'unknown',
            reason: 'Storage unavailable - fail open',
            metadata: ['fail_open' => true],
        );
    }

    private function emitMetrics(RateLimitDecision $decision, int $startTimeNs): void
    {
        $durationMs = (hrtime(true) - $startTimeNs) / 1e6;

        foreach ($this->metricsHooks as $hook) {
            $hook($decision, $durationMs);
        }
    }
}
