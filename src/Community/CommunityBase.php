<?php
declare(strict_types=1);
namespace PhpTools\Community;
use PhpTools\SSO\SSO;                        // 更新
use PhpTools\Community\Exception\UnauthorizedException;
use PhpTools\SSO\Exception\IpMismatchException;
use PhpTools\SSO\Exception\ExpiredTokenException;
use PhpTools\SSO\Exception\InvalidCredentialException;

abstract class CommunityBase
{
    protected SSO $sso;
    protected ?int $currentUserId = null;

    public function __construct(SSO $sso)
    {
        $this->sso = $sso;
    }

    protected function authenticateUser(string $token, string $authCode, ?string $clientIp = null): int
    {
        try {
            $this->currentUserId = $this->sso->verify($token, $authCode, $clientIp);
            return $this->currentUserId;
        } catch (InvalidCredentialException | ExpiredTokenException | IpMismatchException $e) {
            throw new UnauthorizedException($e->getMessage(), $e->getCode(), $e);
        }
    }

    protected function getCurrentUserId(): int
    {
        if ($this->currentUserId === null) {
            throw new UnauthorizedException('User not authenticated');
        }
        return $this->currentUserId;
    }
}