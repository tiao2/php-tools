<?php
namespace PhpTools\Community\Cache;
interface CacheDriver {
    public function get(string $key);
    public function set(string $key, $value, int $ttl = 0): bool;
    public function delete(string $key): bool;
}