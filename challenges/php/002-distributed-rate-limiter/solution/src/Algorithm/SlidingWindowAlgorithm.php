<?php

declare(strict_types=1);

namespace RateLimiter\Algorithm;

use RateLimiter\Storage\StorageInterface;
use RateLimiter\RateLimitDecision;
use RateLimiter\RateLimitConfig;

/**
 * Sliding Window Log algorithm for precise rate limiting.
 *
 * Stores actual request timestamps, counts those within window.
 * Most precise but highest memory usage.
 */
final class SlidingWindowAlgorithm implements AlgorithmInterface
{
    public function getName(): string
    {
        return 'sliding_window';
    }

    public function check(
        string $key,
        RateLimitConfig $config,
        StorageInterface $storage
    ): RateLimitDecision {
        $now = $storage->getTime();
        $windowStart = $now - $config->windowSeconds;
        $windowKey = $key . ':sw';

        // Clean old entries and count current in one logical operation
        // Storage implementations should make this atomic where possible
        $storage->removeTimestampsBefore($windowKey, $windowStart);
        $currentCount = $storage->countTimestamps($windowKey, $windowStart, $now);

        if ($currentCount < $config->limit) {
            // Add timestamp for this request
            $storage->addTimestamp($windowKey, $now, (float) $config->windowSeconds * 2);

            // Find reset time: when oldest entry in window will expire
            // For simplicity, estimate as window end from now
            $resetTime = (int) ceil($now + $config->windowSeconds);

            return new RateLimitDecision(
                allowed: true,
                limit: $config->limit,
                remaining: $config->limit - $currentCount - 1,
                resetTime: $resetTime,
                algorithm: $this->getName(),
                reason: 'Request within sliding window limit',
                metadata: [
                    'window_count' => $currentCount + 1,
                    'window_start' => $windowStart,
                ],
            );
        }

        // Find when oldest request in window expires for accurate retry-after
        // This requires additional storage operation in full implementation
        $resetTime = (int) ceil($now + $config->windowSeconds);

        return new RateLimitDecision(
            allowed: false,
            limit: $config->limit,
            remaining: 0,
            resetTime: $resetTime,
            algorithm: $this->getName(),
            reason: 'Sliding window limit exceeded',
            metadata: [
                'window_count' => $currentCount,
                'window_start' => $windowStart,
            ],
        );
    }
}
