<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use PhpTools\API\Request;          // 更新
use PhpTools\API\Router;
use PhpTools\API\Response;
use PhpTools\API\Middleware\AuthMiddleware;
use PhpTools\API\Middleware\RateLimitMiddleware;
use PhpTools\API\Middleware\VersionMiddleware;
use PhpTools\API\RateLimiter\MemoryRateLimiter;
use PhpTools\API\RateLimiter\RedisRateLimiter;
use PhpTools\SSO\SSO;                // 更新
use PhpTools\SSO\AuthenticatorInterface;
use PhpTools\ModuleManager;

$moduleManager = new ModuleManager($_ENV);

$sso = null;
if ($moduleManager->isEnabled('SSO')) {
    $authenticator = new class implements AuthenticatorInterface {
        public function authenticate(string $username, string $password): ?int {
            if ($username === 'admin' && $password === '123456') {
                return 1;
            }
            return null;
        }
    };
    $sso = SSO::fromEnv($authenticator);
}

if ($moduleManager->isEnabled('API')) {
    $router = new Router();

    if (filter_var($_ENV['API_RATE_LIMIT_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
        $driver = $_ENV['API_RATE_LIMIT_DRIVER'] ?? 'memory';
        $requests = (int)($_ENV['API_RATE_LIMIT_REQUESTS'] ?? 100);
        $window = (int)($_ENV['API_RATE_LIMIT_WINDOW'] ?? 60);

        if ($driver === 'redis') {
            $redis = new \Redis();
            $redis->connect($_ENV['REDIS_HOST'], (int)$_ENV['REDIS_PORT']);
            if (!empty($_ENV['REDIS_PASSWORD'])) {
                $redis->auth($_ENV['REDIS_PASSWORD']);
            }
            $limiter = new RedisRateLimiter($redis, $requests, $window);
        } else {
            $limiter = new MemoryRateLimiter($requests, $window);
        }
        $router->addGlobalMiddleware(new RateLimitMiddleware($limiter, $driver));
    }

    if (filter_var($_ENV['API_AUTH_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
        if ($sso !== null) {
            $router->addGlobalMiddleware(new AuthMiddleware($sso, true));
        } else {
            trigger_error('API_AUTH_ENABLED is true but SSO module is not available.', E_USER_WARNING);
        }
    }

    if (filter_var($_ENV['API_VERSION_ENABLED'] ?? 'true', FILTER_VALIDATE_BOOLEAN)) {
        $versionMap = ['v1' => 'App\Controller\V1\\'];
        $router->addGlobalMiddleware(new VersionMiddleware($versionMap));
    }

    $router->get('/user', function (Request $req) {
        if ($req->userId) {
            return Response::json(['user_id' => $req->userId, 'version' => $req->getVersion()]);
        }
        return Response::error('Not authenticated', 401);
    });

    $request = new Request();
    $response = $router->dispatch($request);
    $response->send();
} else {
    http_response_code(404);
    echo 'API module is disabled';
}