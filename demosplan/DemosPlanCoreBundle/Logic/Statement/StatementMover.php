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

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Exception\ClusterStatementCopyNotImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Doctrine\DBAL\ConnectionException;
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Psr\Log\LoggerInterface;

class StatementMover extends CoreService
{
    /** @var LoggerInterface */
    protected $logger;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ElementsService $elementsService,
        private readonly FileContainerRepository $fileContainerRepository,
        private readonly PermissionsInterface $permissions,
        private readonly MessageBagInterface $messageBag,
        private readonly StatementService $statementService,
        LoggerInterface $logger,
        private readonly StatementCopyAndMoveService $statementCopyAndMoveService,
        private readonly StatementHandler $statementHandler,
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        private readonly ReportService $reportService,
        private readonly StatementCopier $statementCopier,
        private readonly StatementRepository $statementRepository
    ) {
        $this->logger = $logger;
    }

    /**
     * refs: T8488:
     * Move a specific Statement to a specific procedure.
     * The given Statement to move will be copied.
     * The copy will stay as placeholder in the source procedure and will contains the externId of the Statement
     * to move. The Statement itself and its Fragments will attached to the target Procedure and gets a new
     * externId. ClusterStatement cant be moved. All Tags, attached to the statement will be detached and the
     * relation to the statement will be lost.
     *
     * @param bool $deleteVersionHistory Determines if related EntityContentChanges of the statement to move
     *                                   will be deleted or kept. In case of EntityContentChanges where not
     *                                   deleted, they can be seen by owner of target procedure.
     *
     * @return statement|false - Returns the moved Statement if successful, otherwise false
     *
     * @throws ConnectionException
     * @throws MessageBagException
     * @throws ORMException
     * @throws StatementElementNotFoundException
     * @throws NonUniqueResultException
     * @throws InvalidDataException
     * @throws \Exception
     */
    public function moveStatementToProcedure(
        Statement $statementToMove,
        Procedure $targetProcedure,
        bool $deleteVersionHistory = false
    ) {
        $doctrineConnection = $this->entityManager->getConnection();
        try {
            $placeholderStatement = $statementToMove->getPlaceholderStatement();
            if (false === $this->isMovingStatementAllowed($statementToMove, $targetProcedure)) {
                return false;
            }

            $statementElement = $this->elementsService->getStatementElement($targetProcedure->getId());
            $originalStatement = $statementToMove->getOriginal();

            $doctrineConnection->beginTransaction();
            /** @var StatementRepository $statementRepository */
            $statementRepository = $this->entityManager->getRepository(Statement::class);

            // only create placeholder if not already existing:
            if (null === $placeholderStatement) {
                // create placeholder to remain in sourceProcedure as reference:
                // todo: copy statement and use to move, instead to move incoming statement, because this way references can be stay
                $placeholderStatement = $this->createPlaceholderStatement($statementToMove);
                $placeholderStatement = $statementRepository->addObject(
                    $placeholderStatement
                );
            }

            $statementToMove->setProcedure($targetProcedure);
            // handle procedure-unique internID:
            $internIdIsUnique = $this->statementService->isInternIdUniqueForProcedure(
                $statementToMove->getInternId(),
                $targetProcedure->getId()
            );
            $internIdToSet = $statementToMove->getInternId();
            if (!$internIdIsUnique) {
                $this->messageBag->add('warning', 'internId.not.copied', ['internId' => $internIdToSet]);
                $internIdToSet = null;
            }

            // T13668: create new originalStatement to set on moveStatement
            $copyOfOriginalStatementToMove = $statementRepository->copyOriginalStatement(
                $originalStatement,
                $targetProcedure,
                null,
                $internIdToSet
            );
            $copyOfOriginalStatementToMove = $statementRepository->addObject($copyOfOriginalStatementToMove);

            $originalStatement->setChildren(null);
            $placeholderStatement->setParent($originalStatement);

            // Includes new ExternId:
            $newExternId = $copyOfOriginalStatementToMove->getExternId();
            $statementToMove->setOriginal($copyOfOriginalStatementToMove);
            // set parent to original because moving statement will lost his "copy status"
            // placeholder Statement will have the parent of the statement to move
            $statementToMove->setParent($copyOfOriginalStatementToMove);
            $statementToMove->setExternId($newExternId);

            // in every case set statementElement, because null is not a valid value and only statementElement is
            // safely available in each procedure
            $statementToMove->setElement($statementElement);
            $statementToMove->getOriginal()->setElement($statementElement);

            // T12744:
            $this->statementCopyAndMoveService->handlePublicationOfStatement($statementToMove, $targetProcedure, $statementToMove);

            $statementToMove->setDocument(null);
            $statementToMove->getOriginal()->setDocument(null);
            $statementToMove->setParagraph(null);
            $statementToMove->getOriginal()->setParagraph(null);

            // Remove related children.
            // Children of statement to move, are already attached to placeholder
            $statementToMove->setChildren(null);

            // remove all tags, because tags are bound by procedure
            // todo: maybe should attached to placeholder?
            // Attaching tags on placeholder would save them an enable restore after revert moving of statement.
            // (on move back to origin procedure).
            // This behavior is not demanded, not intended and would be increase the complexity slightly.
            // -> "nice to have"
            /** @var Tag $tag */
            foreach ($statementToMove->getTags() as $tag) {
                $statementToMove->removeTag($tag);
            }

            /** @var StatementFragment $fragment */
            foreach ($statementToMove->getFragments() as $fragment) {
                $this->statementHandler->deleteStatementFragment($fragment->getId(), true);
            }

            // set placeholder and persist&flush statement to move:
            if (null === $statementToMove->getPlaceholderStatement()) {
                $statementToMove->setPlaceholderStatement($placeholderStatement);
            }

            $updatedOriginalStatementToMove = null;
            // check assignment before set, to ensure BE-Check of assignment will not be bypassed on move statement
            $lockedByAssignment = $this->statementService->isStatementObjectLockedByAssignment($statementToMove);
            if (!$lockedByAssignment) {
                $statementToMove->setAssignee(null);
                foreach ($statementToMove->getAttachments() as $attachment) {
                    $file = $attachment->getFile();
                    $file->setProcedure($targetProcedure);
                    $attachment->setFile($file);
                }
                $statementFileContainers = $this->fileContainerRepository->getStatementFileContainers($statementToMove->getId());
                foreach ($statementFileContainers as $fileContainer) {
                    $file = $this->statementRepository->copyFile($fileContainer->getFile(), $statementToMove);
                    $fileContainer->setFile($file);
                    $this->fileContainerRepository->updateObject($fileContainer);
                }
                $updatedStatementToMove = $this->statementService->updateStatementFromObject(
                    $statementToMove,
                    true
                );

                $updatedOriginalStatementToMove = $this->statementService->updateStatementFromObject(
                    $copyOfOriginalStatementToMove,
                    true,
                    false,
                    true
                );
            } else {
                $updatedStatementToMove = null;
                $this->statementService->addMessageLockedByAssignment($statementToMove);
                $this->logger->warning('Trying to update a locked by assignment statement.');
            }

            // delete version-history in the end to avoid generating new versions on process of moving
            if ($deleteVersionHistory) {
                $this->entityContentChangeService->deleteByEntityIds([$statementToMove->getId()]);
                $this->entityContentChangeService->deleteByEntityIds([$copyOfOriginalStatementToMove->getId()]);
            }

            if (!$updatedStatementToMove instanceof Statement) {
                $this->messageBag->add('error', 'error.statement.move');
                $this->logger->error('Cant move Statement: '.$statementToMove->getId().'.');
                $doctrineConnection->rollBack();

                return false;
            }

            if (!$updatedOriginalStatementToMove instanceof Statement) {
                $this->messageBag->add('error', 'error.statement.move');
                $this->logger->error('Cant move Statement: '.$statementToMove->getId().'.');
                $doctrineConnection->rollBack();

                return false;
            }
        } catch (Exception) {
            $doctrineConnection->rollBack();

            return false;
        }

        $doctrineConnection->commit();
        $this->addReportOfMovingStatement($statementToMove);

        return $updatedStatementToMove;
    }

    /**
     * Check if the given Statement is allowed to move.
     * Not allowed to move are:
     *  - ClusterStatements
     *  - Statements there are not allowed to copy at all.
     *
     * @throws MessageBagException
     * @throws UserNotFoundException
     */
    protected function isMovingStatementAllowed(Statement $statement, Procedure $targetProcedure): bool
    {
        $sourceProcedure = $statement->getProcedure();

        if ($statement->isClusterStatement()) {
            $this->messageBag->add('warning', 'warning.deny.move.cluster.statement');
            $this->logger->warning('Moving ClusterStatement is not allowed.');

            return false;
        }

        if ($targetProcedure->getId() === $statement->getProcedureId()) {
            $this->messageBag->add('warning', 'warning.deny.move.statement.to.same.procedure');
            $this->logger->warning('Statement is already in Procedure '.$targetProcedure->getName().'.');

            return false;
        }

        // do not check if feature_notification_citizen_statement_submitted is enabled

        if (!$this->permissions->hasPermission('feature_statement_move_to_foreign_procedure')
            && $sourceProcedure->getOrgaId() !== $targetProcedure->getOrgaId()) {
            // error because should already be handled
            $this->messageBag->add('warning', 'warning.deny.move.statement.to.foreign.procedure');
            $this->logger->warning(
                'Cant move Statement: '.$statement->getExternId(
                ).' because target procedure is not owned by your organisation.'
            );

            return false;
        }

        try {
            $copyStatementAllowed = $this->statementCopier->isCopyStatementAllowed($statement);
        } catch (ClusterStatementCopyNotImplementedException) {
            $copyStatementAllowed = false;
        }
        if (false === $copyStatementAllowed) {
            $this->logger->warning(
                'Deny move Statement: '.$statement->getId(
                ).' because copy of Statement is not allowed. (isCopyStatementAllowed())'
            );

            return false;
        }

        // only if internId is set:
        if (null !== $statement->getInternId()) {
            $foundStatement = $this->statementHandler->getStatementByInternIdAndProcedureId(
                $statement->getInternId(),
                $targetProcedure->getId()
            );
            if (null !== $foundStatement) {
                $this->messageBag->add('warning', 'warning.deny.move.statement.taken.internId');
                $this->logger->warning(
                    'C\'ant move Statement: '.$statement->getExternId(
                    ).' because internId of Statement is already used in target procedure.'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Create placeholder statement to stay in sourceProcedure as reference.
     */
    protected function createPlaceholderStatement(Statement $statementToMove): Statement
    {
        $placeholderStatement = clone $statementToMove;

        // remove related Entitycollections
        $placeholderStatement->setFragments([]);
        $placeholderStatement->setVotes([]);
        $placeholderStatement->setTags([]);
        $placeholderStatement->setCounties([]);
        $placeholderStatement->setMunicipalities([]);
        $placeholderStatement->setPriorityAreas([]);
        $placeholderStatement->setFiles([]);
        $placeholderStatement->setInternId(null);
        $placeholderStatement->setMovedStatement($statementToMove);
        $placeholderStatement->setExternId($statementToMove->getExternId()); // maybe Moved %externId%
        $placeholderStatement->setText('');
        $placeholderStatement = $this->statementService->setPublicVerified(
            $placeholderStatement,
            Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED
        );

        // special case: statement to move is a parent-Statement
        /** @var Statement $child */
        foreach ($statementToMove->getChildren() as $child) {
            $child->setParent($placeholderStatement);
        }

        // special case: statement to move is a copy
        $placeholderStatement->setParent($statementToMove->getParent());

        return $placeholderStatement;
    }

    /**
     * Add two a report entries for moving a statement.
     * One in the "source"-Procedure and one in the "target"-procedure.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    protected function addReportOfMovingStatement(Statement $movedStatement): void
    {
        $entry = $this->statementReportEntryFactory->createMovedStatementEntry(
            $movedStatement,
            $movedStatement->getPlaceholderStatement()->getProcedureId()
        );
        $this->reportService->persistAndFlushReportEntries($entry);

        // same entry for targetProcedure, for case of deleting  source procedure:
        $entry = $this->statementReportEntryFactory->createMovedStatementEntry(
            $movedStatement,
            $movedStatement->getProcedureId()
        );
        $this->reportService->persistAndFlushReportEntries($entry);
    }
}
