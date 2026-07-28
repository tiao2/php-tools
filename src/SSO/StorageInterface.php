<?php

declare(strict_types=1);

namespace PhpTools\SSO;

interface StorageInterface
{
    public function createSession(string $token, string $authCode, int $userId, string $ip, int $tokenExpire, int $authCodeExpire): bool;
    public function getSession(string $token): ?array;
    public function deleteSession(string $token): bool;
    public function deleteExpired(): int;
}