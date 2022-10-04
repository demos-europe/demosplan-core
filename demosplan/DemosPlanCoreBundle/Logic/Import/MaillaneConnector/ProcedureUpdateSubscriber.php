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

use demosplan\DemosPlanCoreBundle\Repository\StatementImportEmail\MaillaneConnectionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Throwable;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\EventSubscriber\BaseEventSubscriber;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostNewProcedureCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Procedure\PostProcedureDeletedEvent;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\ILogic\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\Exception\MaillaneApiException;
use demosplan\DemosPlanCoreBundle\Permissions\PermissionsInterface;

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
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MaillaneConnectionRepository
     */
    private $maillaneConnectionRepository;

    public function __construct(
        EntityManagerInterface $entityManager,
        MaillaneConnectionRepository $maillaneConnectionRepository,
        MaillaneSynchronizer $maillaneSynchronizer,
        MessageBagInterface $messageBag,
        PermissionsInterface $permissions,
    ) {
        $this->maillaneSynchronizer = $maillaneSynchronizer;
        $this->messageBag = $messageBag;
        $this->permissions = $permissions;
        $this->entityManager = $entityManager;
        $this->maillaneConnectionRepository = $maillaneConnectionRepository;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            PostNewProcedureCreatedEvent::class => 'prepareAccountCreation',
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

    public function deleteAccount(PostProcedureDeletedEvent $event): void
    {
        if ($this->permissions->hasPermission('feature_import_statement_via_email')) {
            $procedureData = $event->getProcedureData();
            $procedureId = $procedureData['id'];
            $maillaneConnection = $this->maillaneConnectionRepository->getMaillaneConnection($procedureId);
            if (null !== $maillaneConnection) {
                $this->maillaneSynchronizer->deleteAccount($maillaneConnection->getMaillaneAccountId());
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

        $this->maillaneSynchronizer->createAccount($accountEmail, $procedure);

        $this->entityManager->flush();
    }
}
