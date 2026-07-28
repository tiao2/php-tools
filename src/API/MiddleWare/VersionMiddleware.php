<?php
declare(strict_types=1);
namespace PhpTools\API\Middleware;
use PhpTools\API\Request;
use PhpTools\API\Response;

class VersionMiddleware implements MiddlewareInterface
{
    private array $versionMap;
    public function __construct(array $versionMap) { $this->versionMap = $versionMap; }

    public function handle(Request $request, callable $next): Response
    {
        $version = $request->getHeader('X-API-Version');
        if ($version !== null && isset($this->versionMap[$version])) {
            $request->versionPrefix = $this->versionMap[$version];
        }
        return $next($request);
    }
}