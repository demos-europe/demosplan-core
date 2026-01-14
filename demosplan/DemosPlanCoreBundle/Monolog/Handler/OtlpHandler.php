<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Monolog\Handler;

use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Logs\LogRecord as OtelLogRecord;
use OpenTelemetry\API\Logs\LoggerProviderInterface;
use OpenTelemetry\API\Logs\Severity;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Instrumentation\InstrumentationScopeFactory;
use OpenTelemetry\SDK\Logs\LoggerProvider;
use OpenTelemetry\SDK\Logs\Processor\SimpleLogRecordProcessor;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;

/**
 * Monolog handler that sends logs to OpenTelemetry Collector via OTLP HTTP.
 *
 * Based on the pattern from "Track Every Request: Symfony Monitoring with OpenTelemetry and Grafana"
 * by laurentmn (Dec 2025).
 */
class OtlpHandler extends AbstractProcessingHandler
{
    private ?LoggerProviderInterface $loggerProvider = null;
    private string $serviceName;
    private string $otlpEndpoint;
    private string $serviceVersion;
    private string $environment;

    public function __construct(
        string $otlpEndpoint,
        string $serviceName,
        string $serviceVersion = '1.0.0',
        string $environment = 'prod',
        int|string|Level $level = Level::Info,
        bool $bubble = true,
    ) {
        parent::__construct($level, $bubble);
        $this->otlpEndpoint = $otlpEndpoint;
        $this->serviceName = $serviceName;
        $this->serviceVersion = $serviceVersion;
        $this->environment = $environment;
    }

    protected function write(LogRecord $record): void
    {
        // Skip if no endpoint configured (empty = disabled)
        if ('' === $this->otlpEndpoint) {
            return;
        }

        $loggerProvider = $this->getLoggerProvider();
        $logger = $loggerProvider->getLogger($this->serviceName);

        // Convert Monolog level to OpenTelemetry severity
        $severity = Severity::fromPsr3($record->level->toPsrLogLevel());

        // Build the log record following the PDF pattern
        $logRecord = (new OtelLogRecord($record->message))
            ->setTimestamp((int) $record->datetime->format('Uu') * 1000) // microseconds to nanoseconds
            ->setSeverityNumber($severity)
            ->setSeverityText($record->level->name)
            ->setAttributes([
                'level' => $record->level->name,
                'channel' => $record->channel,
                'context' => json_encode($record->context, JSON_THROW_ON_ERROR),
                'extra' => json_encode($record->extra, JSON_THROW_ON_ERROR),
            ]);

        $logger->emit($logRecord);
    }

    private function getLoggerProvider(): LoggerProviderInterface
    {
        if (null !== $this->loggerProvider) {
            return $this->loggerProvider;
        }

        // Create transport for OTLP HTTP protocol
        $transport = (new OtlpHttpTransportFactory())->create(
            rtrim($this->otlpEndpoint, '/').'/v1/logs',
            'application/json'
        );

        // Create exporter with transport
        $exporter = new LogsExporter($transport);

        // Create resource with service information
        $resource = ResourceInfoFactory::emptyResource()->merge(
            ResourceInfo::create(
                Attributes::create([
                    'service.name' => $this->serviceName,
                    'service.version' => $this->serviceVersion,
                    'deployment.environment' => $this->environment,
                ])
            )
        );

        // Build LoggerProvider with SimpleLogRecordProcessor (as shown in PDF)
        $this->loggerProvider = LoggerProvider::builder()
            ->addLogRecordProcessor(new SimpleLogRecordProcessor($exporter))
            ->setResource($resource)
            ->build();

        return $this->loggerProvider;
    }
}
