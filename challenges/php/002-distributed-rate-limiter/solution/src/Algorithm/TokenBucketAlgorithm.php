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
        $now = $storage->getTime();
        $bucketKey = $key . ':bucket';

        // Calculate tokens to add: rate = limit / window
        $refillRate = $config->limit / $config->windowSeconds;

        // Attempt to consume 1 token atomically
        // Storage handles the math: add tokens based on time, then decrement if available
        $success = $storage->decrementToken(
            $bucketKey,
            1,
            $now - 1, // lastRefillTime placeholder, storage recalculates
            $now,
            $config->limit, // bucket capacity = limit for simplicity
            (float) $config->windowSeconds * 2 // TTL: 2 windows for safety
        );

        // For in-memory implementation, we need to simulate the atomic operation
        // since PHP doesn't have true atomic operations. The storage interface
        // contract requires atomicity, so implementations must handle this.

        // Get current state for metadata (this is eventually consistent)
        // In production, the atomic operation returns the state

        if ($success) {
            return new RateLimitDecision(
                allowed: true,
                limit: $config->limit,
                remaining: 0, // Approximate - true value requires second round trip
                resetTime: (int) ceil($now + (1 / $refillRate)),
                algorithm: $this->getName(),
                reason: 'Token consumed from bucket',
                metadata: [
                    'refill_rate_per_sec' => $refillRate,
                    'bucket_capacity' => $config->limit,
                ],
            );
        }

        // Estimate when next token available
        $nextTokenTime = (int) ceil($now + (1 / $refillRate));

        return new RateLimitDecision(
            allowed: false,
            limit: $config->limit,
            remaining: 0,
            resetTime: $nextTokenTime,
            algorithm: $this->getName(),
            reason: 'Bucket empty, tokens refilling',
            metadata: [
                'refill_rate_per_sec' => $refillRate,
                'estimated_wait_seconds' => 1 / $refillRate,
            ],
        );
    }
}
