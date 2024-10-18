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

use DateInterval;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Doctrine\Generator\NCNameGenerator;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Exception\ClusterStatementCopyNotImplementedException;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\StatementElementNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\StatementAttachmentService;
use demosplan\DemosPlanCoreBundle\Repository\FileContainerRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use FOS\ElasticaBundle\Index\IndexManager;
use Symfony\Component\Filesystem\Exception\FileNotFoundException;

class StatementCopier extends CoreService
{
    use RefreshElasticsearchIndexTrait;

    /** @var ReportService */
    protected $reportService;

    public function __construct(
        private readonly AssignService $assignService,
        private readonly CurrentUserInterface $currentUser,
        private readonly ElementsService $elementService,
        private readonly FileService $fileService,
        private readonly FileContainerRepository $fileContainerRepository,
        IndexManager $elasticsearchIndexManager,
        private readonly MessageBagInterface $messageBag,
        private readonly PermissionsInterface $permissions,
        ReportService $reportService,
        private readonly StatementAttachmentService $statementAttachmentService,
        private readonly StatementCopyAndMoveService $statementCopyAndMoveService,
        private readonly StatementFragmentService $statementFragmentService,
        private readonly StatementHandler $statementHandler,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        private readonly StatementRepository $statementRepository,
        private readonly StatementService $statementService,
        private readonly NCNameGenerator $nameGenerator,
    ) {
        $this->elasticsearchIndexManager = $elasticsearchIndexManager;
        $this->reportService = $reportService;
    }

    /**
     * Copy Tags of Statement which is an n:m association.
     * Therefore the Tags have not to be copied and can simple attach to the copied statement.
     * Using the add() method of Statement ensures, that both sites (Statement and Tag) will be handled.
     */
    public function copyTags(Statement $sourceStatement, Statement $copiedStatement): Statement
    {
        $copiedStatement->setTags([]);
        /** @var Tag $tag */
        foreach ($sourceStatement->getTags() as $tag) {
            $copiedStatement->addTag($tag);
        }

        return $copiedStatement;
    }

