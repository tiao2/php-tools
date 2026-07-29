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
        $sql = 'INSERT INTO sso_sessions (token, authcode, user_id, login_ip, token_expire, authcode_expire) VALUES (:token, :authcode, :user_id, :login_ip, :token_expire, :authcode_expire)';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([
            ':token' => $token,
            ':authcode' => $authCode,
            ':user_id' => $userId,
            ':login_ip' => $ip,
            ':token_expire' => $tokenExpire,
            ':authcode_expire' => $authCodeExpire,
        ]);
    }

    public function getSession(string $token): ?array
    {
        $sql = 'SELECT token, authcode, user_id, login_ip, token_expire, authcode_expire FROM sso_sessions WHERE token = :token';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':token' => $token]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function deleteSession(string $token): bool
    {
        $sql = 'DELETE FROM sso_sessions WHERE token = :token';
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute([':token' => $token]);
    }

    public function deleteExpired(): int
    {
        $now = time();
        $sql = 'DELETE FROM sso_sessions WHERE token_expire < :now OR authcode_expire < :now2';
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':now' => $now, ':now2' => $now]);
        return $stmt->rowCount();
    }
}