<?php
declare(strict_types=1);
namespace PhpTools\API\Exception;
class RateLimitExceededException extends HttpException {
    public function __construct(string $message = 'Too Many Requests', int $statusCode = 429, \Throwable $previous = null) {
        parent::__construct($message, $statusCode, $previous);
    }
}