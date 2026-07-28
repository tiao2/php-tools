<?php

declare(strict_types=1);

namespace PhpTools\Community\User;

use PhpTools\Community\CommunityBase;
use PhpTools\SSO\SSO;

class UserManager extends CommunityBase
{
    private \PDO $pdo;

    public function __construct(SSO $sso, \PDO $pdo)
    {
        parent::__construct($sso);
        $this->pdo = $pdo;
    }

    public function getProfile(int $userId): array
    {
        $stmt = $this->pdo->prepare('SELECT id, username, email, nickname, avatar, created_at, updated_at FROM users WHERE id = :id');
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();
        if (!$user) {
            throw new \RuntimeException('User not found');
        }
        return $user;
    }

    public function updateProfile(string $token, string $authCode, array $data): bool
    {
        $this->authenticateUser($token, $authCode);
        $userId = $this->getCurrentUserId();

        $allowed = ['nickname', 'avatar', 'email'];
        $updates = [];
        $params = [':id' => $userId];
        foreach ($data as $key => $value) {
            if (in_array($key, $allowed, true)) {
                $updates[] = "{$key} = :{$key}";
                $params[":{$key}"] = $value;
            }
        }
        if (empty($updates)) {
            return false;
        }
        $sql = 'UPDATE users SET ' . implode(', ', $updates) . ' WHERE id = :id';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }
}