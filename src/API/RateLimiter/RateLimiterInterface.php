<?php
declare(strict_types=1);
namespace PhpTools\API\RateLimiter;
interface RateLimiterInterface {
    public function allow(string $key): bool;
    public function getRemaining(string $key): int;
    public function getRetryAfter(string $key): int;
}