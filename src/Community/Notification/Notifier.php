<?php

declare(strict_types=1);

namespace PhpTools\Community\Notification;

use PhpTools\Community\CommunityBase;
use PhpTools\SSO\SSO;

class Notifier extends CommunityBase
{
    private \PDO $pdo;

    public function __construct(SSO $sso, \PDO $pdo)
    {
        parent::__construct($sso);
        $this->pdo = $pdo;
    }

    /**
     * Send a notification to a specific user.
     */
    public function send(int $toUserId, string $title, string $content, ?string $link = null): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, title, content, link, is_read, created_at) 
             VALUES (:user_id, :title, :content, :link, 0, NOW())'
        );
        return $stmt->execute([
            ':user_id' => $toUserId,
            ':title'   => $title,
            ':content' => $content,
            ':link'    => $link,
        ]);
    }

    /**
     * Send a notification to the currently authenticated user (using SSO token).
     */
    public function notifySelf(string $token, string $authCode, string $title, string $content, ?string $link = null): bool
    {
        $userId = $this->authenticateUser($token, $authCode);
        return $this->send($userId, $title, $content, $link);
    }

    /**
     * Get notifications for the authenticated user.
     */
    public function getNotifications(string $token, string $authCode, bool $unreadOnly = false, int $limit = 20): array
    {
        $userId = $this->authenticateUser($token, $authCode);
        $sql = 'SELECT * FROM notifications WHERE user_id = :user_id';
        if ($unreadOnly) {
            $sql .= ' AND is_read = 0';
        }
        $sql .= ' ORDER BY created_at DESC LIMIT :limit';
        $stmt = $this->pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Mark a notification as read.
     */
    public function markRead(int $notificationId, string $token, string $authCode): bool
    {
        $userId = $this->authenticateUser($token, $authCode);
        $stmt = $this->pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = :id AND user_id = :user_id');
        return $stmt->execute([':id' => $notificationId, ':user_id' => $userId]);
    }
}