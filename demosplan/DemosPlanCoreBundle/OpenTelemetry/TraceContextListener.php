<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\OpenTelemetry;

use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\Propagation\TraceContextPropagator;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Extracts trace context from incoming HTTP requests and creates root spans.
 *
 * Implements W3C Trace Context propagation via traceparent/tracestate headers.
 */
class TraceContextListener implements EventSubscriberInterface
{
    private OpenTelemetryService $openTelemetryService;
    private ?SpanInterface $rootSpan = null;
    private ?ScopeInterface $scope = null;

    public function __construct(OpenTelemetryService $openTelemetryService)
    {
        $this->openTelemetryService = $openTelemetryService;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST   => ['onKernelRequest', 255],
            KernelEvents::EXCEPTION => ['onKernelException', 0],
            KernelEvents::TERMINATE => ['onKernelTerminate', -255],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest() || !$this->openTelemetryService->isEnabled()) {
            return;
        }

        $request = $event->getRequest();

        // Extract parent context from incoming headers using W3C Trace Context
        $propagator = TraceContextPropagator::getInstance();
        $carrier = [];
        foreach ($request->headers->all() as $key => $values) {
            $carrier[strtolower($key)] = $values[0] ?? '';
        }
        $parentContext = $propagator->extract($carrier);

        // Create span name from route or URI
        $spanName = $request->attributes->get('_route', $request->getMethod().' '.$request->getPathInfo());

        // Start the root span for this request
        $tracer = $this->openTelemetryService->getTracer();
        $spanBuilder = $tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setParent($parentContext)
            ->setAttributes([
                'http.method'                 => $request->getMethod(),
                'http.url'                    => $request->getUri(),
                'http.target'                 => $request->getPathInfo(),
                'http.host'                   => $request->getHost(),
                'http.scheme'                 => $request->getScheme(),
                'http.user_agent'             => $request->headers->get('User-Agent', ''),
                'http.request_content_length' => $request->headers->get('Content-Length', 0),
            ]);

        // Add route parameters as attributes if available
        $route = $request->attributes->get('_route');
        if (null !== $route) {
            $spanBuilder->setAttribute('http.route', $route);
        }

        $this->rootSpan = $spanBuilder->startSpan();
        $this->scope = $this->rootSpan->activate();
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        if (null === $this->rootSpan) {
            return;
        }

        $exception = $event->getThrowable();
        $this->rootSpan->recordException($exception);
        $this->rootSpan->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (null === $this->rootSpan) {
            return;
        }

        $response = $event->getResponse();
        $statusCode = $response->getStatusCode();

        // Add response attributes
        $this->rootSpan->setAttribute('http.status_code', $statusCode);
        $this->rootSpan->setAttribute('http.response_content_length', strlen($response->getContent() ?: ''));

        // Set span status based on HTTP status code
        if ($statusCode >= 400) {
            $this->rootSpan->setStatus(
                $statusCode >= 500 ? StatusCode::STATUS_ERROR : StatusCode::STATUS_UNSET,
                "HTTP $statusCode"
            );
        }

        // End the span and detach the scope
        $this->rootSpan->end();
        $this->scope?->detach();
        $this->rootSpan = null;
        $this->scope = null;

        // Flush remaining spans
        $this->openTelemetryService->shutdown();
    }
}