    /**
     * @return Statement|false
     *
     * @throws EntityNotFoundException
     * @throws InvalidDataException
     * @throws MessageBagException
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws StatementElementNotFoundException
     * @throws Exception
     */
    public function copyStatementToProcedure(Statement $sourceStatement, Procedure $targetProcedure)
    {
        $doctrineConnection = $this->getDoctrine()->getConnection();
        $doctrineConnection->beginTransaction();

        // 1. Copy related Original Statement
        // handle procedure-unique internID:
        $internIdIsUnique = $this->statementService->isInternIdUniqueForProcedure(
            $sourceStatement->getInternId(),
            $targetProcedure->getId()
        );
        $internIdToSet = $sourceStatement->getInternId();
        if (!$internIdIsUnique) {
            $this->messageBag->add('warning', 'internId.not.copied', ['internId' => $internIdToSet]);
            $internIdToSet = null;
        }

        // Includes new ExternId:
        $copiedOriginalStatement = $this->statementRepository->copyOriginalStatement(
            $sourceStatement->getOriginal(),
            $targetProcedure,
            null,
            $internIdToSet
        );
        $newExternId = $copiedOriginalStatement->getExternId();
        $statementElementOfTargetProcedure = $this->elementService->getStatementElement($targetProcedure->getId());

        $copiedStatement = new Statement();

        $copiedStatement->setPriority($sourceStatement->getPriority());
        $copiedStatement->setOriginal($copiedOriginalStatement);
        $copiedStatement->setProcedure($copiedOriginalStatement->getProcedure());
        $copiedStatement->setParent($copiedOriginalStatement);
        $copiedStatement->setId(null);
        $copiedStatement->setExternId($newExternId);
        $copiedStatement->setCreated(new DateTime());
        $copiedStatement->setDeletedDate(new DateTime());
        $copiedStatement->setModified(new DateTime());
        $copiedStatement->setSubmit($sourceStatement->getSubmitObject()->add(new DateInterval('PT1S')));
        $newStatementMeta = clone $sourceStatement->getMeta();
        $copiedStatement->setMeta($newStatementMeta);
        // detach from Placeholder
        $copiedStatement->setPlaceholderStatement(null);
        // statement to copy should never have a related moved statement!
        $copiedStatement->setMovedStatement(null);

        $copiedStatement->setText($sourceStatement->getText());
        $copiedStatement->setDraftStatement(null);
        $copiedStatement->setDeleted($sourceStatement->getDeleted());
        $copiedStatement->setHeadStatement(null);
        $copiedStatement->setClusterStatement($sourceStatement->isClusterStatement());
        $copiedStatement->setCountyNotified(false);
        $copiedStatement->setFeedback($sourceStatement->getFeedback());
        $copiedStatement->setFragmentsFilteredCount($sourceStatement->getFragmentsFilteredCount());
        $copiedStatement->setManual($sourceStatement->isManual());
        $copiedStatement->setMemo($sourceStatement->getMemo());
        $copiedStatement->setName($sourceStatement->getName());
        $copiedStatement->setNegativeStatement($sourceStatement->getNegativeStatement());
        $copiedStatement->setOrganisation($sourceStatement->getOrganisation());
        $copiedStatement->setPhase($sourceStatement->getPhase());

        $copiedStatement->setPolygon($sourceStatement->getPolygon());
        $copiedStatement->setPublicUseName($sourceStatement->getPublicUseName());

        $copiedStatement->setRecommendation('');
        $copiedStatement->setRepresentationCheck($sourceStatement->getRepresentationCheck());
        $copiedStatement->setRepresents($sourceStatement->getRepresents());
        $copiedStatement->setSend($sourceStatement->getSend());
        $copiedStatement->setSentAssessment($sourceStatement->getSentAssessment());
        $copiedStatement->setSentAssessmentDate($sourceStatement->getSentAssessmentDate());
        $copiedStatement->setStatus($sourceStatement->getStatus());
        $copiedStatement->setSubmit($sourceStatement->getSubmitObject());
        $copiedStatement->setSubmitType($sourceStatement->getSubmitType());
        $copiedStatement->setPublicStatement($sourceStatement->getPublicStatement());
        $copiedStatement->setTitle($sourceStatement->getTitle());
        $copiedStatement->setToSendPerMail($sourceStatement->getToSendPerMail());
        $copiedStatement->setUser($sourceStatement->getUser());
        $copiedStatement->setVersion(null);

        // T14367
        $copiedStatement->setPublicVerified($sourceStatement->getPublicVerified());
        $copiedStatement->setNumberOfAnonymVotes($sourceStatement->getNumberOfAnonymVotes());

        $copiedStatement->setVotePla(null);
        $copiedStatement->setVoteStk(null);

        $copiedStatement = $this->copyVotes($sourceStatement, $copiedStatement);
        $copiedStatement = $this->copyCounties($sourceStatement, $copiedStatement);
        $copiedStatement = $this->copyMunicipalities($sourceStatement, $copiedStatement);
        $copiedStatement = $this->copyPriorityAreas($sourceStatement, $copiedStatement);
        $copiedStatement = $this->statementCopyAndMoveService->handlePublicationOfStatement(
            $copiedStatement,
            $targetProcedure,
            $sourceStatement
        );

        $copiedStatement->setElement($statementElementOfTargetProcedure);
        $copiedStatement->getOriginal()->setElement($statementElementOfTargetProcedure);
        $copiedStatement->setDocument(null);
        $copiedStatement->setParagraph(null);

        // is it reasonable to copy children too?
        $copiedStatement->setChildren(null);

        // remove all tags, because procedure specific -> impossible to keep:
        /** @var Tag $tag */
        foreach ($copiedStatement->getTags() as $tag) {
            $copiedStatement->removeTag($tag);
        }

        $addedStatement = $this->statementRepository->addObject($copiedStatement);

        // file copy needs existing $addedStatement Id
        $this->copyStatementFiles($addedStatement, $sourceStatement, $targetProcedure->getId());
        $originalAttachments = $sourceStatement->getAttachments();
        $copiedAttachments = $this->statementAttachmentService->copyAttachmentEntries(
            $originalAttachments,
            $copiedStatement
        );
        $copiedStatement->setAttachments($copiedAttachments);

        // explicitly call getStatement to get updated files
        $addedStatement = $this->statementService->getStatement($copiedStatement->getId());

        // copy in the end to avoid doctrine error of not existing related statement
        $this->statementFragmentService->copyStatementFragments(
            $sourceStatement->getFragments(),
            $addedStatement,
            true
        );

        if (!$addedStatement instanceof Statement) {
            $doctrineConnection->rollback();
            $this->messageBag->add('error', 'error.statement.copy.to.procedure');
            $this->getLogger()->error('Cant copy Statement to another procedure: '.$copiedStatement->getId().'.');

            return false;
        }

        $doctrineConnection->commit();

        $this->addReportOfCopingStatement($sourceStatement, $copiedStatement);

        return $addedStatement;
    }

