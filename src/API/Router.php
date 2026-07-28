<?php

declare(strict_types=1);

namespace PhpTools\API;

use PhpTools\API\Middleware\MiddlewareInterface;

class Router
{
    private array $routes = [];
    private array $globalMiddlewares = [];

    public function addGlobalMiddleware(MiddlewareInterface $middleware): self
    {
        $this->globalMiddlewares[] = $middleware;
        return $this;
    }

    public function addRoute(string $method, string $path, callable $handler): self
    {
        $this->routes[] = ['method' => strtoupper($method), 'path' => $path, 'handler' => $handler];
        return $this;
    }

    public function get(string $path, callable $handler): self { return $this->addRoute('GET', $path, $handler); }
    public function post(string $path, callable $handler): self { return $this->addRoute('POST', $path, $handler); }
    public function put(string $path, callable $handler): self { return $this->addRoute('PUT', $path, $handler); }
    public function delete(string $path, callable $handler): self { return $this->addRoute('DELETE', $path, $handler); }

    public function dispatch(Request $request): Response
    {
        $requestMethod = $request->getMethod();
        $requestUri = $request->getUri();

        foreach ($this->routes as $route) {
            if ($route['method'] !== $requestMethod) continue;

            $pattern = $this->convertPathToPattern($route['path']);
            if (preg_match($pattern, $requestUri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];
                $next = function (Request $req) use ($handler, $params) {
                    return $handler($req, ...array_values($params));
                };
                foreach (array_reverse($this->globalMiddlewares) as $middleware) {
                    $currentNext = $next;
                    $next = function (Request $req) use ($middleware, $currentNext) {
                        return $middleware->handle($req, $currentNext);
                    };
                }
                return $next($request);
            }
        }
        return Response::error('Not Found', 404);
    }

    private function convertPathToPattern(string $path): string
    {
        $pattern = preg_replace('/\{([a-zA-Z_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $pattern . '$#';
    }
}