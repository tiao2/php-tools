<?php

declare(strict_types=1);

namespace PhpTools\SSO;

use PhpTools\SSO\Exception\ExpiredTokenException;
use PhpTools\SSO\Exception\InvalidCredentialException;
use PhpTools\SSO\Exception\IpMismatchException;
use PhpTools\SSO\Util\Base64Random;

class SSO
{
    private StorageInterface $storage;
    private AuthenticatorInterface $authenticator;
    private Config $config;

    public function __construct(StorageInterface $storage, AuthenticatorInterface $authenticator, Config $config)
    {
        $this->storage = $storage;
        $this->authenticator = $authenticator;
        $this->config = $config;
    }

    public static function fromEnv(AuthenticatorInterface $authenticator): self
    {
        $config = Config::fromEnv();
        $storage = new MySqlStorage();
        return new self($storage, $authenticator, $config);
    }

    public function login(string $username, string $password, ?string $clientIp = null): array
    {
        $userId = $this->authenticator->authenticate($username, $password);
        if ($userId === null) {
            throw new InvalidCredentialException('Invalid username or password');
        }
        $token = Base64Random::generate(16);
        $authCode = Base64Random::generate(16);
        $now = time();
        $tokenExpire = $now + $this->config->getTokenExpire();
        $authCodeExpire = $now + $this->config->getAuthCodeExpire();
        $ip = $clientIp ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $this->storage->createSession($token, $authCode, $userId, $ip, $tokenExpire, $authCodeExpire);
        return ['token' => $token, 'authcode' => $authCode];
    }

    public function verify(string $token, string $authCode, ?string $clientIp = null): int
    {
        $session = $this->storage->getSession($token);
        if ($session === null) {
            throw new InvalidCredentialException('Token not found');
        }
        if (!hash_equals($session['authcode'], $authCode)) {
            throw new InvalidCredentialException('Auth code does not match');
        }
        $now = time();
        if ($session['token_expire'] < $now || $session['authcode_expire'] < $now) {
            throw new ExpiredTokenException('Session expired');
        }
        $sessionIp = $session['login_ip'];
        $clientIp = $clientIp ?? $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        if (!$this->isIpMatch($sessionIp, $clientIp)) {
            throw new IpMismatchException('IP address changed');
        }
        return (int)$session['user_id'];
    }

    private function isIpMatch(string $storedIp, string $currentIp): bool
    {
        if (filter_var($storedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) &&
            filter_var($currentIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $storedIp === $currentIp;
        }
        if (filter_var($storedIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) &&
            filter_var($currentIp, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $prefixLen = $this->config->getIpv6PrefixLength();
            return $this->ipv6PrefixMatch($storedIp, $currentIp, $prefixLen);
        }
        return $storedIp === $currentIp;
    }

    private function ipv6PrefixMatch(string $ip1, string $ip2, int $prefixLen): bool
    {
        $bin1 = inet_pton($ip1);
        $bin2 = inet_pton($ip2);
        if ($bin1 === false || $bin2 === false) return false;
        $byteLen = intdiv($prefixLen, 8);
        for ($i = 0; $i < $byteLen; $i++) {
            if ($bin1[$i] !== $bin2[$i]) return false;
        }
        $remainingBits = $prefixLen % 8;
        if ($remainingBits > 0 && $byteLen < strlen($bin1)) {
            $mask = 0xFF << (8 - $remainingBits) & 0xFF;
            if ((ord($bin1[$byteLen]) & $mask) !== (ord($bin2[$byteLen]) & $mask)) return false;
        }
        return true;
    }

    public function cleanExpired(): int
    {
        return $this->storage->deleteExpired();
    }
}