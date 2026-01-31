<?php

declare(strict_types=1);

namespace RateLimiter;

use InvalidArgumentException;
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
        if ($limit <= 0) {
            throw new InvalidArgumentException('Limit must be positive');
        }
        if ($windowSeconds <= 0) {
            throw new InvalidArgumentException('Window seconds must be positive');
        }
    }

    /**
     * Get fully qualified key with prefix.
     */
    public function getKey(string $identifier): string
    {
        if ($this->keyPrefix === null) {
            return $identifier;
        }
        return $this->keyPrefix . ':' . $identifier;
    }
}
