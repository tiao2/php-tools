<?php

declare(strict_types=1);

namespace PhpTools\SSO;

use PhpTools\SSO\Exception\StorageException;

class MySqlStorage implements StorageInterface
{
    private \PDO $pdo;

    public function __construct(?array $dbConfig = null)
    {
        $host = $dbConfig['host'] ?? $_ENV['DB_HOST'] ?? '127.0.0.1';
        $port = $dbConfig['port'] ?? $_ENV['DB_PORT'] ?? '3306';
        $dbname = $dbConfig['dbname'] ?? $_ENV['DB_NAME'] ?? 'sso_db';
        $user = $dbConfig['user'] ?? $_ENV['DB_USER'] ?? 'root';
        $pass = $dbConfig['pass'] ?? $_ENV['DB_PASS'] ?? '';
        $charset = $dbConfig['charset'] ?? $_ENV['DB_CHARSET'] ?? 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";
        try {
            $this->pdo = new \PDO($dsn, $user, $pass, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (\PDOException $e) {
            throw new StorageException('Database connection failed: ' . $e->getMessage(), 0, $e);
        }
    }

    public function createSession(string $token, string $authCode, int $userId, string $ip, int $tokenExpire, int $authCodeExpire): bool
    {
        // 使用 tokenExpire 作为过期时间，存入 expires_at
        $expiresAt = date('Y-m-d H:i:s', $tokenExpire);
        $sql = 'INSERT INTO sso_sessions (token, auth_code, user_id, client_ip, expires_at) VALUES (:token, :auth_code, :user_id, :client_ip, :expires_at)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':token' => $token,
            ':auth_code' => $authCode,
            ':user_id' => $userId,
            ':client_ip' => $ip,
            ':expires_at' => $expiresAt,
        ]);
    }

    public function getSession(string $token): ?array
    {
        $sql = 'SELECT token, auth_code AS authcode, user_id, client_ip AS login_ip, expires_at FROM sso_sessions WHERE token = :token';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':token' => $token]);
        $row = $stmt->fetch();
        if (!$row) {
            return null;
        }
        // 将 expires_at 转为时间戳，同时赋值给 token_expire 和 authcode_expire（兼容 SSO 逻辑）
        $timestamp = strtotime($row['expires_at']);
        $row['token_expire'] = $timestamp;
        $row['authcode_expire'] = $timestamp;
        return $row;
    }

    public function deleteSession(string $token): bool
    {
        $sql = 'DELETE FROM sso_sessions WHERE token = :token';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':token' => $token]);
    }

    public function deleteExpired(): int
    {
        $sql = 'DELETE FROM sso_sessions WHERE expires_at < NOW()';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }
}
