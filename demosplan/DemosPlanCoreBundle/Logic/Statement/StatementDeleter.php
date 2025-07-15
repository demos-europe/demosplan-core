<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\Events\StatementPreDeleteEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementPreDeleteEvent;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\StatementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Repository\EntitySyncLinkRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class StatementDeleter extends CoreService
{
    public function __construct(
        protected AssignService $assignService,
        protected PermissionsInterface $permissions,
        protected StatementFragmentService $statementFragmentService,
        protected ConsultationTokenService $consultationTokenService,
        protected StatementAttachmentService $statementAttachmentService,
        private readonly StatementRepository $statementRepository,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        private readonly ReportService $reportService,
        private readonly MessageBagInterface $messageBag,
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly StatementService $statementService,
        private readonly EntitySyncLinkRepository $entitySyncLinkRepository,
        private readonly SqlQueriesService $queriesService,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws StatementNotFoundException
     */
    public function deleteOriginalStatementAttachmentByStatementId(string $statementId): Statement
    {
        $statement = $this->statementService->getStatement($statementId);
        if (!$statement instanceof Statement) {
            throw StatementNotFoundException::createFromId($statementId);
        }
        $statement = $this->statementAttachmentService->deleteOriginalAttachment($statement);

        return $this->statementService->updateStatementObject($statement);
    }

    /**
     * @throws OptimisticLockException
     * @throws UserNotFoundException
     * @throws ORMException
     * @throws Exception
     */
    private function deleteOriginalStatement(Statement $originalStatement): void
    {
        if (!$originalStatement->isOriginal()) {
            throw new InvalidArgumentException('Given original-Statement is actually not an original statement.');
        }

        if (!$originalStatement->getChildren()->isEmpty()) {
            throw new InvalidArgumentException('Original-Statement to delete still has children.');
        }
        $forReport = clone $originalStatement;

        $this->eventDispatcher->dispatch(new StatementPreDeleteEvent($originalStatement), StatementPreDeleteEventInterface::class);

        $deleteOriginal = $this->deleteStatementObject(
            $originalStatement,
            true,
            true,
            false
        );

        if ($deleteOriginal) {
            $entry = $this->statementReportEntryFactory->createDeletionEntry($forReport);
            $this->reportService->persistAndFlushWithoutTransaction($entry);
            $this->logger->info(
                'generate report of deleteStatement(). ReportID: ',
                ['identifier' => $entry->getIdentifier()]
            );
        }
    }

    /**
     * @throws UserNotFoundException
     * @throws ORMException
     * @throws OptimisticLockException|Exception
     * @throws \Exception
     */
    public function deleteStatementObject(
        Statement $statement,
        bool $ignoreAssignment = false,
        bool $ignoreOriginal = false,
        bool $canTransaction = true
    ): bool {
        /** @var Connection $doctrineConnection */
        $doctrineConnection = $this->getDoctrine()->getConnection();
        try {
            $success = false;
            $statementId = $statement->getId();

            // if the corresponding permission is disabled, the Statement can be deleted anyway
            $ignoreAssignment =
                $ignoreAssignment
                || false === $this->permissions->hasPermission('feature_statement_assignment');

            $noAssignee = null === $statement->getAssignee();
            $assignedToCurrentUser = $this->assignService->isStatementObjectAssignedToCurrentUser($statement);
            // T5136:
            $lockedByAssignment = !($ignoreAssignment || $noAssignee || $assignedToCurrentUser);

            $lockedByAssignmentOfRelatedFragments =
                !$this->statementFragmentService->areAllFragmentsClaimedByCurrentUser($statementId);

            $lockedByCluster = $statement->isInCluster();
            // placeholders (even originalSTN) are allowed to delete:
            $lockedBecauseOfOriginal = $statement->isOriginal() && !$ignoreOriginal;

            // T27971:
            $lockedBySync = null !== $this->entitySyncLinkRepository
                    ->findOneBy(['class' => Statement::class, 'sourceId' => $statement->getId()])
                || null !== $this->entitySyncLinkRepository
                    ->findOneBy(['class' => Statement::class, 'targetId' => $statement->getId()]);

            $allowedToDelete =
                !$lockedByAssignmentOfRelatedFragments
                && !$lockedByAssignment
                && !$lockedByCluster
                && !$lockedBecauseOfOriginal
                && !$lockedBySync;

            if ($allowedToDelete) {
                try {
                    // Prohibit deletion if a consultation token exists for this statement
                    if (null !== $this->consultationTokenService->getTokenForStatement($statement)) {
                        throw new DemosException('error.delete.statement.consultation.token', 'Statement '.DemosPlanTools::varExport($statementId, true).' has an associated consultation token.');
                    }
                    if ($canTransaction) {
                        $doctrineConnection->beginTransaction();
                    }
                    $attachedFileIdents = \collect($statement->getAttachments())
                        ->map(static fn (StatementAttachment $attachment): string => $attachment->getFile()->getIdent());

                    $this->statementAttachmentService->deleteStatementAttachments($statement->getAttachments()->getValues());
                    $deleted = $this->statementRepository->delete($statementId);
                    // add report:
                    try {
                        if (true === $deleted) {
                            $originalStatement = $statement->getOriginal();
                            if ($originalStatement instanceof Statement) {
                                if ($this->permissions->hasPermission('feature_auto_delete_original_statement')
                                    && $originalStatement->getChildren()->isEmpty()
                                ) {
                                    $this->deleteOriginalStatement($originalStatement);
                                }
                            }
                        }
                    } catch (Exception $e) {
                        $this->getLogger()->warning('Add Report in deleteStatement() failed Message: ', [$e]);
                    }

                    if ($canTransaction) {
                        $doctrineConnection->commit();
                    }

                    $this->entityContentChangeService->deleteByEntityIds([$statementId]);
                    $success = true;
                } catch (DemosException $demosException) {
                    $this->getLogger()->error('Fehler beim Löschen eines Statements: ', [$demosException]);
                    $this->messageBag->add(
                        'warning',
                        $demosException->getUserMsg()
                    );
                    $success = false;
                } catch (Exception $e) {
                    $this->getLogger()->error('Fehler beim Löschen eines Statements: ', [$e]);
                    $doctrineConnection->rollBack();
                    $success = false;
                } catch (\Doctrine\DBAL\Driver\Exception $e) {
                    $e->getMessage();
                }
            } else {
                if ($lockedByAssignmentOfRelatedFragments) {
                    $this->getLogger()->warning("Statement {$statementId} was not deleted, because of related fragments are locked by assignment");
                    $this->messageBag->add(
                        'warning',
                        'warning.delete.statement.because.of.fragments.not.claimed.by.current.user',
                        ['externId' => $statement->getExternId()]
                    );
                }

                if ($lockedByAssignment) {
                    $this->getLogger()
                        ->warning("Statement {$statementId} was not deleted, because of locked by assignment");
                    $this->messageBag->add(
                        'warning', 'warning.delete.statement.because.of.assignment',
                        ['externId' => $statement->getExternId()]
                    );
                }

                if ($lockedByCluster) {
                    $this->getLogger()
                        ->warning("Statement {$statementId} was not deleted, because of locked by cluster");
                    $this->messageBag->add(
                        'warning', 'error.statement.clustered.in',
                        ['headStatementId' => $statement->getExternId()]
                    );
                }

                if ($lockedBecauseOfOriginal) {
                    $this->getLogger()
                        ->warning("Statement {$statementId} was not deleted, because it is a undeletable original-Statement");
                    $this->messageBag->add(
                        'warning', 'warning.delete.statement.original',
                        ['externId' => $statement->getExternId()]
                    );
                }

                if ($lockedBySync) {
                    $this->getLogger()
                        ->warning("Statement {$statementId} was not deleted, because of locked by related synced statement.");
                    $this->messageBag->add(
                        'warning', 'warning.delete.statement.synced', // add transkey
                        ['externId' => $statement->getExternId()]
                    );
                }
            }

            return $success;
        } catch (Exception $e) {
            $this->getLogger()->warning('Fehler beim Löschen eines Statements: ', [$e]);
            $doctrineConnection->rollBack();

            return false;
        }
    }
}
