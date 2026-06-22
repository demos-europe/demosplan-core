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
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementHandler;
use demosplan\DemosPlanCoreBundle\Message\CreateUnsubmittedDraftEmailsMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\CreateUnsubmittedDraftEmailsMessageHandler;
use Exception;
use Psr\Log\LoggerInterface;
use Tests\Base\UnitTestCase;

class CreateUnsubmittedDraftEmailsMessageHandlerTest extends UnitTestCase
{
    private ?DraftStatementHandler $draftStatementHandler = null;
    private ?PermissionsInterface $permissions = null;
    private ?LoggerInterface $logger = null;
    private ?CreateUnsubmittedDraftEmailsMessageHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->draftStatementHandler = $this->createMock(DraftStatementHandler::class);
        $this->permissions = $this->createMock(PermissionsInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->sut = new CreateUnsubmittedDraftEmailsMessageHandler(
            $this->draftStatementHandler,
            $this->permissions,
            $this->logger
        );
    }

    public function testInvokeSkipsWhenPermissionNotGranted(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_send_email_on_procedure_ending_phase_send_mails')
            ->willReturn(false);

        $this->logger->expects($this->once())
            ->method('info')
            ->with('Skipping unsubmitted draft emails: permission not granted');

        $this->draftStatementHandler->expects($this->never())
            ->method('createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser');

        // Act
        ($this->sut)(new CreateUnsubmittedDraftEmailsMessage());
    }

    public function testInvokeCreatesEmailsAndLogsSuccess(): void
    {
        // Arrange
        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_send_email_on_procedure_ending_phase_send_mails')
            ->willReturn(true);

        $this->draftStatementHandler->expects($this->exactly(2))
            ->method('createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser')
            ->willReturnOnConsecutiveCalls(3, 2);

        $this->logger->expects($this->exactly(2))
            ->method('info')
            ->willReturnCallback(function ($message, $context = []) {
                if ('Maintenance: createMailsForUnsubmittedDraftsInSoonEndingProcedures()' === $message) {
                    $this->assertNotEmpty($context); // Expects [spl_object_id($message)]
                } elseif ('Maintenance: createMailsForUnsubmittedDraftsInSoonEndingProcedures(). Number of created mail_send entries:' === $message) {
                    $this->assertSame([5], $context);
                }
            });

        // Act
        ($this->sut)(new CreateUnsubmittedDraftEmailsMessage());
    }

    public function testInvokeLogsErrorOnException(): void
    {
        // Arrange
        $exception = new Exception('Creating emails failed');

        $this->permissions->expects($this->once())
            ->method('hasPermission')
            ->with('feature_send_email_on_procedure_ending_phase_send_mails')
            ->willReturn(true);

        $this->draftStatementHandler->expects($this->once())
            ->method('createEMailsForUnsubmittedDraftStatementsOfProcedureOfUser')
            ->willThrowException($exception);

        $this->logger->expects($this->once())
            ->method('error')
            ->with('Daily maintenance task failed for: createMailsForUnsubmittedDraftsInSoonEndingProcedures.', [$exception]);

        // Act
        ($this->sut)(new CreateUnsubmittedDraftEmailsMessage());
    }
}
