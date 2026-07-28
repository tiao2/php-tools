<?php

declare(strict_types=1);

namespace PhpTools\Community\Acl;

class AclChecker
{
    private \PDO $pdo;

    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function hasPermission(int $userId, string $resource, string $action): bool
    {
        $sql = 'SELECT COUNT(*) as cnt FROM user_roles ur
                JOIN role_permissions rp ON ur.role_id = rp.role_id
                WHERE ur.user_id = :user_id AND rp.resource = :resource AND rp.action = :action';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $userId,
            ':resource' => $resource,
            ':action' => $action,
        ]);
        $row = $stmt->fetch();
        return (int)$row['cnt'] > 0;
    }
}