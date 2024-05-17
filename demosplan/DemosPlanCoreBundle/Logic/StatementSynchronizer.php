<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DateInterval;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Entity\EntitySyncLink;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\FileContainer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePerson;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Exception\CopyException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Exception\ORMException;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Exception;
use Symfony\Component\Validator\Validator\ValidatorInterface;

use function in_array;

class StatementSynchronizer
{
    public function __construct(
        private readonly CurrentUserInterface $currentUserProvider,
        private readonly FileService $fileService,
        private readonly StatementCopier $statementCopier,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        private readonly StatementRepository $statementRepository,
        private readonly StatementService $statementService,
        private readonly TransactionService $transactionService,
        private readonly ValidatorInterface $validator
    ) {
    }

    /**
     * Synchronizes the given statements from their procedure into the given target procedure
     * by creating a new original statement and a copy of that original statement.
     *
     * When creating the new original statement, only specific fields are copied, to avoid
     * accidentally copying fields that are added later but whose values in the source
     * statement must not be made visible in the target procedure.
     *
     * For each given statement an {@link EntitySyncLink} is created between the given statement and
     * the new original statement. This instance is needed when information is synchronized back into the
     * source procedure. Additional {@link EntitySyncLink} instances are created for specific
     * relationships (potentially nested) of the given statements for the same reason.
     *
     * Note that this method may **not** perform any sanity checks on the given statements. I.e.
     * it makes not sense to pass original statements or statements that were already synchronized
     * into this method and the behavior if done so anyway is undefined.
     *
     * @param array<string, Statement> $statements
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws ConnectionException
     */
    public function synchronizeStatements(array $statements, Procedure $targetProcedure): void
    {
        $this->transactionService->executeAndFlushInTransaction(
            function (EntityManager $em) use ($statements, $targetProcedure): void {
                foreach ($statements as $statement) {
                    $this->synchronizeStatement($statement, $targetProcedure);
                }
            }
        );
    }

    /**
     * @throws NonUniqueResultException
     * @throws ORMException
     * @throws CopyException
     * @throws Exception
     */
    protected function synchronizeStatement(Statement $sourceStatement, Procedure $targetProcedure): void
    {
        // use source statement as "original" in target procedure
        [$newOriginalStatement, $targetOriginalFileContainers] = $this->copyAsOriginalStatement(
            $sourceStatement,
            $targetProcedure,
        );

        $newStatement = $this->statementCopier->copyStatementObjectWithinProcedureWithRelatedFiles(
            $newOriginalStatement,
            false,
        );
        /*
         * to follow the schema that all Original-STN-FileContainers as well as its Child-STN-FileContainers
         * share the same { @link File } reference the { @link FileContainer } gets cloned here
         * the cloned FileContainer itself gets a new id assigned - just its File reference get shared.
         */
        $this->cloneFileContainersToStatement($targetOriginalFileContainers, $newStatement);

        $this->validateStatement($newStatement);
        $this->createAndPersistLink($sourceStatement, $newOriginalStatement);

        $sourceStatementReportEntry = $this->statementReportEntryFactory->createStatementSynchronizationInSource(
            $this->currentUserProvider->getUser(),
            $sourceStatement
        );
        $targetStatementReportEntry = $this->statementReportEntryFactory->createStatementSynchronizationInTarget($newOriginalStatement);
        $this->statementRepository->persistEntities([$sourceStatementReportEntry, $targetStatementReportEntry]);

        $statementIds = [$newOriginalStatement->getId(), $newStatement->getId()];
        if (in_array(null, $statementIds, true)) {
            throw new InvalidArgumentException('Statement IDs not set yet.');
        }
    }