    public function copyVotes(Statement $sourceStatement, Statement $copiedStatement): Statement
    {
        $copiedVotes = [];
        foreach ($sourceStatement->getVotes() as $vote) {
            $newVote = new StatementVote();
            $newVote->setStatement($copiedStatement);
            $newVote->setManual($vote->isManual());
            $newVote->setUserPostcode($vote->getUserPostcode());
            $newVote->setUser($vote->getUser());
            $newVote->setUserName($vote->getUserName());
            $newVote->setUserCity($vote->getUserCity());
            $newVote->setUserMail($vote->getUserMail());
            $newVote->setOrganisationName($vote->getOrganisationName());
            $newVote->setLastName($vote->getLastName());
            $newVote->setFirstName($vote->getFirstName());
            $newVote->setDepartmentName($vote->getDepartmentName());
            $newVote->setCreatedByCitizen($vote->isCreatedByCitizen());
            $newVote->setDeleted($vote->getDeleted());
            $newVote->setActive($vote->getActive());
            $newVote->setCreatedDate($vote->getCreatedDate());
            $newVote->setDeletedDate($vote->getDeletedDate());
            $newVote->setModifiedDate($vote->getModifiedDate());

            $copiedVotes[] = $newVote;
        }

        $copiedStatement->setVotes($copiedVotes);

        return $copiedStatement;
    }

    /**
     * Copy Counties of Statement which is an n:m association.
     * Therefore the Tags have not to be copied and can simple attach to the copied statement.
     * Using the add() method of Statement ensures, that both sites (Statement and County) will be handled.
     */
    public function copyCounties(Statement $sourceStatement, Statement $copiedStatement): Statement
    {
        $copiedStatement->setCounties([]);
        /** @var County $county */
        foreach ($sourceStatement->getCounties() as $county) {
            $copiedStatement->addCounty($county);
        }

        return $copiedStatement;
    }

    /**
     * Copy Municipalities of Statement which is an n:m association.
     * Therefore the Tags have not to be copied and can simple attach to the copied statement.
     * Using the add() method of Statement ensures, that both sites (Statement and Municipality) will be handled.
     */
    public function copyMunicipalities(Statement $sourceStatement, Statement $copiedStatement): Statement
    {
        $copiedStatement->setMunicipalities([]);
        /** @var Municipality $municipality */
        foreach ($sourceStatement->getMunicipalities() as $municipality) {
            $copiedStatement->addMunicipality($municipality);
        }

        return $copiedStatement;
    }

    /**
     * Copy PriorityAreas of Statement which is an n:m association.
     * Therefore the Tags have not to be copied and can simple attach to the copied statement.
     * Using the add() method of Statement ensures, that both sites (Statement and PriorityArea) will be handled.
     */
    public function copyPriorityAreas(Statement $sourceStatement, Statement $copiedStatement): Statement
    {
        $copiedStatement->setPriorityAreas([]);
        /** @var PriorityArea $priorityArea */
        foreach ($sourceStatement->getPriorityAreas() as $priorityArea) {
            $copiedStatement->addPriorityArea($priorityArea);
        }

        return $copiedStatement;
    }

