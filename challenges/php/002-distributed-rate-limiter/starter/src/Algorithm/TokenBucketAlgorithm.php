<?php

declare(strict_types=1);

namespace RateLimiter\Algorithm;

use RateLimiter\Storage\StorageInterface;
use RateLimiter\RateLimitDecision;
use RateLimiter\RateLimitConfig;

/**
 * Token Bucket algorithm allowing bursts up to bucket size.
 *
 * Tokens refill at rate of limit/window per second.
 * Bucket capacity determines burst size.
 */
final class TokenBucketAlgorithm implements AlgorithmInterface
{
    public function getName(): string
    {
        return 'token_bucket';
    }

    public function check(
        string $key,
        RateLimitConfig $config,
        StorageInterface $storage
    ): RateLimitDecision {
        // TODO: Implement token bucket logic
        // 1. Calculate tokens to add based on time elapsed
        // 2. Atomically check and decrement if token available
        // 3. Return appropriate decision with metadata

        throw new \RuntimeException('Not implemented');
    }
}
