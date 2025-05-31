<?php

declare(strict_types=1);

namespace Zip\Http;

use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * @psalm-api
 */
interface MiddlewareStackInterface
{
    /**
     * Adds a middleware to the stack.
     *
     * @param  MiddlewareInterface|string  $middleware  The middleware instance or class name
     *
     * @psalm-api
     */
    public function add(MiddlewareInterface|string $middleware): void;

    /**
     * Compiles the middleware stack into an optimized handler pipeline.
     *
     * @param  RequestHandlerInterface  $handler  The final handler
     * @return RequestHandlerInterface The compiled pipeline
     *
     * @psalm-api
     */
    public function compile(RequestHandlerInterface $handler): RequestHandlerInterface;
}
