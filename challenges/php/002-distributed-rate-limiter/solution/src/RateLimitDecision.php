<?php

declare(strict_types=1);

namespace RateLimiter;

/**
 * Immutable result of a rate limit check with full observability metadata.
 */
final class RateLimitDecision
{
    /**
     * @param bool $allowed Whether the request is allowed
     * @param int $limit The configured limit
     * @param int $remaining Remaining requests in current window (may be negative if over limit)
     * @param int $resetTime Unix timestamp when limit resets
     * @param string $algorithm Name of algorithm used
     * @param string $reason Human-readable explanation of decision
     * @param array<string, mixed> $metadata Algorithm-specific debug info
     */
    public function __construct(
        public readonly bool $allowed,
        public readonly int $limit,
        public readonly int $remaining,
        public readonly int $resetTime,
        public readonly string $algorithm,
        public readonly string $reason,
        public readonly array $metadata = [],
    ) {
    }

    /**
     * Get standard rate limit headers for HTTP response.
     *
     * @return array<string, string>
     */
    public function getHeaders(): array
    {
        return [
            'X-RateLimit-Limit' => (string) $this->limit,
            'X-RateLimit-Remaining' => (string) max(0, $this->remaining),
            'X-RateLimit-Reset' => (string) $this->resetTime,
        ];
    }

    /**
     * Get Retry-After header value in seconds.
     */
    public function getRetryAfter(): int
    {
        $now = time();
        $retryAfter = $this->resetTime - $now;
        return max(1, $retryAfter);
    }
}
