<?php

declare(strict_types=1);

namespace RateLimiter\Storage;

/**
 * In-memory storage implementation for testing.
 * NOT suitable for production distributed systems.
 */
final class InMemoryStorage implements StorageInterface
{
    /** @var array<string, array{value: int, expires: float}> */
    private array $counters = [];

    /** @var array<string, list<array{timestamp: float, expires: float}>> */
    private array $timestamps = [];

    /** @var array<string, array{tokens: float, lastRefill: float, expires: float, maxTokens: int}> */
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
        $now = $this->getTime();
        $this->cleanupExpired($now);

        if (!isset($this->counters[$key]) || $this->counters[$key]['expires'] < $now) {
            $this->counters[$key] = ['value' => 0, 'expires' => $now + $ttl];
        }

        $this->counters[$key]['value'] += $value;
        return $this->counters[$key]['value'];
    }

    public function get(string $key): ?int
    {
        $now = $this->getTime();
        $this->cleanupExpired($now);

        if (!isset($this->counters[$key]) || $this->counters[$key]['expires'] < $now) {
            return null;
        }

        return $this->counters[$key]['value'];
    }

    public function setNx(string $key, int $value, float $ttl): bool
    {
        $now = $this->getTime();
        $this->cleanupExpired($now);

        if (isset($this->counters[$key]) && $this->counters[$key]['expires'] >= $now) {
            return false;
        }

        $this->counters[$key] = ['value' => $value, 'expires' => $now + $ttl];
        return true;
    }

    public function addTimestamp(string $key, float $timestamp, float $ttl): void
    {
        $now = $this->getTime();
        $this->cleanupExpired($now);

        if (!isset($this->timestamps[$key])) {
            $this->timestamps[$key] = [];
        }

        $this->timestamps[$key][] = ['timestamp' => $timestamp, 'expires' => $timestamp + $ttl];
    }

    public function countTimestamps(string $key, float $min, float $max): int
    {
        $now = $this->getTime();
        $this->cleanupExpired($now);

        if (!isset($this->timestamps[$key])) {
            return 0;
        }

        $count = 0;
        foreach ($this->timestamps[$key] as $entry) {
            if ($entry['timestamp'] >= $min && $entry['timestamp'] <= $max && $entry['expires'] >= $now) {
                $count++;
            }
        }

        return $count;
    }

    public function removeTimestampsBefore(string $key, float $cutoff): void
    {
        $now = $this->getTime();

        if (!isset($this->timestamps[$key])) {
            return;
        }

        $this->timestamps[$key] = array_values(array_filter(
            $this->timestamps[$key],
            fn(array $entry): bool => $entry['timestamp'] >= $cutoff && $entry['expires'] >= $now
        ));
    }

    public function decrementToken(
        string $key,
        int $tokens,
        float $lastRefillTime,
        float $newRefillTime,
        int $maxTokens,
        float $ttl
    ): bool {
        $now = $this->getTime();
        $this->cleanupExpired($now);

        // Initialize or get bucket
        if (!isset($this->buckets[$key]) || $this->buckets[$key]['expires'] < $now) {
            $this->buckets[$key] = [
                'tokens' => (float) $maxTokens,
                'lastRefill' => $now,
                'expires' => $now + $ttl,
                'maxTokens' => $maxTokens,
            ];
        }

        $bucket = &$this->buckets[$key];

        // Calculate tokens to add based on time elapsed
        $elapsed = $now - $bucket['lastRefill'];
        $refillRate = $maxTokens / ($ttl / 2); // window is half of TTL
        $tokensToAdd = $elapsed * $refillRate;

        $bucket['tokens'] = min((float) $maxTokens, $bucket['tokens'] + $tokensToAdd);
        $bucket['lastRefill'] = $now;

        // Try to consume
        if ($bucket['tokens'] >= $tokens) {
            $bucket['tokens'] -= $tokens;
            return true;
        }

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

    private function cleanupExpired(float $now): void
    {
        foreach ($this->counters as $key => $data) {
            if ($data['expires'] < $now) {
                unset($this->counters[$key]);
            }
        }

        foreach ($this->timestamps as $key => $entries) {
            $this->timestamps[$key] = array_values(array_filter(
                $entries,
                fn(array $e): bool => $e['expires'] >= $now
            ));
            if (empty($this->timestamps[$key])) {
                unset($this->timestamps[$key]);
            }
        }

        foreach ($this->buckets as $key => $data) {
            if ($data['expires'] < $now) {
                unset($this->buckets[$key]);
            }
        }
    }
}
