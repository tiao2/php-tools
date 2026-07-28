<?php
declare(strict_types=1);
namespace PhpTools\API;
use PhpTools\API\Exception\HttpException;
use PhpTools\Community\Exception\UnauthorizedException;
use PhpTools\Community\Exception\ForbiddenException;

class ExceptionHandler
{
    private bool $debug;
    private ?callable $logger = null;
    private ?callable $customHandler = null;

    public function __construct(bool $debug = false, ?callable $logger = null)
    {
        $this->debug = $debug;
        $this->logger = $logger;
    }

    public function setLogger(callable $logger): void { $this->logger = $logger; }
    public function setCustomHandler(callable $handler): void { $this->customHandler = $handler; }

    public function handle(\Throwable $e): void
    {
        if ($this->logger) ($this->logger)($e);
        if ($this->customHandler) {
            ($this->customHandler)($e);
            return;
        }
        if ($this->isApiRequest()) {
            $this->renderApiError($e);
        } else {
            $this->renderHtmlError($e);
        }
    }

    private function isApiRequest(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        return stripos($accept, 'application/json') !== false || strpos($uri, '/api') === 0;
    }

    private function renderApiError(\Throwable $e): void
    {
        $statusCode = 500;
        if ($e instanceof HttpException) {
            $statusCode = $e->getStatusCode();
        } elseif ($e instanceof UnauthorizedException) {
            $statusCode = 401;
        } elseif ($e instanceof ForbiddenException) {
            $statusCode = 403;
        }
        $body = ['error' => $this->debug ? $e->getMessage() : 'Internal Server Error'];
        if ($this->debug) {
            $body['file'] = $e->getFile();
            $body['line'] = $e->getLine();
            $body['trace'] = $e->getTraceAsString();
        }
        (new Response())->setStatusCode($statusCode)->setBody($body)->send();
    }

    private function renderHtmlError(\Throwable $e): void
    {
        http_response_code(500);
        if ($this->debug) {
            echo '<h1>Error: ' . htmlspecialchars($e->getMessage()) . '</h1>';
            echo '<p>File: ' . htmlspecialchars($e->getFile()) . ':' . $e->getLine() . '</p>';
            echo '<pre>' . htmlspecialchars($e->getTraceAsString()) . '</pre>';
        } else {
            echo '<h1>Internal Server Error</h1>';
        }
    }
}