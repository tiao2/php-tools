<?php

declare(strict_types=1);

namespace PhpTools\Community\File;

use PhpTools\Community\CommunityBase;
use PhpTools\SSO\SSO;
use PhpTools\Community\Exception\ForbiddenException;

/**
 * FileManager handles file uploads, metadata retrieval, deletion, and listing.
 * All operations require SSO authentication.
 */
class FileManager extends CommunityBase
{
    private \PDO $pdo;
    private string $uploadDir;
    private int $maxFileSize;      // bytes
    private array $allowedTypes;   // MIME types

    /**
     * Constructor.
     *
     * @param SSO $sso SSO instance for authentication
     * @param \PDO $pdo Database connection
     * @param string|null $uploadDir Directory for storing uploaded files (trailing slash auto-appended)
     * @param int $maxFileSize Maximum file size in bytes
     * @param array $allowedTypes List of allowed MIME types
     */
    public function __construct(
        SSO $sso,
        \PDO $pdo,
        string $uploadDir = null,
        int $maxFileSize = 5 * 1024 * 1024,   // 5MB
        array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf']
    ) {
        parent::__construct($sso);
        $this->pdo = $pdo;
        $this->uploadDir = rtrim($uploadDir ?? ($_ENV['FILE_UPLOAD_DIR'] ?? __DIR__ . '/../../public/uploads/'), '/') . '/';
        $this->maxFileSize = (int)($_ENV['FILE_MAX_SIZE'] ?? $maxFileSize);
        $this->allowedTypes = $allowedTypes;
    }

    /**
     * Upload a file.
     *
     * The file extension is determined solely by its actual MIME type via a safe mapping,
     * ignoring the original filename extension to prevent malicious execution.
     *
     * @param array $file The $_FILES entry for the uploaded file
     * @param string $token SSO token
     * @param string $authCode SSO auth code
     * @return array The uploaded file record from database
     * @throws \RuntimeException on upload failure or invalid file
     */
    public function upload(array $file, string $token, string $authCode): array
    {
        $userId = $this->authenticateUser($token, $authCode);

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('File upload error code: ' . $file['error']);
        }
        if ($file['size'] > $this->maxFileSize) {
            throw new \RuntimeException('File size exceeds maximum allowed');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        if (!in_array($mime, $this->allowedTypes, true)) {
            throw new \RuntimeException('File type not allowed: ' . $mime);
        }

        if (!is_dir($this->uploadDir) && !mkdir($this->uploadDir, 0755, true)) {
            throw new \RuntimeException('Failed to create upload directory');
        }

        // Map MIME type to a safe, harmless extension
        $mimeToExt = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/gif'  => 'gif',
            'application/pdf' => 'pdf',
        ];
        $extension = $mimeToExt[$mime] ?? 'bin';
        $storedName = bin2hex(random_bytes(16)) . '.' . $extension;
        $destination = $this->uploadDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Failed to move uploaded file');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO files (user_id, original_name, stored_name, mime_type, size, path, created_at) 
             VALUES (:user_id, :original_name, :stored_name, :mime_type, :size, :path, NOW())'
        );
        $stmt->execute([
            ':user_id'       => $userId,
            ':original_name' => $file['name'],
            ':stored_name'   => $storedName,
            ':mime_type'     => $mime,
            ':size'          => $file['size'],
            ':path'          => $destination,
        ]);

        $fileId = (int)$this->pdo->lastInsertId();
        return $this->getById($fileId, $token, $authCode);
    }

    /**
     * Get file metadata by ID.
     * Only the owner (or an admin, if extended) may access.
     *
     * @param int $fileId File ID
     * @param string $token SSO token
     * @param string $authCode SSO auth code
     * @return array File record
     * @throws \RuntimeException if file not found
     * @throws ForbiddenException if access denied
     */
    public function getById(int $fileId, string $token, string $authCode): array
    {
        $userId = $this->authenticateUser($token, $authCode);
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = :id');
        $stmt->execute([':id' => $fileId]);
        $file = $stmt->fetch();
        if (!$file) {
            throw new \RuntimeException('File not found');
        }
        if ($file['user_id'] != $userId) {
            throw new ForbiddenException('Access denied');
        }
        return $file;
    }

    /**
     * Delete a file by ID.
     * Only the owner may delete (extend with admin roles if needed).
     *
     * @param int $fileId File ID
     * @param string $token SSO token
     * @param string $authCode SSO auth code
     * @return bool True on success
     * @throws \RuntimeException if file not found or deletion fails
     * @throws ForbiddenException if access denied
     */
    public function delete(int $fileId, string $token, string $authCode): bool
    {
        $file = $this->getById($fileId, $token, $authCode);
        if (file_exists($file['path'])) {
            unlink($file['path']);
        }
        $stmt = $this->pdo->prepare('DELETE FROM files WHERE id = :id');
        return $stmt->execute([':id' => $fileId]);
    }

    /**
     * List files belonging to the authenticated user.
     *
     * @param string $token SSO token
     * @param string $authCode SSO auth code
     * @param int $page Page number (1-indexed)
     * @param int $perPage Items per page
     * @return array List of file records
     */
    public function listUserFiles(string $token, string $authCode, int $page = 1, int $perPage = 20): array
    {
        $userId = $this->authenticateUser($token, $authCode);
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare(
            'SELECT * FROM files WHERE user_id = :user_id ORDER BY created_at DESC LIMIT :limit OFFSET :offset'
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}