<?php

declare(strict_types=1);

namespace PhpTools\API;

use PhpTools\SSO\SSO;          // 更新

abstract class ApiBase
{
    protected ?SSO $sso = null;

    public function setSso(SSO $sso): void { $this->sso = $sso; }

    protected function extractCredentials(Request $request): array
    {
        $token = $request->getHeader('X-API-Token') ?? $request->getQuery('token');
        $authCode = $request->getHeader('X-API-AuthCode') ?? $request->getQuery('auth_code');
        return ['token' => $token, 'auth_code' => $authCode];
    }

    protected function authenticate(Request $request): int
    {
        if ($this->sso === null) {
            throw new \RuntimeException('SSO service not set');
        }
        $credentials = $this->extractCredentials($request);
        if ($credentials['token'] === null || $credentials['auth_code'] === null) {
            throw new \RuntimeException('Missing credentials');
        }
        return $this->sso->verify($credentials['token'], $credentials['auth_code'], $request->getClientIp());
    }

    protected function json($data, int $status = 200): Response { return Response::json($data, $status); }
    protected function error(string $message, int $status = 400): Response { return Response::error($message, $status); }
}