    /**
     * Coping an original statement.
     *
     * @return array{0: Statement, 1: array<int, FileContainer>}
     *
     * @throws ORMException
     * @throws NonUniqueResultException
     * @throws Exception
     */
    private function copyAsOriginalStatement(
        Statement $sourceStatement,
        Procedure $targetProcedure
    ): array {
        if ($sourceStatement->isOriginal()) {
            throw new InvalidArgumentException('Given statement is an original statement.');
        }

        if ($sourceStatement->isDeleted()) {
            throw new InvalidArgumentException('Given statement is deleted.');
        }

        // persist statement here to create an uuid which is needed for copying files
        $newOriginalStatement = new Statement();
        $this->statementRepository->persistEntities([$newOriginalStatement]);

        // warning: order of the method calls matter, as later methods may use information
        // of fields set previously

        $newOriginalStatement->setSubmitType($sourceStatement->getSubmitType());
        $newOriginalStatement->setSubmitTypeTranslated($sourceStatement->getSubmitTypeTranslated());
        $newOriginalStatement->setMapFile($sourceStatement->getMapFile());
        $newOriginalStatement->setSubmit($sourceStatement->getSubmitObject()->add(new DateInterval('PT1S')));
        $newOriginalStatement->setExternId($sourceStatement->getExternId());
        $newOriginalStatement->setProcedure($targetProcedure);
        $newOriginalStatement->setOrganisation($sourceStatement->getOrganisation());
        $newOriginalStatement->setManual($sourceStatement->isManual());
        $newOriginalStatement->setPublicStatement($sourceStatement->getPublicStatement());
        $newOriginalStatement->setSubmitterEmailAddress($sourceStatement->getSubmitterEmailAddress());
        $newOriginalStatement->setText($sourceStatement->getText());
        $newOriginalStatement->setSend($sourceStatement->getSend());
        $newOriginalStatement->setAnonymous($sourceStatement->isAnonymous());
        $newOriginalStatement->setPhase($sourceStatement->getPhase());
        $newOriginalStatement->setMemo($sourceStatement->getMemo());
        // This may be useless information in the target procedure, but copying the value
        // from the source procedure seems like the most resilient thing to do in case
        // of permission changes.
        $newOriginalStatement->setPublicVerified($sourceStatement->getPublicVerified());
        $this->copyMeta($sourceStatement, $newOriginalStatement);
        $this->copyInternId($sourceStatement, $newOriginalStatement);
        $this->copyAttachments($sourceStatement, $newOriginalStatement);
        $this->copySimilarStatementSubmitters($sourceStatement, $newOriginalStatement);
        $fileContainerCopies = $this->copyFileContainersBetweenStatements($sourceStatement, $newOriginalStatement);

        $this->validateStatement($newOriginalStatement);

        return [$newOriginalStatement, $fileContainerCopies];
    }

    /**
     * Will not copy {@link StatementMeta::$caseWorkerName} because this is no information
     * intended for the target procedure.
     */
    private function copyMeta(Statement $sourceStatement, Statement $targetStatement): void
    {
        $oldMeta = $sourceStatement->getMeta();

        $newMeta = new StatementMeta();
        $newMeta->setAuthoredDate($oldMeta->getAuthoredDateObject());
        $newMeta->setAuthorFeedback($oldMeta->getAuthorFeedback());
        $newMeta->setAuthorName($oldMeta->getAuthorName());
        $newMeta->setHouseNumber($oldMeta->getHouseNumber());
        $newMeta->setMiscData($oldMeta->getMiscData());
        $newMeta->setOrgaCity($oldMeta->getOrgaCity());
        $newMeta->setOrgaName($oldMeta->getOrgaName());
        $newMeta->setOrgaDepartmentName($oldMeta->getOrgaDepartmentName());
        $newMeta->setOrgaEmail($oldMeta->getOrgaEmail());
        $newMeta->setOrgaPostalCode($oldMeta->getOrgaPostalCode());
        $newMeta->setOrgaStreet($oldMeta->getOrgaStreet());
        $newMeta->setSubmitName($oldMeta->getSubmitName());
        $newMeta->setSubmitOrgaId($oldMeta->getSubmitOrgaId());
        $newMeta->setSubmitUId($oldMeta->getSubmitUId());
        $newMeta->setStatement($targetStatement);

        $targetStatement->setMeta($newMeta);

        $metaViolations = $this->validator->validate($newMeta, null, [
            Statement::DEFAULT_VALIDATION,
            Statement::IMPORT_VALIDATION,
        ]);
        if (0 !== $metaViolations->count()) {
            throw ViolationsException::fromConstraintViolationList($metaViolations);
        }
    }

    /**
     * Copies the {@link Statement::$internId} but ensures it is unique in the target procedure
     * and not an empty string.
     */
    private function copyInternId(Statement $sourceStatement, Statement $targetStatement): void
    {
        $internId = $sourceStatement->getInternId();
        if ('' === $internId) {
            throw new InvalidArgumentException('Given internID cant be empty string.');
        }

        $targetProcedureId = $targetStatement->getProcedure()->getId();

        $internIdIsUnique = $this->statementService->isInternIdUniqueForProcedure($internId, $targetProcedureId);
        if (!$internIdIsUnique) {
            throw new InvalidArgumentException("A statement with intern ID '{$sourceStatement->getInternId()}' already exists in the target procedure '$targetProcedureId'");
        }

        $targetStatement->setInternId($internId);
    }

    /**
     * @return array<int, FileContainer>
     *
     * @throws Exception
     */
    private function copyFileContainersBetweenStatements(
        Statement $sourceStatement,
        Statement $newOriginalStatement
    ): array {
        $sourceFileContainers = $this->statementService->getFileContainersForStatement($sourceStatement->getId());

        return $this->copyFileContainersToStatement($sourceFileContainers, $newOriginalStatement);
    }

