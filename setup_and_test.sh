#!/bin/bash
set -e

echo "📦 配置 PHP Tools 环境..."

# 1. 备份原始文件（如果需要）
cp -n index.php index.php.orig 2>/dev/null || true
cp -n src/API/Middleware/AuthMiddleware.php src/API/Middleware/AuthMiddleware.orig 2>/dev/null || true
cp -n src/API/Request.php src/API/Request.orig 2>/dev/null || true

# 2. 覆盖 AuthMiddleware（跳过 /login）
cat > src/API/Middleware/AuthMiddleware.php <<'EOF'
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
        // 跳过 /login 路由
        if (strpos($request->getUri(), "/login") === 0) { return $next($request); }

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
EOF

# 3. 覆盖 Request.php（支持带参数的 Content-Type）
cat > src/API/Request.php <<'EOF'
<?php
declare(strict_types=1);
namespace PhpTools\API;

class Request
{
    public array $query;
    public array $post;
    public array $headers;
    public string $method;
    public string $uri;
    public array $server;
    public ?int $userId = null;
    public ?string $version = null;
    public ?string $versionPrefix = null;

    public function __construct()
    {
        $this->query = $_GET;
        $this->post = $_POST;
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->server = $_SERVER;
        $this->headers = $this->getAllHeaders();
        $this->version = $this->headers['X-API-Version'] ?? null;
    }

    public function getQuery(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function getPost(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null) return $this->post;
        return $this->post[$key] ?? $default;
    }

    public function getHeader(string $name, $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    public function getMethod(): string { return $this->method; }
    public function getUri(): string { return $this->uri; }

    public function getClientIp(): string
    {
        return $this->server['REMOTE_ADDR'] ?? '127.0.0.1';
    }

    public function getJsonBody(): ?array
    {
        $contentType = $this->getHeader('Content-Type');
        if ($contentType !== null && strpos($contentType, 'application/json') === 0) {
            $raw = file_get_contents('php://input');
            if ($raw === false) return null;
            $data = json_decode($raw, true);
            return is_array($data) ? $data : null;
        }
        return null;
    }

    public function getVersion(): ?string { return $this->version; }

    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders();
        }
        $headers = [];
        foreach ($_SERVER as $name => $value) {
            if (strpos($name, 'HTTP_') === 0) {
                $headers[str_replace('_', '-', strtolower(substr($name, 5)))] = $value;
            }
        }
        return $headers;
    }
}
EOF

# 4. 更新 index.php（确保包含 /login 路由）
cat > index.php <<'EOF'
<?php
declare(strict_types=1);
require __DIR__ . '/bootstrap.php';
use PhpTools\API\Request;
use PhpTools\API\Router;
use PhpTools\API\Response;
use PhpTools\API\Middleware\AuthMiddleware;
use PhpTools\API\Middleware\RateLimitMiddleware;
use PhpTools\API\Middleware\VersionMiddleware;
use PhpTools\API\RateLimiter\MemoryRateLimiter;
use PhpTools\API\RateLimiter\RedisRateLimiter;
use PhpTools\SSO\SSO;
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
            if (!empty($_ENV['REDIS_PASSWORD'])) $redis->auth($_ENV['REDIS_PASSWORD']);
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

    // 登录路由
    $router->addRoute('POST', '/login', function (Request $req) use ($sso) {
        $data = $req->getJsonBody();
        if (!isset($data['username']) || !isset($data['password'])) {
            return Response::error('Missing username or password', 400);
        }
        try {
            $result = $sso->login($data['username'], $data['password'], $req->getClientIp());
            return Response::json($result);
        } catch (\Exception $e) {
            return Response::error($e->getMessage(), 401);
        }
    });

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
EOF

echo "✅ 文件配置完成！"
echo "🚀 启动 PHP 服务器（按 Ctrl+C 可停止）..."
php -S localhost:8080 &
SERVER_PID=$!
sleep 2  # 等待服务器启动

echo "🧪 测试登录接口..."
curl -X POST http://localhost:8080/login \
  -H "Content-Type: application/json" \
  -d '{"username":"admin","password":"123456"}' \
  -w "\nHTTP Status: %{http_code}\n"

echo "✅ 测试完成。服务器仍在运行（PID: $SERVER_PID）。"
echo "按 Ctrl+C 停止服务器。"
wait $SERVER_PID
