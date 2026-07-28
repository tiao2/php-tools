<?php

declare(strict_types=1);

namespace PhpTools\SSO;

class Config
{
    private int $tokenExpire;
    private int $authCodeExpire;
    private int $ipv6PrefixLength;

    public function __construct(array $options = [])
    {
        $this->tokenExpire = (int)($options['tokenExpire'] ?? $_ENV['SSO_TOKEN_EXPIRE'] ?? 3600);
        $this->authCodeExpire = (int)($options['authCodeExpire'] ?? $_ENV['SSO_AUTHCODE_EXPIRE'] ?? 3600);
        $this->ipv6PrefixLength = (int)($options['ipv6Prefix'] ?? $_ENV['SSO_IPV6_PREFIX'] ?? 64);
    }

    public static function fromEnv(): self
    {
        return new self();
    }

    public function getTokenExpire(): int { return $this->tokenExpire; }
    public function getAuthCodeExpire(): int { return $this->authCodeExpire; }
    public function getIpv6PrefixLength(): int { return $this->ipv6PrefixLength; }
}