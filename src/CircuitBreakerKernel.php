<?php

declare(strict_types=1);

namespace Zip\Http;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CircuitBreakerKernel wraps a Kernel and adds circuit breaker logic for resilience.
 * It tracks failures and temporarily blocks requests if too many failures occur,
 * automatically recovering after a timeout.
 *
 * @psalm-api
 */
final class CircuitBreakerKernel implements RequestHandlerInterface
{
    /**
     * Number of consecutive failures.
     */
    protected int $failures = 0;

    /**
     * Timestamp of the last failure (microseconds since epoch).
     */
    protected ?float $lastFailureTime = null;

    /**
     * Whether the circuit is currently open (blocking requests).
     */
    protected bool $isOpen = false;

    /**
     * Constructs the CircuitBreakerKernel with a kernel and configuration.
     *
     * @param  RequestHandlerInterface  $kernel  The underlying kernel to delegate requests to
     * @param  int  $failureThreshold  Number of failures before opening the circuit
     * @param  float  $recoveryTimeout  Time in seconds before attempting recovery
     */
    public function __construct(
        protected RequestHandlerInterface $kernel,
        protected int $failureThreshold = 5,
        protected float $recoveryTimeout = 60.0, // seconds
    ) {
        //
    }

    /**
     * Handles an incoming HTTP request, applying circuit breaker logic.
     * If the circuit is open, throws an exception. Otherwise, delegates to the kernel.
     *
     * @param  ServerRequestInterface  $request  The incoming request
     * @return ResponseInterface The response from the kernel
     *
     * @throws \RuntimeException If the circuit breaker is open
     * @throws \Throwable If the underlying kernel throws an exception
     */
    #[Override]
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Block request if circuit is open
        if ($this->isCircuitOpen()) {
            throw new \RuntimeException('Circuit breaker is open', 503);
        }

        try {
            // Try to handle the request
            $response = $this->kernel->handle($request);
            $this->onSuccess(); // Reset failure count on success

            return $response;
        } catch (\Throwable $e) {
            $this->onFailure(); // Increment failure count on error
            throw $e;
        }
    }

    /**
     * Checks if the circuit is open (blocking requests).
     * If the recovery timeout has passed, resets the circuit.
     *
     * @return bool True if circuit is open, false otherwise
     */
    protected function isCircuitOpen(): bool
    {
        if (! $this->isOpen) {
            return false;
        }

        // If enough time has passed, close the circuit and reset failures
        if (microtime(true) - $this->lastFailureTime > $this->recoveryTimeout) {
            $this->isOpen = false;
            $this->failures = 0;
        }

        return $this->isOpen;
    }

    /**
     * Called on successful request; resets failure count and closes circuit.
     */
    protected function onSuccess(): void
    {
        $this->failures = 0;
        $this->isOpen = false;
    }

    /**
     * Called on failed request; increments failure count and opens circuit if needed.
     */
    protected function onFailure(): void
    {
        $this->failures++;
        $this->lastFailureTime = microtime(true);

        // Open circuit if failure threshold reached
        if ($this->failures >= $this->failureThreshold) {
            $this->isOpen = true;
        }
    }
}
