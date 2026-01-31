<?php

declare(strict_types=1);

namespace RateLimiter\Algorithm;

use RateLimiter\Storage\StorageInterface;
use RateLimiter\RateLimitDecision;
use RateLimiter\RateLimitConfig;

/**
 * Interface for rate limiting algorithms.
 * Each algorithm must be implementable with atomic storage operations.
 */
interface AlgorithmInterface
{
    /**
     * Check if request should be allowed and return decision metadata.
     */
    public function check(
        string $key,
        RateLimitConfig $config,
        StorageInterface $storage
    ): RateLimitDecision;

    /**
     * Human-readable algorithm name for observability.
     */
    public function getName(): string;
}
