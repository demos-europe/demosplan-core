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

use demosplan\DemosPlanCoreBundle\Logic\EmailAddressService;
use demosplan\DemosPlanCoreBundle\Message\DeleteOrphanEmailAddressesMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\DeleteOrphanEmailAddressesMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class DeleteOrphanEmailAddressesMessageHandlerTest extends UnitTestCase
{
    private ?EmailAddressService $emailAddressService = null;
    private ?LoggerInterface $logger = null;
    private ?DeleteOrphanEmailAddressesMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->emailAddressService = $this->createMock(EmailAddressService::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new DeleteOrphanEmailAddressesMessageHandler(
            $this->emailAddressService,
            $this->logger
        );
    }

    public function testInvokeDeletesOrphanEmailsAndLogsSuccess(): void
    {
        // Arrange
        $this->emailAddressService->expects($this->once())
            ->method('deleteOrphanEmailAddresses')
            ->willReturn(10);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Deleted 10 orphan email addresses');

        // Act
        ($this->sut)(new DeleteOrphanEmailAddressesMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Deleting orphan emails failed');

        $this->emailAddressService->expects($this->once())
            ->method('deleteOrphanEmailAddresses')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: removing orphan email addresses.', [$exception]);

        // Act
        ($this->sut)(new DeleteOrphanEmailAddressesMessage());
    }
}
