<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\StatementAttachment;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Consultation\ConsultationTokenService;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class StatementDeleter extends CoreService
{
    protected AssignService $assignService;
    protected PermissionsInterface $permissions;
    protected StatementFragmentService $statementFragmentService;
    protected ConsultationTokenService $consultationTokenService;
    protected StatementAttachmentService $statementAttachmentService;
    private StatementRepository $statementRepository;
    private StatementReportEntryFactory $statementReportEntryFactory;
    private ReportService $reportService;
    private MessageBagInterface $messageBag;
    private EntityContentChangeService $entityContentChangeService;
    private StatementService $statementService;

    public function __construct(
        AssignService $assignService,
        PermissionsInterface $permissions,
        StatementFragmentService $statementFragmentService,
        ConsultationTokenService $consultationTokenService,
        StatementAttachmentService $statementAttachmentService,
        StatementRepository $statementRepository,
        StatementReportEntryFactory $statementReportEntryFactory,
        ReportService $reportService,
        MessageBagInterface $messageBag,
        EntityContentChangeService $entityContentChangeService,
        StatementService $statementService
    ) {
        $this->assignService = $assignService;
        $this->permissions = $permissions;
        $this->statementFragmentService = $statementFragmentService;
        $this->consultationTokenService = $consultationTokenService;
        $this->statementAttachmentService = $statementAttachmentService;
        $this->statementRepository = $statementRepository;
        $this->statementReportEntryFactory = $statementReportEntryFactory;
        $this->reportService = $reportService;
        $this->messageBag = $messageBag;
        $this->entityContentChangeService = $entityContentChangeService;
        $this->statementService = $statementService;
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
        bool $ignoreOriginal = false
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

            $allowedToDelete =
                !$lockedByAssignmentOfRelatedFragments
                && !$lockedByAssignment
                && !$lockedByCluster
                && !$lockedBecauseOfOriginal;

            if ($allowedToDelete) {
                try {
                    // Prohibit deletion if a consultation token exists for this statement
                    if (null !== $this->consultationTokenService->getTokenForStatement($statement)) {
                        throw new DemosException('error.delete.statement.consultation.token', 'Statement '.DemosPlanTools::varExport($statementId, true).' has an associated consultation token.');
                    }

                    $doctrineConnection->beginTransaction();
                    $forReport = clone $statement;

                    $attachedFileIdents = \collect($statement->getAttachments())
                        ->map(static fn (StatementAttachment $attachment): string => $attachment->getFile()->getIdent());

                    $this->statementAttachmentService->deleteStatementAttachments($statement->getAttachments()->getValues());
                    $deleted = $this->statementRepository->delete($statementId);
                    // add report:
                    try {
                        if (true === $deleted) {
                            $this->emptyInternIdOfOriginalInCaseOfDeleteLastChild($statement);
                            $entry = $this->statementReportEntryFactory->createDeletionEntry($forReport);
                            $this->reportService->persistAndFlushReportEntries($entry);
                            $this->logger->info('generate report of deleteStatement(). ReportID: ', ['identifier' => $entry->getIdentifier()]);
                        }
                    } catch (Exception $e) {
                        $this->getLogger()->warning('Add Report in deleteStatement() failed Message: ', [$e]);
                    }
                    $doctrineConnection->commit();

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
            }

            return $success;
        } catch (Exception $e) {
            $this->getLogger()->warning('Fehler beim Löschen eines Statements: ', [$e]);
            $doctrineConnection->rollBack();

            return false;
        }
    }

    /**
     * @throws \Exception
     */
    private function emptyInternIdOfOriginalInCaseOfDeleteLastChild(Statement $statement): void
    {
        if ($this->permissions->hasPermission('feature_auto_delete_original_statement')) {
            $original = $statement->getOriginal();
            if (0 === $original->getChildren()->count()) {
                $original->setInternId(null);
                $this->statementService->updateStatementObject($original);
            }
        }
    }
}
