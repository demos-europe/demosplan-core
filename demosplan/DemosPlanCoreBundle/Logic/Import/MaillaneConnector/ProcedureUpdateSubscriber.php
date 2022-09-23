<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostNewProcedureCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostProcedureDeletedEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\ProcedureEditedEvent;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\Exception\MaillaneApiException;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Doctrine\ORM\EntityManagerInterface;
use EDT\JsonApi\Schema\ContentField;
use Throwable;

class ProcedureUpdateSubscriber extends BaseEventSubscriber
{
    /**
     * @var PermissionsInterface
     */
    private $permissions;

    /**
     * @var MaillaneSynchronizer
     */
    private $maillaneSynchronizer;

    /**
     * @var MessageBagInterface
     */
    private $messageBag;

    /**
     * @var ProcedureService
     */
    private $procedureService;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    public function __construct(
        EntityManagerInterface $entityManager,
        MaillaneSynchronizer $maillaneSynchronizer,
        MessageBagInterface $messageBag,
        PermissionsInterface $permissions,
        ProcedureService $procedureService
    ) {
        $this->maillaneSynchronizer = $maillaneSynchronizer;
        $this->messageBag = $messageBag;
        $this->permissions = $permissions;
        $this->procedureService = $procedureService;
        $this->entityManager = $entityManager;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostNewProcedureCreatedEvent::class => 'prepareAccountCreation',
            ProcedureEditedEvent::class         => 'updateAccount',
            PostProcedureDeletedEvent::class    => 'deleteAccount',
        ];
    }

    public function prepareAccountCreation(PostNewProcedureCreatedEvent $event): void
    {
        if ($this->permissions->hasPermission('feature_import_statement_via_email')) {
            try {
                $procedure = $event->getProcedure();
                if (!$procedure->getMaster()) {
                    $this->createAccount($procedure);
                }
            } catch (Throwable $e) {
                $this->logger->error('Could not create Maillane account', [
                    'exception' => $e->getMessage(),
                ]);

                $this->messageBag->add(
                    'error',
                    'error.statement.import.mail.maillane.account.creation.failed'
                );
            }
        }
    }

    public function updateAccount(ProcedureEditedEvent $event): void
    {
        if ($this->permissions->hasPermission('feature_import_statement_via_email')) {
            $beforeUpdateProcedureData = $event->getOriginalProcedureArray();
            $updateData = $event->getInData();

            $procedure = $this->procedureService->getProcedureWithCertainty(
                $beforeUpdateProcedureData[ContentField::ID]
            );

            // Get maillaneConnection or create it if necessary
            $maillaneConnection = $procedure->getMaillaneConnection();
            try {
                if (null === $maillaneConnection) {
                    // if an existing procedure gets edited and has no maillane connection, create one
                    $this->createAccount($procedure);
                    $maillaneConnection = $procedure->getMaillaneConnection();
                }
                $this->maillaneSynchronizer->editAccount($maillaneConnection, $updateData);
                $this->entityManager->flush();
            } catch (Throwable $e) {
                $this->logger->error('Could not edit or create Maillane account', [
                    'exception' => $e->getMessage(),
                ]);

                $this->messageBag->add(
                    'error',
                    'error.statement.import.mail.maillane.account.update.failed'
                );
            }
        }
    }

    public function deleteAccount(PostProcedureDeletedEvent $event): void
    {
        if ($this->permissions->hasPermission('feature_import_statement_via_email')) {
            $procedureData = $event->getProcedureData();
            if ('' !== $procedureData['maillaneAccountId']) {
                $this->maillaneSynchronizer->deleteAccount($procedureData['maillaneAccountId']);
            }
        }
    }

    /**
     * @throws MaillaneApiException
     * @throws ViolationsException
     */
    private function createAccount(Procedure $procedure): void
    {
        $accountEmail = $this->maillaneSynchronizer->generateRecipientMailAddress(
            $procedure->getName()
        );

        $maillaneConnection = $this->maillaneSynchronizer->createAccount($accountEmail);
        $procedure->setMaillaneConnection($maillaneConnection);

        $this->entityManager->flush();
    }
}
