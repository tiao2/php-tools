<?php

declare(strict_types=1);

namespace PhpTools\Community\Content;

use PhpTools\Community\CommunityBase;
use PhpTools\Community\Acl\AclChecker;
use PhpTools\Community\Exception\ForbiddenException;
use PhpTools\SSO\SSO;

class PostManager extends CommunityBase
{
    private \PDO $pdo;
    private AclChecker $acl;

    public function __construct(SSO $sso, \PDO $pdo, AclChecker $acl)
    {
        parent::__construct($sso);
        $this->pdo = $pdo;
        $this->acl = $acl;
    }

    /**
     * Create a new post.
     */
    public function create(string $title, string $content, string $token, string $authCode): int
    {
        $userId = $this->authenticateUser($token, $authCode);
        if (!$this->acl->hasPermission($userId, 'post', 'create')) {
            throw new ForbiddenException('No permission to create posts');
        }

        $stmt = $this->pdo->prepare('INSERT INTO posts (user_id, title, content, status, created_at) VALUES (:user_id, :title, :content, 2, NOW())');
        $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':content' => $content,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    /**
     * List posts with pagination.
     */
    public function list(int $page = 1, int $perPage = 20): array
    {
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare('SELECT p.id, p.title, p.content, p.status, p.created_at, u.username
            FROM posts p JOIN users u ON p.user_id = u.id
            ORDER BY p.created_at DESC LIMIT :limit OFFSET :offset');
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /**
     * Delete a post (owner or admin).
     */
    public function delete(int $postId, string $token, string $authCode): bool
    {
        $userId = $this->authenticateUser($token, $authCode);

        // Fetch post owner
        $stmt = $this->pdo->prepare('SELECT user_id FROM posts WHERE id = :id');
        $stmt->execute([':id' => $postId]);
        $post = $stmt->fetch();
        if (!$post) {
            throw new \RuntimeException('Post not found');
        }

        if ($post['user_id'] !== $userId && !$this->acl->hasPermission($userId, 'post', 'delete')) {
            throw new ForbiddenException('No permission to delete this post');
        }

        $stmt = $this->pdo->prepare('DELETE FROM posts WHERE id = :id');
        return $stmt->execute([':id' => $postId]);
    }
}