    /**
     * @param array<int, FileContainer> $fileContainers
     *
     * @return array<int, FileContainer>
     *
     * @throws Exception
     */
    private function copyFileContainersToStatement(
        array $fileContainers,
        Statement $targetStatement
    ): array {
        $fileContainerCopies = [];
        foreach ($fileContainers as $fileContainer) {
            $newFileContainer = $this->statementRepository->copyFileContainer($fileContainer, $targetStatement);
            $fileViolations = $this->validator->validate($newFileContainer);
            if (0 !== $fileViolations->count()) {
                throw ViolationsException::fromConstraintViolationList($fileViolations);
            }
            $this->createAndPersistLink($fileContainer, $newFileContainer);
            $fileContainerCopies[] = $newFileContainer;
        }

        $targetStatement->setFiles(
            array_map(
                static fn (FileContainer $fileContainer): string => $fileContainer->getFileString(),
                $fileContainerCopies
            )
        );

        return $fileContainerCopies;
    }

    /**
     * @param array<int, FileContainer> $originalfileContainers
     *
     * @throws Exception
     */
    public function cloneFileContainersToStatement(
        array $originalfileContainers,
        Statement $newStatement
    ): void {
        $fileStrings = [];
        foreach ($originalfileContainers as $oldFileContainer) {
            $copy = $this->fileService->addFileContainerCopy($newStatement->getId(), $oldFileContainer);
            $fileStrings[] = $copy->getFileString();
        }

        $newStatement->setFiles($fileStrings);
    }

    /**
     * @throws ORMException
     */
    private function copyAttachments(Statement $sourceStatement, Statement $targetStatement): void
    {
        $copiedAttachments = new ArrayCollection();
        foreach ($sourceStatement->getAttachments() as $attachment) {
            $copiedAttachment = $this->statementRepository->copyAttachment($targetStatement, $attachment);

            $attachmentViolations = $this->validator->validate($copiedAttachment);
            if (0 !== $attachmentViolations->count()) {
                throw ViolationsException::fromConstraintViolationList($attachmentViolations);
            }

            $this->statementRepository->persistEntities([$copiedAttachment]);
            $copiedAttachments->add($copiedAttachment);
            $this->createAndPersistLink($attachment, $copiedAttachment);
        }
        $targetStatement->setAttachments($copiedAttachments);
    }

    /**
     * Simply copies the person from one statement to the other, without creating a {@link EntitySyncLink}
     * instance. This is because we try to avoid connections between {@link ProcedurePerson}s in
     * different procedures for data protection reasons.
     */
    private function copySimilarStatementSubmitters(Statement $sourceStatement, Statement $targetStatement): void
    {
        $submitters = $sourceStatement->getSimilarStatementSubmitters();
        $newSubmitters = $submitters->map(function (ProcedurePerson $person) use ($targetStatement): ProcedurePerson {
            $copy = new ProcedurePerson($person->getFullName(), $targetStatement->getProcedure());
            $copy->setEmailAddress($person->getEmailAddress());
            $copy->setStreetName($person->getStreetName());
            $copy->setStreetNumber($person->getStreetNumber());
            $copy->setPostalCode($person->getPostalCode());
            $copy->setCity($person->getCity());
            $personViolations = $this->validator->validate($copy);
            if (0 !== $personViolations->count()) {
                throw ViolationsException::fromConstraintViolationList($personViolations);
            }

            $this->statementRepository->persistEntities([$copy]);

            return $copy;
        });
        $targetStatement->setSimilarStatementSubmitters($newSubmitters);
    }

    /**
     * @template T of \DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface
     *
     * @param T $source
     * @param T $target
     *
     * @throws ORMException
     */
    private function createAndPersistLink(UuidEntityInterface $source, UuidEntityInterface $target): void
    {
        $syncLink = new EntitySyncLink($source, $target);
        $linkViolations = $this->validator->validate($syncLink);
        if (0 !== $linkViolations->count()) {
            throw ViolationsException::fromConstraintViolationList($linkViolations);
        }
        $this->statementRepository->persistEntities([$syncLink]);
    }

    private function validateStatement(Statement $statement): void
    {
        $statementViolations = $this->validator->validate($statement, null, [
            Statement::DEFAULT_VALIDATION,
            Statement::MANUAL_CREATE_VALIDATION,
            Statement::IMPORT_VALIDATION,
        ]);
        if (0 !== $statementViolations->count()) {
            throw ViolationsException::fromConstraintViolationList($statementViolations);
        }
    }
}