    /**
     * @throws InvalidDataException
     * @throws MessageBagException
     */
    private function copyStatementFiles(Statement $copiedStatement, Statement $statementToCopy, string $targetProcedureId): void
    {
        // ensure loading files of statement by getting statement via getStatement(), because this way the realted Files will be loaded.
        $relatedFiles = $this->fileService->getEntityFileString(
            Statement::class,
            $statementToCopy->getId(),
            'file'
        );
        $statementToCopy->setFiles($relatedFiles);

        $hasFiles = is_array($statementToCopy->getFiles()) && 0 < (is_countable($statementToCopy->getFiles()) ? count($statementToCopy->getFiles()) : 0);
        $hasMapFile = '' !== $statementToCopy->getMapFile() && null !== $statementToCopy->getMapFile();

        if (!$hasFiles && !$hasMapFile) {
            return;
        }

        // create a physical copy of files as the increased complexity of having
        // multiple references to one file does count more than hdd space
        foreach ($statementToCopy->getFiles() as $fileString) {
            // filestring is written into $fileService on copy
            try {
                $this->fileService->copyByFileString($fileString, $targetProcedureId);
            } catch (FileNotFoundException) {
                $this->messageBag->add(
                    'error',
                    'error.copy.files',
                    ['externId' => $statementToCopy->getExternId()]
                );
                $this->getLogger()->error('Fail to copy Files of Statement', [$statementToCopy->getId()]);
            }
            $this->fileService->addStatementFileContainer(
                $copiedStatement->getId(),
                $this->fileService->getInfoFromFileString($this->fileService->getFileString(), 'hash'),
                $this->fileService->getFileString()
            );
        }

        // copy
        if ($hasMapFile) {
            try {
                // filestring is written into $fileService on copy
                $this->fileService->copyByFileString($statementToCopy->getMapFile(), $targetProcedureId);
            } catch (FileNotFoundException) {
                $this->messageBag->add(
                    'error',
                    'error.copy.mapfile',
                    ['externId' => $statementToCopy->getExternId()]
                );
                $this->getLogger()->error('Fail to copy Mapfile of Statement', [$statementToCopy->getId()]);
            }
            $copiedStatement->setMapFile($this->fileService->getFileString());
            $this->statementService->updateStatementFromObject($copiedStatement, true);
        }
    }

    /**
     * Add two a report entries for coping a statement.
     * One in the "source"-Procedure and one in the "target"-procedure.
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    private function addReportOfCopingStatement(Statement $sourceStatement, Statement $copiedStatement): void
    {
        $sourceReport = $this->statementReportEntryFactory->createStatementCopiedEssentialsEntry(
            $sourceStatement,
            $copiedStatement,
            $sourceStatement->getProcedure()->getId()
        );
        $this->reportService->persistAndFlushReportEntries($sourceReport);

        // same entry for targetProcedure, for case of deleting  source procedure:
        $targetReport = $this->statementReportEntryFactory->createStatementCopiedEssentialsEntry(
            $sourceStatement,
            $copiedStatement,
            $copiedStatement->getProcedureId()
        );
        $this->reportService->persistAndFlushReportEntries($targetReport);
    }

    /**
     * Check if the given Statement is allowed to copy into another procedure.
     * Not allowed to move are:
     *  - ClusterStatements
     *  - Statements there are not allowed to copy at all.
     *
     * @throws MessageBagException
     * @throws UserNotFoundException
     */
    public function isCopyStatementToProcedureAllowed(
        Statement $statement,
        Procedure $targetProcedure,
        bool $ignoreReviewer = false,
        bool $ignoreInternId = false,
    ): bool {
        $sourceProcedure = $statement->getProcedure();

        if ($targetProcedure->getId() === $statement->getProcedureId()) {
            $this->messageBag->add('warning', 'warning.deny.copy.statement.to.same.procedure');
            $this->getLogger()->warning('Statement is already in Procedure '.$targetProcedure->getName().'.');

            return false;
        }

        // do not check if feature_notification_citizen_statement_submitted is enabled
        if (!$this->permissions->hasPermission('feature_statement_copy_to_foreign_procedure')) {
            $currentUser = $this->currentUser->getUser();
            $hasPlannerRole = $currentUser->hasRole(Role::PRIVATE_PLANNING_AGENCY);
            $authorizedPlanningOffices = collect($targetProcedure->getPlanningOfficesIds());

            // neither authorized by ownership nor by planningagency?
            if ($sourceProcedure->getOrgaId() !== $targetProcedure->getOrgaId()
                && ($hasPlannerRole && !$authorizedPlanningOffices->containsStrict(
                    $currentUser->getOrganisationId()
                ))) {
                // error because should already be handled
                $this->messageBag->add('warning', 'warning.deny.copy.statement.to.foreign.procedure');
                $this->getLogger()->warning(
                    'Cant copy Statement: '.$statement->getExternId(
                    ).' because target procedure is not owned by your organisation.'
                );

                return false;
            }
        }

        $copyStatementAllowed = $this->isCopyStatementAllowed($statement, true, $ignoreReviewer);
        if (false === $copyStatementAllowed) {
            $this->getLogger()->warning(
                'Deny copy Statement: '.$statement->getId(
                ).' because copy of Statement is not allowed. (isCopyStatementAllowed())'
            );

            return false;
        }

        // only if internId is set:
        if (!$ignoreInternId && null !== $statement->getInternId()) {
            $foundStatement = $this->statementHandler->getStatementByInternIdAndProcedureId(
                $statement->getInternId(),
                $targetProcedure->getId()
            );
            if (null !== $foundStatement) {
                $this->messageBag->add('warning', 'warning.deny.copy.statement.taken.internId');
                $this->getLogger()->warning(
                    'Cant copy Statement: '.$statement->getExternId(
                    ).' because internId of Statement is already used in target procedure.'
                );

                return false;
            }
        }

        return true;
    }

