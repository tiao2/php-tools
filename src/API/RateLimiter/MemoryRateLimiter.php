<?php
declare(strict_types=1);
namespace PhpTools\API\RateLimiter;

class MemoryRateLimiter implements RateLimiterInterface
{
    private int $maxRequests;
    private int $window;
    private array $storage = [];

    public function __construct(int $maxRequests = 100, int $window = 60)
    {
        $this->maxRequests = $maxRequests;
        $this->window = $window;
    }

    public function allow(string $key): bool
    {
        $this->clearExpired($key);
        $now = time();
        $this->storage[$key][] = $now;
        return count($this->storage[$key]) <= $this->maxRequests;
    }

    public function getRemaining(string $key): int
    {
        $this->clearExpired($key);
        $used = count($this->storage[$key] ?? []);
        return max(0, $this->maxRequests - $used);
    }

    public function getRetryAfter(string $key): int
    {
        $this->clearExpired($key);
        if (empty($this->storage[$key])) return 0;
        $oldest = min($this->storage[$key]);
        return max(0, $oldest + $this->window - time());
    }

    private function clearExpired(string $key): void
    {
        if (!isset($this->storage[$key])) {
            $this->storage[$key] = [];
            return;
        }
        $cutoff = time() - $this->window;
        $this->storage[$key] = array_filter($this->storage[$key], fn($t) => $t > $cutoff);
    }
}