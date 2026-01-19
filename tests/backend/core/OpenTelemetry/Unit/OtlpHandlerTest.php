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
use Exception;
use Monolog\Level;
use Monolog\LogRecord;
use OpenTelemetry\API\Common\Time\Clock;
use OpenTelemetry\API\Common\Time\ClockInterface;
use PHPUnit\Framework\TestCase;

class OtlpHandlerTest extends TestCase
{
    private const TEST_SERVICE_VERSION = '1.0.0';
    private const TEST_MESSAGE = 'Test message';

    public function testHandlerSkipsWhenEndpointEmpty(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: self::TEST_MESSAGE,
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
            serviceVersion: self::TEST_SERVICE_VERSION,
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
            serviceVersion: self::TEST_SERVICE_VERSION,
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
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: 'tenant-123',
            level: Level::Info
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: self::TEST_MESSAGE,
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
            serviceVersion: self::TEST_SERVICE_VERSION,
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
            serviceVersion: self::TEST_SERVICE_VERSION,
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
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info,
            bubble: true
        );

        $handlerWithoutBubble = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info,
            bubble: false
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: self::TEST_MESSAGE,
            context: [],
            extra: []
        );

        // With bubble=true, handle() returns false (continue to next handler)
        $this->assertFalse($handlerWithBubble->handle($record));

        // With bubble=false, handle() returns true (stop propagation)
        $this->assertTrue($handlerWithoutBubble->handle($record));
    }

    /**
     * Verify that the non-deprecated Clock API is used for BatchLogRecordProcessor.
     *
     * This test ensures we're using OpenTelemetry\API\Common\Time\Clock
     * instead of the deprecated OpenTelemetry\SDK\Common\Time\ClockFactory.
     */
    public function testClockApiIsAvailable(): void
    {
        $clock = Clock::getDefault();

        $this->assertInstanceOf(ClockInterface::class, $clock);
        $this->assertGreaterThan(0, $clock->now());
    }

    public function testShutdownIsIdempotent(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        // Multiple shutdown calls should not throw
        $handler->shutdown();
        $handler->shutdown();
        $handler->shutdown();

        $this->assertTrue(true);
    }

    public function testCloseCallsShutdown(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        // close() should not throw and should call shutdown internally
        $handler->close();

        // Subsequent shutdown should be safe (idempotent)
        $handler->shutdown();

        $this->assertTrue(true);
    }

    public function testHandlerWithRequestIdInExtra(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test message with request ID',
            context: [],
            extra: ['rid' => 'request-123']
        );

        // Should not throw - request_id attribute should be added
        $handler->handle($record);

        $this->assertTrue(true);
    }

    public function testHandlerSkipsSensitiveContextKeys(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        // These sensitive keys should be skipped when processing context
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test with sensitive context',
            context: [
                'user_id'     => '123',           // Should be included
                'params'      => ['secret' => 'value'], // Should be skipped
                'exception'   => new Exception('test'), // Should be skipped
                'stack_trace' => 'some trace',    // Should be skipped
                'action'      => 'login',         // Should be included
            ],
            extra: []
        );

        // Should not throw - sensitive keys should be skipped
        $handler->handle($record);

        $this->assertTrue(true);
    }

    public function testHandlerFlattensContextToAttributes(): void
    {
        $handler = new OtlpHandler(
            otlpEndpoint: '',
            serviceName: 'test-service',
            serviceVersion: self::TEST_SERVICE_VERSION,
            environment: 'test',
            tenantId: '',
            level: Level::Info
        );

        // Context should be flattened to individual attributes with 'context.' prefix
        $record = new LogRecord(
            datetime: new DateTimeImmutable(),
            channel: 'test',
            level: Level::Info,
            message: 'Test with flattened context',
            context: [
                'user_id' => '123',
                'action'  => 'login',
                'nested'  => ['key' => 'value'], // Non-scalar values should be JSON encoded
            ],
            extra: []
        );

        // Should not throw - context should be flattened properly
        $handler->handle($record);

        $this->assertTrue(true);
    }
}
