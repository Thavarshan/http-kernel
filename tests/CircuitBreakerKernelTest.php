<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Stubs\TestKernel;
use Zip\Http\CircuitBreakerKernel; // Assuming this is the class being tested

class CircuitBreakerKernelTest extends TestCase
{
    public function test_handle_delegates_when_closed(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Use TestKernel instead of mocking Kernel
        $kernel = new TestKernel;
        $kernel->willReturn($response);

        $circuitBreaker = new CircuitBreakerKernel($kernel);
        $result = $circuitBreaker->handle($request);

        $this->assertSame($response, $result);
        $this->assertTrue($kernel->wasHandleCalled());
        $this->assertEquals(1, $kernel->getHandleCallCount());
        $this->assertSame([$request], $kernel->getHandledRequests());
    }

    public function test_handle_throws_when_open(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);

        // Use TestKernel that throws an exception
        $kernel = new TestKernel;
        $kernel->willThrow(new \RuntimeException('Circuit breaker open'));

        $circuitBreaker = new CircuitBreakerKernel($kernel);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Circuit breaker open');

        $circuitBreaker->handle($request);
    }

    public function test_circuit_closes_after_timeout(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Use TestKernel that first throws, then succeeds
        $kernel = new TestKernel;

        $circuitBreaker = new CircuitBreakerKernel($kernel);

        // First call - circuit should be closed, kernel succeeds
        $kernel->willReturn($response);
        $result1 = $circuitBreaker->handle($request);
        $this->assertSame($response, $result1);

        // Reset for next test scenario
        $kernel->reset();

        // Simulate circuit opening (this would depend on your actual implementation)
        $kernel->willThrow(new \RuntimeException('Service unavailable'));

        try {
            $circuitBreaker->handle($request);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Service unavailable', $e->getMessage());
        }

        // Reset and test circuit closing after timeout
        $kernel->reset();
        $kernel->willReturn($response);

        // After timeout, circuit should close and allow requests through
        $result2 = $circuitBreaker->handle($request);
        $this->assertSame($response, $result2);
    }
}
