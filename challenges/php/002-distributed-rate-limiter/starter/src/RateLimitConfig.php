<?php

declare(strict_types=1);

namespace RateLimiter;

use RateLimiter\Algorithm\AlgorithmInterface;

/**
 * Immutable configuration for a rate limit.
 */
final class RateLimitConfig
{
    /**
     * @param positive-int $limit Maximum requests allowed in window
     * @param positive-int $windowSeconds Time window in seconds
     * @param AlgorithmInterface $algorithm Algorithm implementation to use
     * @param bool $failClosed If true, reject when storage unavailable; if false, allow
     * @param string|null $keyPrefix Optional prefix for multi-tenancy
     */
    public function __construct(
        public readonly int $limit,
        public readonly int $windowSeconds,
        public readonly AlgorithmInterface $algorithm,
        public readonly bool $failClosed = true,
        public readonly ?string $keyPrefix = null,
    ) {
        // TODO: Validate limit > 0, windowSeconds > 0
    }

    /**
     * Get fully qualified key with prefix.
     */
    public function getKey(string $identifier): string
    {
        // TODO: Implement prefixing logic
        return $identifier;
    }
}
