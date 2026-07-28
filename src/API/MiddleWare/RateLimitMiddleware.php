<?php
declare(strict_types=1);
namespace PhpTools\API\Middleware;
use PhpTools\API\Request;
use PhpTools\API\Response;
use PhpTools\API\RateLimiter\RateLimiterInterface;

class RateLimitMiddleware implements MiddlewareInterface
{
    private RateLimiterInterface $limiter;
    private string $driver;

    public function __construct(RateLimiterInterface $limiter, string $driver = 'memory')
    {
        $this->limiter = $limiter;
        $this->driver = $driver;
    }

    public function handle(Request $request, callable $next): Response
    {
        $identifier = $request->userId ? 'user_' . $request->userId : 'ip_' . $request->getClientIp();
        $key = $this->driver . ':' . $identifier;
        if (!$this->limiter->allow($key)) {
            $retryAfter = $this->limiter->getRetryAfter($key);
            $response = Response::error('Too Many Requests', 429);
            if ($retryAfter > 0) $response->setHeader('Retry-After', (string)$retryAfter);
            return $response;
        }
        return $next($request);
    }
}