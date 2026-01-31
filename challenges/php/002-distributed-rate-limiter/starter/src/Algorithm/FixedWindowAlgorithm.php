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
        // TODO: Implement fixed window logic
        // 1. Determine current window based on timestamp
        // 2. Atomically increment counter for window
        // 3. Return appropriate decision

        throw new \RuntimeException('Not implemented');
    }
}
