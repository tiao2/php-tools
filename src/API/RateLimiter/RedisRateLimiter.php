<?php
declare(strict_types=1);
namespace PhpTools\API\RateLimiter;

class RedisRateLimiter implements RateLimiterInterface
{
    private \Redis $redis;
    private int $maxRequests;
    private int $window;

    public function __construct(\Redis $redis, int $maxRequests = 100, int $window = 60)
    {
        $this->redis = $redis;
        $this->maxRequests = $maxRequests;
        $this->window = $window;
    }

    public function allow(string $key): bool
    {
        $lua = <<<LUA
            local key = KEYS[1]
            local now = tonumber(ARGV[1])
            local window = tonumber(ARGV[2])
            local max = tonumber(ARGV[3])
            redis.call('ZREMRANGEBYSCORE', key, 0, now - window)
            local count = redis.call('ZCARD', key)
            if count < max then
                redis.call('ZADD', key, now, now .. '-' .. math.random())
                redis.call('EXPIRE', key, window)
                return 1
            end
            return 0
LUA;
        $now = microtime(true) * 1000;
        $result = $this->redis->eval($lua, [$key, $now, $this->window * 1000, $this->maxRequests], 1);
        return (int)$result === 1;
    }

    public function getRemaining(string $key): int
    {
        $now = microtime(true) * 1000;
        $this->redis->zRemRangeByScore($key, 0, $now - $this->window * 1000);
        $count = $this->redis->zCard($key);
        return max(0, $this->maxRequests - $count);
    }

    public function getRetryAfter(string $key): int
    {
        $now = microtime(true) * 1000;
        $this->redis->zRemRangeByScore($key, 0, $now - $this->window * 1000);
        $oldest = $this->redis->zRange($key, 0, 0, true);
        if (empty($oldest)) return 0;
        $oldestTime = (float)reset($oldest);
        return max(0, (int)(($oldestTime + $this->window * 1000 - $now) / 1000));
    }
}