    /**
     * Copy a specific statement including related files.
     *
     * @param bool $createReport          if this parameter is true, a copy-reportEntry will be generated
     * @param bool $copyOnCreateStatement Determines if this method is called on create a Statement.
     *                                    In case of creating new clusterStatement, coping new created
     *                                    clusterStatement, has to be allowed.
     *
     * @throws CopyException
     */
    public function copyStatementObjectWithinProcedureWithRelatedFiles(
        Statement $statement,
        bool $createReport = true,
        bool $copyOnCreateStatement = false,
    ): Statement {
        $newStatement = $this->copyStatementObjectWithinProcedure(
            $statement,
            $createReport,
            $copyOnCreateStatement,
            false // do not flush to avoid constraints at this point
        );
        if (!$statement instanceof Statement) {
            throw new CopyException('error on copying original statement');
        }
        // persist to get an ID for the FileContainer copying below
        $this->getDoctrine()->getManager()->persist($newStatement);
        if ([] !== $statement->getFiles()) {
            $this->statementService->addFilesToCopiedStatement($newStatement, $statement->getId());

            return $newStatement;
        }

        // We do have to flush the new copied statement here if the original statement has no FileContainers otherwise
        // the new copied statement is already flushed while copying FileContainers in the previous method 'addFilesToCopiedStatement'.
        $this->getDoctrine()->getManager()->flush();

        return $newStatement;
    }

    /**
     * Copy a specific statement without the file-references!
     *
     * @param bool $createReport          if this parameter is true, a copy-reportEntry will be generated
     * @param bool $copyOnCreateStatement Determines if this method is called on create a Statement.
     *                                    In case of creating new clusterStatement, coping new created
     *                                    clusterStatement, has to be allowed.
     * @param bool $persistAndFlush       determines if copied Statement will be persisted
     *
     * @throws CopyException
     * @throws ClusterStatementCopyNotImplementedException
     */
    public function copyStatementObjectWithinProcedure(
        Statement $statement,
        bool $createReport = true,
        bool $copyOnCreateStatement = false,
        bool $persistAndFlush = true,
    ): Statement|false {
        try {
            $em = $this->getDoctrine()->getManager();

            // ClusterStatementCopyNotImplementedException is thrown here too
            if (false === $this->isCopyStatementAllowed($statement, $copyOnCreateStatement)) {
                throw new CopyException('Copying of statement not allowed.');
            }

            $newStatement = clone $statement;
            $newStatement->setParent($statement);
            $newStatement->setId(null);
            $newStatement->setInternId(null);
            $newStatement->setCreated(new DateTime());
            $newStatement->setDeletedDate(new DateTime());
            $newStatement->setModified(new DateTime());
            $newStatement->setSubmit($statement->getSubmitObject()->add(new DateInterval('PT1S')));
            $newStatementMeta = clone $statement->getMeta();
            $newStatement->setMeta($newStatementMeta);
            // remove direct reference to placeholderStatement:
            $newStatement->setPlaceholderStatement(null);
            // copy attachments
            $originalAttachments = $statement->getAttachments();
            $copiedAttachments = $this->statementAttachmentService->copyAttachmentEntries(
                $originalAttachments,
                $newStatement
            );
            $newStatement->setAttachments($copiedAttachments);
            // Wenn er aus den Originalen kopiert wird, muss die Id auch als originalId übernommen werden
            // ansonsten bleibt die originalId identisch bei allen Kindskopien
            if (null === $statement->getOriginalId()) {
                $newStatement->setOriginal($statement);
            }

            // T15852 + T14880:
            // in each case reset public verified to ensure FP has to check each new statement
            if (!$newStatement->isManual() && in_array($newStatement->getPublicVerified(), [
                Statement::PUBLICATION_APPROVED,
                Statement::PUBLICATION_REJECTED,
            ], true)
            ) {
                $newStatement = $this->statementService->setPublicVerified(
                    $newStatement,
                    Statement::PUBLICATION_PENDING
                );
            }

            if ($persistAndFlush) {
                $em->persist($newStatement);
            }

            // copy fragments if set
            if (0 < $statement->getFragments()->count()) {
                // automatically flushes index tasks
                $newStatement = $this->statementFragmentService->copyStatementFragments(
                    $statement->getFragments(),
                    $newStatement
                );
            }

            if ($persistAndFlush) {
                $em->flush();
            }

            if (true === $createReport) {
                try {
                    $entry = $this->statementReportEntryFactory->createStatementCopiedEntry($newStatement);
                    if ($persistAndFlush) {
                        $this->reportService->persistAndFlushReportEntries($entry);
                    } else {
                        $this->reportService->persistReportEntries([$entry]);
                    }
                    $this->logger->info(
                        'generate report of copyStatement(). ReportID: ',
                        ['identifier' => $entry->getIdentifier()]
                    );
                } catch (Exception $e) {
                    $this->getLogger()->warning('Add Report in copyStatementAction() failed Message: ', [$e]);
                }
            }

            return $newStatement;
        } catch (CopyException $e) {
            throw $e;
        } catch (ClusterStatementCopyNotImplementedException) {
            return false;
        } catch (Exception $e) {
            $this->getLogger()->error('Could not copy statement ', [$e]);

            return false;
        }
    }

