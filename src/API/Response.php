<?php

declare(strict_types=1);

namespace PhpTools\API;

class Response
{
    private int $statusCode = 200;
    private array $headers = [];
    private $body;

    public function setStatusCode(int $code): self { $this->statusCode = $code; return $this; }
    public function setHeader(string $name, string $value): self { $this->headers[$name] = $value; return $this; }
    public function setBody($body): self { $this->body = $body; return $this; }

    public function send(): void
    {
        http_response_code($this->statusCode);
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($this->body, JSON_UNESCAPED_UNICODE);
    }

    public static function json($data, int $status = 200): self
    {
        $response = new self();
        $response->setStatusCode($status)->setBody($data);
        return $response;
    }

    public static function error(string $message, int $status = 400): self
    {
        return self::json(['error' => $message], $status);
    }
}