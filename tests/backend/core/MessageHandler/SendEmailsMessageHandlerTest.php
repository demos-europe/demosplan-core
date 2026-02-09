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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Message\SendEmailsMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\SendEmailsMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class SendEmailsMessageHandlerTest extends UnitTestCase
{
    private ?MailService $mailService = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?SendEmailsMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailService = $this->createMock(MailService::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new SendEmailsMessageHandler(
            $this->mailService,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeCallsMailServiceAndLogsSuccess(): void
    {
        // Arrange
        $this->mailService->expects($this->once())
            ->method('sendMailsFromQueue')
            ->willReturn(5);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Mails sent: 5');

        // Act
        ($this->sut)(new SendEmailsMessage());
    }

    public function testInvokeDoesNotLogWhenNoMailsSent(): void
    {
        // Arrange
        $this->mailService->expects($this->once())
            ->method('sendMailsFromQueue')
            ->willReturn(0);

        $this->logger->expects($this->never())
            ->method('info');

        // Act
        ($this->sut)(new SendEmailsMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Mail sending failed');

        $this->mailService->expects($this->once())
            ->method('sendMailsFromQueue')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Error sending mails', [$exception]);

        // Act
        ($this->sut)(new SendEmailsMessage());
    }
}
