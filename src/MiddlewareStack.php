<?php

declare(strict_types=1);

namespace Zip\Http;

use Override;
use Psr\Container\ContainerInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * MiddlewareStack manages a stack of middleware for HTTP request processing.
 * It supports adding middleware, compiling the stack into a pipeline, and caching
 * middleware instances for performance. Compilation is done once at boot.
 *
 * @psalm-api
 */
final class MiddlewareStack implements MiddlewareStackInterface
{
    /**
     * List of middleware (instances or class names).
     *
     * @var array<MiddlewareInterface|string>
     */
    protected array $middleware = [];

    /**
     * Cache of resolved middleware instances by class name.
     *
     * @var array<string, MiddlewareInterface>
     */
    protected array $instanceCache = [];

    /**
     * Cached compiled middleware pipeline.
     */
    protected ?RequestHandlerInterface $compiledPipeline = null;

    /**
     * Constructs the MiddlewareStack with an optional container for resolving middleware.
     *
     * @param  ContainerInterface|null  $container  Optional container for resolving middleware
     */
    public function __construct(
        protected ?ContainerInterface $container = null,
    ) {
        //
    }

    /**
     * Adds a middleware to the stack and invalidates the compiled pipeline cache.
     *
     * @param  MiddlewareInterface|string  $middleware  The middleware instance or class name
     */
    #[Override]
    public function add(MiddlewareInterface|string $middleware): void
    {
        $this->middleware[] = $middleware;
        // Invalidate the compiled pipeline cache when middleware changes
        $this->compiledPipeline = null;
    }

    /**
     * Compiles the middleware stack into an optimized handler pipeline.
     * This is called once at boot for maximum performance.
     *
     * @param  RequestHandlerInterface  $handler  The final handler
     * @return RequestHandlerInterface The compiled pipeline
     */
    #[Override]
    public function compile(RequestHandlerInterface $handler): RequestHandlerInterface
    {
        // Return cached pipeline if already compiled
        if ($this->compiledPipeline !== null) {
            return $this->compiledPipeline;
        }
        // If no middleware, return the handler directly
        if (empty($this->middleware)) {
            return $this->compiledPipeline = $handler;
        }
        // Resolve all middleware instances in reverse order (outermost first)
        $resolvedMiddleware = [];
        foreach (array_reverse($this->middleware) as $middleware) {
            $resolvedMiddleware[] = $this->resolveMiddleware($middleware);
        }
        // Build the pipeline by wrapping each middleware around the handler
        $pipeline = $handler;
        foreach ($resolvedMiddleware as $middleware) {
            $pipeline = new OptimizedMiddlewareHandler($middleware, $pipeline);
        }

        // Cache and return the compiled pipeline
        return $this->compiledPipeline = $pipeline;
    }

    /**
     * Resolves a middleware instance, using the cache if available.
     * If a class name is given, it is resolved via the container or instantiated directly.
     *
     * @param  MiddlewareInterface|string  $middleware  The middleware instance or class name
     * @return MiddlewareInterface The resolved middleware instance
     */
    protected function resolveMiddleware(MiddlewareInterface|string $middleware): MiddlewareInterface
    {
        // If already an instance, return it
        if ($middleware instanceof MiddlewareInterface) {
            return $middleware;
        }
        // Return cached instance if available
        if (isset($this->instanceCache[$middleware])) {
            return $this->instanceCache[$middleware];
        }
        // Resolve via container or instantiate directly
        $instance = $this->container?->get($middleware) ?? new $middleware();

        // Cache and return
        return $this->instanceCache[$middleware] = $instance;
    }
}
