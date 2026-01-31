<?php

declare(strict_types=1);

namespace RateLimiter\Storage;

/**
 * In-memory storage implementation for testing.
 * NOT suitable for production distributed systems.
 */
final class InMemoryStorage implements StorageInterface
{
    /** @var array<string, int> */
    private array $counters = [];

    /** @var array<string, list<array{timestamp: float, expires: float}>> */
    private array $timestamps = [];

    /** @var array<string, array{tokens: int, lastRefill: float, expires: float}> */
    private array $buckets = [];

    private bool $available = true;

    /** @var ?callable(): float */
    private $timeOverride = null;

    public function getTime(): float
    {
        if ($this->timeOverride !== null) {
            return ($this->timeOverride)();
        }
        return microtime(true);
    }

    /**
     * Override time for testing clock skew scenarios.
     *
     * @param callable(): float $fn
     */
    public function setTimeOverride(callable $fn): void
    {
        $this->timeOverride = $fn;
    }

    public function clearTimeOverride(): void
    {
        $this->timeOverride = null;
    }

    public function setAvailable(bool $available): void
    {
        $this->available = $available;
    }

    public function isAvailable(): bool
    {
        return $this->available;
    }

    public function increment(string $key, int $value, float $ttl): int
    {
        // TODO: Implement with proper TTL simulation
        return 0;
    }

    public function get(string $key): ?int
    {
        // TODO: Implement with expiration check
        return null;
    }

    public function setNx(string $key, int $value, float $ttl): bool
    {
        // TODO: Implement
        return false;
    }

    public function addTimestamp(string $key, float $timestamp, float $ttl): void
    {
        // TODO: Implement with expiration tracking
    }

    public function countTimestamps(string $key, float $min, float $max): int
    {
        // TODO: Implement
        return 0;
    }

    public function removeTimestampsBefore(string $key, float $cutoff): void
    {
        // TODO: Implement
    }

    public function decrementToken(
        string $key,
        int $tokens,
        float $lastRefillTime,
        float $newRefillTime,
        int $maxTokens,
        float $ttl
    ): bool {
        // TODO: Implement atomic token bucket logic
        return false;
    }

    public function clear(): void
    {
        $this->counters = [];
        $this->timestamps = [];
        $this->buckets = [];
        $this->timeOverride = null;
        $this->available = true;
    }
}
