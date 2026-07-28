<?php
declare(strict_types=1);
namespace PhpTools\API\Middleware;
use PhpTools\API\Request;
use PhpTools\API\Response;
interface MiddlewareInterface {
    public function handle(Request $request, callable $next): Response;
}