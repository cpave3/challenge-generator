<?php

declare(strict_types=1);

namespace RateLimiter\Storage;

/**
 * Redis-backed storage using Lua scripts for atomic operations.
 *
 * Production-ready implementation requiring Redis 6.2+.
 */
final class RedisStorage implements StorageInterface
{
    // Lua script for atomic token bucket check-and-decrement
    private const TOKEN_BUCKET_SCRIPT = <<<LUA
        local key = KEYS[1]
        local tokens_to_consume = tonumber(ARGV[1])
        local now = tonumber(ARGV[3])
        local max_tokens = tonumber(ARGV[4])
        local ttl = tonumber(ARGV[5])
        local refill_rate = max_tokens / (ttl / 2)
        
        local bucket = redis.call('HMGET', key, 'tokens', 'last_refill')
        local tokens = tonumber(bucket[1]) or max_tokens
        local last_refill = tonumber(bucket[2]) or now
        
        -- Add tokens based on elapsed time
        local elapsed = now - last_refill
        local new_tokens = math.min(max_tokens, tokens + elapsed * refill_rate)
        
        if new_tokens >= tokens_to_consume then
            new_tokens = new_tokens - tokens_to_consume
            redis.call('HMSET', key, 'tokens', new_tokens, 'last_refill', now)
            redis.call('EXPIRE', key, math.ceil(ttl))
            return 1
        else
            -- Still update last_refill for accurate timing
            redis.call('HMSET', key, 'tokens', new_tokens, 'last_refill', now)
            redis.call('EXPIRE', key, math.ceil(ttl))
            return 0
        end
    LUA;

    private ?string $tokenBucketScriptSha = null;

    public function __construct(
        private readonly \Redis $redis,
    ) {
    }

    public function getTime(): float
    {
        // Redis TIME returns [seconds, microseconds]
        $time = $this->redis->time();
        return (float) $time[0] + ($time[1] / 1e6);
    }

    public function increment(string $key, int $value, float $ttl): int
    {
        $multi = $this->redis->multi();
        $multi->incrBy($key, $value);
        $multi->expire($key, (int) ceil($ttl));
        $results = $multi->exec();

        return (int) ($results[0] ?? 0);
    }

    public function get(string $key): ?int
    {
        $value = $this->redis->get($key);
        return $value === false ? null : (int) $value;
    }

    public function setNx(string $key, int $value, float $ttl): bool
    {
        return $this->redis->set($key, $value, ['NX', 'EX' => (int) ceil($ttl)]);
    }

    public function addTimestamp(string $key, float $timestamp, float $ttl): void
    {
        // Use ZADD with timestamp as score, member as unique id
        $member = uniqid('', true);
        $this->redis->zAdd($key, $timestamp, $member);
        $this->redis->expire($key, (int) ceil($ttl));
    }

    public function countTimestamps(string $key, float $min, float $max): int
    {
        return (int) $this->redis->zCount($key, $min, $max);
    }

    public function removeTimestampsBefore(string $key, float $cutoff): void
    {
        // Remove scores < cutoff (inclusive of -inf)
        $this->redis->zRemRangeByScore($key, '-inf', (string) $cutoff);
    }

    public function decrementToken(
        string $key,
        int $tokens,
        float $lastRefillTime,
        float $newRefillTime,
        int $maxTokens,
        float $ttl
    ): bool {
        // Load script on first use
        if ($this->tokenBucketScriptSha === null) {
            $this->tokenBucketScriptSha = $this->redis->script('load', self::TOKEN_BUCKET_SCRIPT);
        }

        $result = $this->redis->evalSha(
            $this->tokenBucketScriptSha,
            [$key],
            [$tokens, $lastRefillTime, $newRefillTime, $maxTokens, $ttl]
        );

        return (bool) $result;
    }

    public function isAvailable(): bool
    {
        try {
            return $this->redis->ping() === '+PONG';
        } catch (\RedisException) {
            return false;
        }
    }

    public function clear(): void
    {
        $this->redis->flushDB();
    }
}
