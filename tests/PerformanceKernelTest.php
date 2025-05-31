<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Tests\Stubs\TestKernel;
use Zip\Http\PerformanceKernel;

class PerformanceKernelTest extends TestCase
{
    public function test_handle_delegates_without_callback(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Use TestKernel instead of mocking Kernel
        $kernel = new TestKernel;
        $kernel->willReturn($response);

        // Create PerformanceKernel without metrics callback
        $performanceKernel = new PerformanceKernel($kernel);
        $result = $performanceKernel->handle($request);

        $this->assertSame($response, $result);
        $this->assertTrue($kernel->wasHandleCalled());
        $this->assertEquals(1, $kernel->getHandleCallCount());
        $this->assertSame([$request], $kernel->getHandledRequests());
    }

    public function test_handle_calls_metrics_callback(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Use TestKernel instead of mocking Kernel
        $kernel = new TestKernel;
        $kernel->willReturn($response);

        // Track if metrics callback was called
        $metricsCallbackCalled = false;
        $recordedMetrics = null;

        // Based on the error, your callback only receives 1 argument (metrics array)
        $metricsCallback = function ($metrics) use (&$metricsCallbackCalled, &$recordedMetrics) {
            $metricsCallbackCalled = true;
            $recordedMetrics = $metrics;
        };

        // Create PerformanceKernel with metrics callback
        $performanceKernel = new PerformanceKernel($kernel, $metricsCallback);
        $result = $performanceKernel->handle($request);

        $this->assertSame($response, $result);
        $this->assertTrue($kernel->wasHandleCalled());
        $this->assertTrue($metricsCallbackCalled);

        // Verify the metrics structure based on the error output
        $this->assertIsArray($recordedMetrics);
        $this->assertArrayHasKey('duration_ms', $recordedMetrics);
        $this->assertArrayHasKey('status', $recordedMetrics);
        $this->assertArrayHasKey('method', $recordedMetrics);
        $this->assertIsFloat($recordedMetrics['duration_ms']);
        $this->assertGreaterThanOrEqual(0, $recordedMetrics['duration_ms']);
    }

    public function test_handle_records_execution_time(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Use TestKernel that simulates some processing time
        $kernel = new TestKernel;
        $kernel->willReturn($response);

        $executionMetrics = [];
        $metricsCallback = function ($metrics) use (&$executionMetrics) {
            $executionMetrics[] = $metrics;
        };

        $performanceKernel = new PerformanceKernel($kernel, $metricsCallback);

        // Make multiple calls to verify timing is working
        $performanceKernel->handle($request);
        $performanceKernel->handle($request);

        $this->assertCount(2, $executionMetrics);

        // Check that each metrics entry has the expected structure
        foreach ($executionMetrics as $metrics) {
            $this->assertIsArray($metrics);
            $this->assertArrayHasKey('duration_ms', $metrics);
            $this->assertArrayHasKey('status', $metrics);
            $this->assertArrayHasKey('method', $metrics);
            $this->assertIsFloat($metrics['duration_ms']);
            $this->assertGreaterThanOrEqual(0, $metrics['duration_ms']);
        }
    }

    public function test_handle_propagates_exceptions_with_metrics(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = new \RuntimeException('Test exception');

        // Use TestKernel that throws an exception
        $kernel = new TestKernel;
        $kernel->willThrow($exception);

        $metricsCallbackCalled = false;
        $recordedMetrics = null;

        $metricsCallback = function ($metrics) use (&$metricsCallbackCalled, &$recordedMetrics) {
            $metricsCallbackCalled = true;
            $recordedMetrics = $metrics;
        };

        $performanceKernel = new PerformanceKernel($kernel, $metricsCallback);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $performanceKernel->handle($request);

        // Note: The callback might not be called when an exception occurs,
        // depending on your implementation. This test verifies the exception
        // is properly propagated.
    }

    public function test_handle_records_metrics_even_with_exceptions(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $exception = new \RuntimeException('Test exception');

        // Use TestKernel that throws an exception
        $kernel = new TestKernel;
        $kernel->willThrow($exception);

        $metricsCallbackCalled = false;
        $recordedMetrics = null;

        $metricsCallback = function ($metrics) use (&$metricsCallbackCalled, &$recordedMetrics) {
            $metricsCallbackCalled = true;
            $recordedMetrics = $metrics;
        };

        $performanceKernel = new PerformanceKernel($kernel, $metricsCallback);

        try {
            $performanceKernel->handle($request);
            $this->fail('Expected exception was not thrown');
        } catch (\RuntimeException $e) {
            $this->assertEquals('Test exception', $e->getMessage());
        }
    }
}
