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
        // TODO: Implement sliding window logic
        // 1. Clean old timestamps outside window
        // 2. Count timestamps in current window
        // 3. If under limit, add timestamp and allow
        // 4. Return appropriate decision

        throw new \RuntimeException('Not implemented');
    }
}
