<?php

declare(strict_types=1);

namespace PhpTools\Community\Cache;

class FileCache implements CacheDriver
{
    private string $cacheDir;

    public function __construct(?string $cacheDir = null)
    {
        $this->cacheDir = $cacheDir ?? ($_ENV['CACHE_DIR'] ?? sys_get_temp_dir() . '/php_tools_cache/');
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0755, true);
        }
    }

    public function get(string $key)
    {
        $file = $this->getFilePath($key);
        if (!file_exists($file)) {
            return null;
        }
        $data = file_get_contents($file);
        if ($data === false) {
            return null;
        }
        $entry = unserialize($data);
        if ($entry['expires_at'] > 0 && $entry['expires_at'] < time()) {
            $this->delete($key);
            return null;
        }
        return $entry['value'];
    }

    public function set(string $key, $value, int $ttl = 0): bool
    {
        $expiresAt = $ttl > 0 ? time() + $ttl : 0;
        $entry = [
            'value'      => $value,
            'expires_at' => $expiresAt,
        ];
        $file = $this->getFilePath($key);
        return file_put_contents($file, serialize($entry), LOCK_EX) !== false;
    }

    public function delete(string $key): bool
    {
        $file = $this->getFilePath($key);
        if (file_exists($file)) {
            return unlink($file);
        }
        return true;
    }

    private function getFilePath(string $key): string
    {
        return $this->cacheDir . md5($key) . '.cache';
    }
}