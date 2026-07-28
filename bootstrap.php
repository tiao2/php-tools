<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use Dotenv\Dotenv;
use PhpTools\API\ExceptionHandler;  // 更新命名空间

$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$debug = filter_var($_ENV['APP_DEBUG'] ?? 'false', FILTER_VALIDATE_BOOLEAN);
$handler = new ExceptionHandler($debug);

$handler->setLogger(function (Throwable $e) use ($debug): void {
    $log = sprintf(
        "[%s] %s: %s in %s:%d\nStack trace:\n%s\n",
        date('Y-m-d H:i:s'),
        get_class($e),
        $e->getMessage(),
        $e->getFile(),
        $e->getLine(),
        $e->getTraceAsString()
    );
    error_log($log);
});

set_exception_handler([$handler, 'handle']);

set_error_handler(function (int $severity, string $message, string $file, int $line): bool {
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () use ($handler): void {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        $exception = new \ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
        $handler->handle($exception);
    }
});

return $handler;