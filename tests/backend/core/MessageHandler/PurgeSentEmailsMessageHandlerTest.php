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

use demosplan\DemosPlanCoreBundle\Logic\MailService;
use demosplan\DemosPlanCoreBundle\Message\PurgeSentEmailsMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\PurgeSentEmailsMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\UnitTestCase;

class PurgeSentEmailsMessageHandlerTest extends UnitTestCase
{
    private ?MailService $mailService = null;
    private ?ParameterBagInterface $parameterBag = null;
    private ?LoggerInterface $logger = null;
    private ?PurgeSentEmailsMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mailService = $this->createMock(MailService::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new PurgeSentEmailsMessageHandler(
            $this->mailService,
            $this->parameterBag,
            $this->logger
        );
    }

    public function testInvokePurgesEmailsAndLogsSuccess(): void
    {
        // Arrange
        $this->parameterBag->expects($this->once())
            ->method('get')
            ->with('email_delete_after_days')
            ->willReturn('30');

        $this->mailService->expects($this->once())
            ->method('deleteAfterDays')
            ->with(30)
            ->willReturn(15);

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context = []) {
                static $callCount = 0;
                $callCount++;

                if (1 === $callCount) {
                    $this->assertSame('Maintenance: deleteAfterDays()', $message);
                    $this->assertNotEmpty($context); // Expects [spl_object_id($message)]
                } elseif (2 === $callCount) {
                    $this->assertSame('Deleted 15 old emails', $message);
                }
            });

        // Act
        ($this->sut)(new PurgeSentEmailsMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Purging emails failed');

        $this->parameterBag->expects($this->once())
            ->method('get')
            ->with('email_delete_after_days')
            ->willReturn('30');

        $this->mailService->expects($this->once())
            ->method('deleteAfterDays')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: Delete old emails', [$exception]);

        // Act
        ($this->sut)(new PurgeSentEmailsMessage());
    }
}
