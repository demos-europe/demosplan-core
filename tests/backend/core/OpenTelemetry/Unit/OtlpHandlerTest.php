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

use DateTimeImmutable;
use demosplan\DemosPlanCoreBundle\Monolog\Handler\OtlpHandler;
use Monolog\Level;
use Monolog\LogRecord;
use PHPUnit\Framework\TestCase;

class OtlpHandlerTest extends TestCase
{
    public function testHandlerSkipsWhenEndpointEmpty(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        // Should not throw when endpoint is empty
        $handler->handle($record);

        $this->assertTrue(true);
    }

    public function testHandlerAcceptsRecordAtConfiguredLevel(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: Level::Warning
        );

        $infoRecord = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Info message',
            context: [],
            extra: []
        );

        $warningRecord = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Warning,
            message: 'Warning message',
            context: [],
            extra: []
        );

        $this->assertFalse($handler->isHandling($infoRecord));
        $this->assertTrue($handler->isHandling($warningRecord));
    }

    public function testHandlerAcceptsStringLevel(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: 'warning'
        );

        $infoRecord = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Info message',
            context: [],
            extra: []
        );

        $errorRecord = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Error,
            message: 'Error message',
            context: [],
            extra: []
        );

        $this->assertFalse($handler->isHandling($infoRecord));
        $this->assertTrue($handler->isHandling($errorRecord));
    }

    public function testHandlerWithTenantId(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: 'tenant-123',
            level: Level::Info
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        // Should not throw
        $handler->handle($record);

        $this->assertTrue(true);
    }

    public function testHandlerWithContext(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message with context',
            context: ['user_id' => '123', 'action' => 'login'],
            extra: ['request_id' => 'req-456']
        );

        // Should not throw
        $handler->handle($record);

        $this->assertTrue(true);
    }

    public function testHandlerWithDifferentLogLevels(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: Level::Debug
        );

        $levels = [
            Level::Debug,
            Level::Info,
            Level::Notice,
            Level::Warning,
            Level::Error,
            Level::Critical,
            Level::Alert,
            Level::Emergency,
        ];

        foreach ($levels as $level) {
            $record = new LogRecord(
                datetime: new DateTimeImmutable(),
                channel: 'test',
                level: $level,
                message: "Test message at {$level->name} level",
                context: [],
                extra: []
            );

            // Should not throw for any level
            $handler->handle($record);
        }

        $this->assertTrue(true);
    }

    public function testHandlerBubblesBehavior(): void
    {
        $handlerWithBubble = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: Level::Info,
            bubble: true
        );

        $handlerWithoutBubble = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: '1.0.0',
            environment: 'test',
            tenantId: '',
            level: Level::Info,
            bubble: false
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message',
            context: [],
            extra: []
        );

        // With bubble=true, handle() returns false (continue to next handler)
        $this->assertFalse($handlerWithBubble->handle($record));

        // With bubble=false, handle() returns true (stop propagation)
        $this->assertTrue($handlerWithoutBubble->handle($record));
    }
}
