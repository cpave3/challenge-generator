<?php

declare(strict_types=1);

namespace RateLimiter\Storage;

/**
 * Abstraction for rate limit storage backends.
 * Must support atomic operations for distributed coordination.
 */
interface StorageInterface
{
    /**
     * Get current timestamp from storage perspective.
     * Critical for handling clock skew in distributed systems.
     */
    public function getTime(): float;

    /**
     * Atomically increment a counter and return new value.
     * TTL should be set to prevent memory leaks.
     */
    public function increment(string $key, int $value, float $ttl): int;

    /**
     * Get current value of a key, or null if not exists.
     */
    public function get(string $key): ?int;

    /**
     * Atomically set value with TTL if key doesn't exist.
     * Returns true if set succeeded, false if key already exists.
     */
    public function setNx(string $key, int $value, float $ttl): bool;

    /**
     * Add timestamp to sorted set with TTL.
     * Used by sliding window algorithm.
     */
    public function addTimestamp(string $key, float $timestamp, float $ttl): void;

    /**
     * Count timestamps in range [min, max].
     * Used by sliding window algorithm.
     */
    public function countTimestamps(string $key, float $min, float $max): int;

    /**
     * Remove timestamps older than cutoff.
     * Used by sliding window algorithm cleanup.
     */
    public function removeTimestampsBefore(string $key, float $cutoff): void;

    /**
     * Atomically decrement tokens if available.
     * Returns true if decremented, false if not enough tokens.
     * Used by token bucket algorithm.
     */
    public function decrementToken(string $key, int $tokens, float $lastRefillTime, float $newRefillTime, int $maxTokens, float $ttl): bool;

    /**
     * Check if storage is available.
     */
    public function isAvailable(): bool;

    /**
     * Clear all data (primarily for testing).
     */
    public function clear(): void;
}
