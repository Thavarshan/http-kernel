<?php

declare(strict_types=1);

namespace Zip\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The Kernel class is the entry point for handling HTTP requests in the Zip framework.
 * It compiles the middleware stack once at boot for maximum performance, so each request
 * is processed with zero runtime overhead. The compiled pipeline is stored and reused.
 *
 * @psalm-api
 */
final readonly class Kernel implements RequestHandlerInterface
{
    /**
     * The pre-compiled middleware pipeline for handling requests.
     * This is built once at boot and reused for every request.
     */
    protected RequestHandlerInterface $compiledPipeline;

    /**
     * Constructs the Kernel and compiles the middleware pipeline.
     *
     * @param  RequestHandlerInterface  $handler  The final handler for requests
     * @param  MiddlewareStackInterface  $stack  The middleware stack to compile
     */
    public function __construct(
        RequestHandlerInterface $handler,
        MiddlewareStackInterface $stack,
    ) {
        // Compile the middleware pipeline once for all requests
        $this->compiledPipeline = $stack->compile($handler);
    }

    /**
     * Handles an incoming HTTP request using the pre-compiled pipeline.
     *
     * @param  ServerRequestInterface  $request  The incoming request
     * @return ResponseInterface The response from the pipeline
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Delegate to the pre-compiled pipeline for zero overhead
        return $this->compiledPipeline->handle($request);
    }
}