    /**
     * Determines if the given statement is allowed to copy.
     * Will check for headStatement, assignment and claimant.
     * Will also add a corresponding message.
     *
     * @param Statement $statement     - statement to check
     * @param Statement $statement     - statement to check
     * @param bool      $ignoreCluster - Determines if this method is called on create a Statement
     *
     * @return bool false, if the given statement<ul>
     *              <li>is a headstatement</li>
     *              <li>is not claimed by current user</li>
     *              <li>has fragments, which are not claimed by current user</li>
     *              <li>has fragments, which are assigned to a reviewer (department)</li></ul>
     *
     * @throws MessageBagException
     * @throws UserNotFoundException
     * @throws ClusterStatementCopyNotImplementedException
     */
    public function isCopyStatementAllowed(
        Statement $statement,
        bool $ignoreCluster = false,
        bool $ignoreReviewer = false,
    ): bool {
        // check here for clustermember instead for cluster flag, because on create new cluster a statement will be created, marked as cluster and  have to be copied.
        if (false === $ignoreCluster && $statement->isClusterStatement()) {
            $this->messageBag->add(
                'warning',
                'warning.statement.cluster.copy',
                ['clusterId' => $statement->getExternId()]
            );
            // This method is supposed to return false if copying a cluster statement is
            // not allowed. However I wan't to be able to know WHY copying was not allowed
            // and using exceptions seems kind of easier than parsing message bag strings.
            $exception = new ClusterStatementCopyNotImplementedException('Copying cluster statements is not implemented yet.');
            $exception->setExternId($statement->getExternId());
            throw $exception;
        }

        // T7137: avoid copy Statement if statement or fragments are not claimed by current user
        // or fragments are assigned to orga
        if (true === $this->permissions->hasPermission('feature_statement_assignment')) {
            // check for claim of statement
            if (!$this->assignService->isStatementObjectAssignedToCurrentUser($statement)
                && null !== $statement->getAssignee()) {
                // stn kann nicht kopiert werden, weil statement nicht von dem current user geclaimed ist
                $this->messageBag->add(
                    'warning',
                    'statement.copy.not.claimed.by.current.user',
                    ['id' => $statement->getExternId()]
                );

                return false;
            }

            // check for claim of fragments of statement
            if (!$this->statementFragmentService->areAllFragmentsClaimedByCurrentUser($statement->getId())) {
                // stn kann nicht copiert werden, weil datensätze nicht alle vom current user claimed sind!
                $this->messageBag->add(
                    'warning',
                    'statement.copy.fragments.not.claimed.by.current.user',
                    ['id' => $statement->getExternId()]
                );

                return false;
            }
        }

        // check for assigment his fragments
        if (!$ignoreReviewer && !$this->statementFragmentService->isNoFragmentAssignedToReviewer($statement->getId())) {
            // At least on fragment is assigned to a reviewer
            $this->messageBag->add(
                'warning',
                'warning.statement.copy.fragment.assigned.to.reviewer',
                ['statementId' => $statement->getExternId()]
            );

            return false;
        }

        // check for placeholder
        if ($statement->isPlaceholder()) {
            $this->messageBag->add(
                'warning',
                'warning.statement.copy.placeholder',
                ['externId' => $statement->getExternId()]
            );

            return false;
        }

        return true;
    }
}
