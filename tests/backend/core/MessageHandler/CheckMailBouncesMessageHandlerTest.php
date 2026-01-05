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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\BounceChecker;
use demosplan\DemosPlanCoreBundle\Message\CheckMailBouncesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\CheckMailBouncesMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class CheckMailBouncesMessageHandlerTest extends UnitTestCase
{
    private ?BounceChecker $bounceChecker = null;
    private ?GlobalConfigInterface $globalConfig = null;
    private ?LoggerInterface $logger = null;
    private ?CheckMailBouncesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->bounceChecker = $this->createMock(BounceChecker::class);
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new CheckMailBouncesMessageHandler(
            $this->bounceChecker,
            $this->globalConfig,
            $this->logger
        );
    }

    public function testInvokeDoesNothingWhenBounceCheckDisabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('doEmailBounceCheck')
            ->willReturn(false);

        $this->bounceChecker->expects($this->never())
            ->method('checkEmailBounces');

        // Act
        ($this->sut)(new CheckMailBouncesMessage());
    }

    public function testInvokeChecksBouncesWhenEnabled(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('doEmailBounceCheck')
            ->willReturn(true);

        $this->bounceChecker->expects($this->once())
            ->method('checkEmailBounces')
            ->willReturn(3);

        $loggerCalls = [];
        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message) use (&$loggerCalls) {
                $loggerCalls[] = $message;
            });

        // Act
        ($this->sut)(new CheckMailBouncesMessage());

        // Assert
        $this->assertSame(['Emailbounces', 'Emailbounces processed: 3'], $loggerCalls);
    }

    public function testInvokeDoesNotLogWhenNoBouncesProcessed(): void
    {
        // Arrange
        $this->globalConfig->expects($this->once())
            ->method('doEmailBounceCheck')
            ->willReturn(true);

        $this->bounceChecker->expects($this->once())
            ->method('checkEmailBounces')
            ->willReturn(0);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Emailbounces');

        // Act
        ($this->sut)(new CheckMailBouncesMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Bounce check failed');

        $this->globalConfig->expects($this->once())
            ->method('doEmailBounceCheck')
            ->willReturn(true);

        $this->bounceChecker->expects($this->once())
            ->method('checkEmailBounces')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Emailbounces failed', [$exception]);

        // Act
        ($this->sut)(new CheckMailBouncesMessage());
    }
}
