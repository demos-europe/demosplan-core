<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use Psr\Log\LoggerInterface;

/**
 * Trait to help capture logger calls in message handler tests.
 */
trait LoggerTestTrait
{
    private ?array $capturedLoggerCalls = [];

    /**
     * Creates a logger mock that captures all info() calls.
     *
     * @param int $expectedCallCount Number of expected info() calls
     *
     * @return LoggerInterface
     */
    private function createLoggerMockWithCapture(int $expectedCallCount): LoggerInterface
    {
        $this->capturedLoggerCalls = [];
        $logger = $this->createMock(LoggerInterface::class);

        $logger->expects($this->exactly($expectedCallCount))
            ->method('info')
            ->willReturnCallback(function ($message) {
                $this->capturedLoggerCalls[] = $message;
            });

        return $logger;
    }

    /**
     * Gets the captured logger calls.
     *
     * @return array<string>
     */
    private function getCapturedLoggerCalls(): array
    {
        return $this->capturedLoggerCalls ?? [];
    }

    /**
     * Creates a logger mock that expects a single info() call with a specific message.
     *
     * @param string $expectedMessage The expected message
     *
     * @return LoggerInterface
     */
    private function createLoggerMockWithSingleCall(string $expectedMessage): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with($expectedMessage);

        return $logger;
    }

    /**
     * Creates a logger mock that expects an error() call.
     *
     * @param string   $expectedMessage The expected error message
     * @param \Throwable|null $exception       The expected exception (or null if no exception expected)
     *
     * @return LoggerInterface
     */
    private function createLoggerMockForError(string $expectedMessage, ?\Throwable $exception = null): LoggerInterface
    {
        $logger = $this->createMock(LoggerInterface::class);

        if (null !== $exception) {
            $logger->expects($this->once())
                ->method('error')
                ->with(
                    $expectedMessage,
                    $this->callback(function ($context) use ($exception) {
                        return isset($context[0]) && $context[0] === $exception;
                    })
                );
        } else {
            $logger->expects($this->once())
                ->method('error')
                ->with($expectedMessage);
        }

        return $logger;
    }
}
