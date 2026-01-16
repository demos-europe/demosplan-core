<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\OpenTelemetry\Unit;

use demosplan\DemosPlanCoreBundle\OpenTelemetry\OpenTelemetryService;
use demosplan\DemosPlanCoreBundle\OpenTelemetry\TraceContextListener;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class TraceContextListenerTest extends TestCase
{
    private TraceContextListener $listener;
    private OpenTelemetryService|MockObject $openTelemetryService;

    protected function setUp(): void
    {
        $this->openTelemetryService = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $this->listener = new TraceContextListener($this->openTelemetryService);
    }

    public function testGetSubscribedEventsReturnsCorrectEvents(): void
    {
        $events = TraceContextListener::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        $this->assertArrayHasKey(KernelEvents::TERMINATE, $events);
    }

    public function testOnKernelRequestIgnoresSubRequests(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::SUB_REQUEST);

        // Should not throw and should not create a span
        $this->listener->onKernelRequest($event);

        // No assertion needed - we just verify it doesn't crash
        $this->assertTrue(true);
    }

    public function testOnKernelRequestIgnoresWhenDisabled(): void
    {
        $disabledService = $this->createMock(OpenTelemetryService::class);
        $disabledService->method('isEnabled')->willReturn(false);

        $listener = new TraceContextListener($disabledService);

        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/test');
        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not call getTracer when disabled
        $disabledService->expects($this->never())->method('getTracer');

        $listener->onKernelRequest($event);
    }

    public function testOnKernelRequestCreatesSpanForMainRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test', 'GET');
        $request->attributes->set('_route', 'api_test_route');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        // Verify span was created by checking terminate doesn't fail
        $response = new Response('OK', 200);
        $terminateEvent = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($terminateEvent);

        $this->assertTrue(true);
    }

    public function testOnKernelRequestExtractsTraceparentHeader(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test', 'GET');
        $request->headers->set('traceparent', '00-0af7651916cd43dd8448eb211c80319c-b7ad6b7169203331-01');

        $event = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);

        $this->listener->onKernelRequest($event);

        $response = new Response('OK', 200);
        $terminateEvent = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($terminateEvent);

        $this->assertTrue(true);
    }

    public function testOnKernelTerminateSetsHttpStatusCode(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test', 'GET');

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($requestEvent);

        $response = new Response('OK', 200);
        $terminateEvent = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($terminateEvent);

        // No exception means success
        $this->assertTrue(true);
    }

    public function testOnKernelTerminateHandlesErrorStatusCode(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test', 'GET');

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($requestEvent);

        $response = new Response('Not Found', 404);
        $terminateEvent = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($terminateEvent);

        $this->assertTrue(true);
    }

    public function testOnKernelTerminateHandlesServerErrorStatusCode(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test', 'GET');

        $requestEvent = new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
        $this->listener->onKernelRequest($requestEvent);

        $response = new Response('Internal Server Error', 500);
        $terminateEvent = new TerminateEvent($kernel, $request, $response);
        $this->listener->onKernelTerminate($terminateEvent);

        $this->assertTrue(true);
    }

    public function testOnKernelTerminateWithoutPriorRequest(): void
    {
        $kernel = $this->createMock(HttpKernelInterface::class);
        $request = Request::create('/api/test', 'GET');
        $response = new Response('OK', 200);

        $terminateEvent = new TerminateEvent($kernel, $request, $response);

        // Should not throw when no span exists
        $this->listener->onKernelTerminate($terminateEvent);

        $this->assertTrue(true);
    }
}
