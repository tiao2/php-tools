<?php

declare(strict_types=1);

namespace PhpTools\SSO;

class RedisStorage implements StorageInterface
{
    private \Redis $redis;

    public function __construct(\Redis $redis) { $this->redis = $redis; }

    public function createSession(string $token, string $authCode, int $userId, string $ip, int $tokenExpire, int $authCodeExpire): bool
    {
        $key = 'sso:token:' . $token;
        $ttl = max($tokenExpire, $authCodeExpire) - time();
        if ($ttl <= 0) return false;
        $data = json_encode([
            'authcode' => $authCode,
            'user_id' => $userId,
            'login_ip' => $ip,
            'token_expire' => $tokenExpire,
            'authcode_expire' => $authCodeExpire,
        ]);
        return $this->redis->setex($key, $ttl, $data);
    }

    public function getSession(string $token): ?array
    {
        $key = 'sso:token:' . $token;
        $data = $this->redis->get($key);
        if ($data === false) return null;
        $session = json_decode($data, true);
        $session['token'] = $token;
        return $session;
    }

    public function deleteSession(string $token): bool
    {
        return $this->redis->del('sso:token:' . $token) > 0;
    }

    public function deleteExpired(): int
    {
        return 0; // Redis keys expire automatically
    }
}