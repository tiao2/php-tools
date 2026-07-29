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
        if (strpos($request->getUri(), "/login") === 0) {
            return $next($request);
        }

        if (!$this->required) {
            return $next($request);
        }

        // 从 $_SERVER 直接读取头部，避免 Request::getHeader 大小写问题
        // FIXME: Workaround for Request::getHeader case-sensitivity, should be fixed upstream
        $token = $_SERVER['HTTP_X_API_TOKEN'] ?? $_SERVER['HTTP_X_API_TOKEN'] ?? $request->getQuery('token');
        $authCode = $_SERVER['HTTP_X_API_AUTHCODE'] ?? $_SERVER['HTTP_X_API_AUTHCODE'] ?? $request->getQuery('auth_code');

        if ($token === null || $authCode === null) {
            return Response::error('Authentication required', 401);
        }

        try {
            $userId = $this->sso->verify($token, $authCode, $request->getClientIp());
            $request->userId = $userId;
            return $next($request);
        } catch (\Exception $e) {
            return Response::error('Invalid credentials: ' . $e->getMessage(), 401);
        }
    }
}
