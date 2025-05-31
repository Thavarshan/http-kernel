<?php

declare(strict_types=1);

namespace Zip\Http;

use Closure;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * PerformanceKernel wraps a Kernel and records performance metrics for each request.
 * If a metrics callback is provided, it is called with timing and status information.
 *
 * @psalm-api
 */
final readonly class PerformanceKernel implements RequestHandlerInterface
{
    /**
     * Constructs the PerformanceKernel with a kernel and an optional metrics callback.
     *
     * @param  RequestHandlerInterface  $kernel  The underlying kernel to handle requests
     * @param  Closure|null  $metricsCallback  Optional callback to record metrics
     */
    public function __construct(
        protected RequestHandlerInterface $kernel,
        protected ?Closure $metricsCallback = null,
    ) {
        //
    }

    /**
     * Handles an HTTP request and records performance metrics if a callback is set.
     *
     * @param  ServerRequestInterface  $request  The incoming request
     * @return ResponseInterface The response from the kernel
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // If no metrics callback, just delegate to the kernel
        if (! $this->metricsCallback) {
            return $this->kernel->handle($request);
        }
        // Start timing
        $start = hrtime(true);
        $response = $this->kernel->handle($request);
        // Calculate duration in milliseconds
        $duration = (hrtime(true) - $start) / 1_000_000;
        // Call the metrics callback with timing and status info
        ($this->metricsCallback)([
            'duration_ms' => $duration,
            'status' => $response->getStatusCode(),
            'method' => $request->getMethod(),
        ]);

        return $response;
    }
}
