<?php

declare(strict_types=1);

namespace RateLimiter\Algorithm;

use RateLimiter\Storage\StorageInterface;
use RateLimiter\RateLimitDecision;
use RateLimiter\RateLimitConfig;

/**
 * Fixed Window Counter algorithm.
 *
 * Simple counter that resets each window. Allows 2x traffic at boundaries.
 * O(1) memory and very fast.
 */
final class FixedWindowAlgorithm implements AlgorithmInterface
{
    public function getName(): string
    {
        return 'fixed_window';
    }

    public function check(
        string $key,
        RateLimitConfig $config,
        StorageInterface $storage
    ): RateLimitDecision {
        $now = $storage->getTime();

        // Determine current window
        $windowIndex = (int) floor($now / $config->windowSeconds);
        $windowKey = $key . ':fw:' . $windowIndex;

        // Atomically increment
        $count = $storage->increment($windowKey, 1, (float) $config->windowSeconds * 2);

        // Calculate window boundaries
        $windowStart = $windowIndex * $config->windowSeconds;
        $windowEnd = $windowStart + $config->windowSeconds;
        $resetTime = (int) ceil($windowEnd);

        $remaining = $config->limit - $count;

        if ($count <= $config->limit) {
            return new RateLimitDecision(
                allowed: true,
                limit: $config->limit,
                remaining: max(0, $remaining),
                resetTime: $resetTime,
                algorithm: $this->getName(),
                reason: 'Request within fixed window limit',
                metadata: [
                    'window_index' => $windowIndex,
                    'window_count' => $count,
                ],
            );
        }

        return new RateLimitDecision(
            allowed: false,
            limit: $config->limit,
            remaining: 0,
            resetTime: $resetTime,
            algorithm: $this->getName(),
            reason: 'Fixed window limit exceeded',
            metadata: [
                'window_index' => $windowIndex,
                'window_count' => $count,
            ],
        );
    }
}
