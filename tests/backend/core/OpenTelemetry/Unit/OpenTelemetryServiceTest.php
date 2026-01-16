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
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use PHPUnit\Framework\TestCase;

class OpenTelemetryServiceTest extends TestCase
{
    public function testIsEnabledReturnsFalseWhenEndpointEmpty(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $this->assertFalse($service->isEnabled());
    }

    public function testIsEnabledReturnsTrueWhenEndpointConfigured(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: 'http://collector:4318',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $this->assertTrue($service->isEnabled());
    }

    public function testGetTracerProviderReturnsTracerProviderInterface(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $provider = $service->getTracerProvider();

        $this->assertInstanceOf(TracerProviderInterface::class, $provider);
    }

    public function testGetTracerReturnsTracerInterface(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $tracer = $service->getTracer();

        $this->assertInstanceOf(TracerInterface::class, $tracer);
    }

    public function testGetTracerWithCustomName(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $tracer = $service->getTracer('custom-tracer', '2.0.0');

        $this->assertInstanceOf(TracerInterface::class, $tracer);
    }

    public function testStartSpanReturnsSpanInterface(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $span = $service->startSpan('test-span');

        $this->assertNotNull($span);
        $span->end();
    }

    public function testStartSpanWithKindAndAttributes(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $span = $service->startSpan(
            'http-request',
            SpanKind::KIND_SERVER,
            ['http.method' => 'GET', 'http.url' => '/api/test']
        );

        $this->assertNotNull($span);
        $span->end();
    }

    public function testTracerProviderIsCached(): void
    {
        $service = new OpenTelemetryService(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: ''
        );

        $provider1 = $service->getTracerProvider();
        $provider2 = $service->getTracerProvider();

        $this->assertSame($provider1, $provider2);
    }
}
