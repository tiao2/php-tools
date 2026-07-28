<?php
declare(strict_types=1);
namespace PhpTools\API\Middleware;
use PhpTools\API\Request;
use PhpTools\API\Response;
use PhpTools\SSO\SSO;               

class AuthMiddleware implements MiddlewareInterface
{
    private SSO $sso;
    private bool $required;

    public function __construct(SSO $sso, bool $required = true)
    {
        $this->sso = $sso;
        $this->required = $required;
    }

    public function handle(Request $request, callable $next): Response
    {
        if (!$this->required) return $next($request);
        $token = $request->getHeader('X-API-Token') ?? $request->getQuery('token');
        $authCode = $request->getHeader('X-API-AuthCode') ?? $request->getQuery('auth_code');
        if ($token === null || $authCode === null) return Response::error('Authentication required', 401);
        try {
            $userId = $this->sso->verify($token, $authCode, $request->getClientIp());
            $request->userId = $userId;
            return $next($request);
        } catch (\Exception $e) {
            return Response::error('Invalid credentials', 401);
        }
    }
}