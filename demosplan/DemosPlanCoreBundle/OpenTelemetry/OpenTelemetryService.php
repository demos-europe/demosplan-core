<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\OpenTelemetry;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\Sampler\AlwaysOnSampler;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Central OpenTelemetry service for distributed tracing.
 *
 * Provides TracerProvider and convenience methods for creating spans.
 */
class OpenTelemetryService
{
    private ?TracerProviderInterface $tracerProvider = null;
    private string $otlpEndpoint;
    private string $serviceName;
    private string $serviceVersion;
    private string $environment;
    private string $tenantId;

    public function __construct(
        #[Autowire('%otel_exporter_endpoint%')] string $otlpEndpoint,
        #[Autowire('%otel_service_name%')] string $serviceName,
        #[Autowire('%project_version%')] string $serviceVersion = '1.0.0',
        #[Autowire('%kernel.environment%')] string $environment = 'prod',
        #[Autowire('%otel_tenant_id%')] string $tenantId = '',
    ) {
        $this->otlpEndpoint = $otlpEndpoint;
        $this->serviceName = $serviceName;
        $this->serviceVersion = $serviceVersion;
        $this->environment = $environment;
        $this->tenantId = $tenantId;
    }

    /**
     * Check if OpenTelemetry tracing is enabled.
     */
    public function isEnabled(): bool
    {
        return '' !== $this->otlpEndpoint;
    }

    /**
     * Get the TracerProvider instance.
     */
    public function getTracerProvider(): TracerProviderInterface
    {
        if (null !== $this->tracerProvider) {
            return $this->tracerProvider;
        }

        if (!$this->isEnabled()) {
            // Return a no-op tracer provider when disabled
            $this->tracerProvider = new TracerProvider();

            return $this->tracerProvider;
        }

        // Create transport for OTLP HTTP protocol
        $transport = (new OtlpHttpTransportFactory())->create(
            rtrim($this->otlpEndpoint, '/').'/v1/traces',
            'application/json'
        );

        // Create span exporter
        $exporter = new SpanExporter($transport);

        // Create resource with service information
        $attributes = [
            'service.name'           => $this->serviceName,
            'service.version'        => $this->serviceVersion,
            'deployment.environment' => $this->environment,
        ];
        if ('' !== $this->tenantId) {
            $attributes['tenant.id'] = $this->tenantId;
        }
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(Attributes::create($attributes))
        );

        // Build TracerProvider
        $this->tracerProvider = TracerProvider::builder()
            ->addSpanProcessor(new SimpleSpanProcessor($exporter))
            ->setResource($resource)
            ->setSampler(new AlwaysOnSampler())
            ->build();

        return $this->tracerProvider;
    }

    /**
     * Get a tracer instance for the given instrumentation scope.
     */
    public function getTracer(string $name = 'demosplan', ?string $version = null): TracerInterface
    {
        return $this->getTracerProvider()->getTracer($name, $version ?? $this->serviceVersion);
    }

    /**
     * Start a new span with the given name.
     *
     * @param 0|1|2|3|4 $kind One of SpanKind::KIND_* constants
     */
    public function startSpan(
        string $name,
        int $kind = SpanKind::KIND_INTERNAL,
        ?iterable $attributes = null,
    ): SpanInterface {
        /** @var 0|1|2|3|4 $kind */
        $spanBuilder = $this->getTracer()->spanBuilder($name)->setSpanKind($kind);

        if (null !== $attributes) {
            $spanBuilder->setAttributes($attributes);
        }

        return $spanBuilder->startSpan();
    }

    /**
     * Shutdown the tracer provider and flush any remaining spans.
     */
    public function shutdown(): void
    {
        if ($this->tracerProvider instanceof TracerProvider) {
            $this->tracerProvider->shutdown();
        }
    }
}
