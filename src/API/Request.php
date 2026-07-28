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

    public function getQuery(string $key = null, $default = null)
    {
        if ($key === null) return $this->query;
        return $this->query[$key] ?? $default;
    }

    public function getPost(string $key = null, $default = null)
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
        if ($this->getHeader('Content-Type') === 'application/json') {
            $raw = file_get_contents('php://input');
            return json_decode($raw, true) ?: null;
        }
        return null;
    }

    public function getVersion(): ?string { return $this->version; }

    private function getAllHeaders(): array
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
        } else {
            $headers = [];
            foreach ($_SERVER as $name => $value) {
                if (strpos($name, 'HTTP_') === 0) {
                    $headers[str_replace('_', '-', strtolower(substr($name, 5)))] = $value;
                }
            }
        }
        return $headers;
    }
}