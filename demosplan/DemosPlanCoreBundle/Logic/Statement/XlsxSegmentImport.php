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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualOriginalStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\RowAwareViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\SegmentExcelImportResult;
use demosplan\DemosPlanCoreBundle\Logic\SearchIndexTaskService;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use demosplan\DemosPlanStatementBundle\Logic\StatementService;
use demosplan\DemosPlanStatementBundle\Repository\StatementRepository;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use demosplan\plugins\workflow\SegmentsManager\Entity\Segment;
use demosplan\plugins\workflow\SegmentsManager\Repository\Segment\SegmentRepository;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

class XlsxSegmentImport
{
    /**
     * @var SearchIndexTaskService
     */
    private $searchIndexTaskService;
    /**
     * @var StatementRepository
     */
    private $statementRepository;
    /**
     * @var StatementService
     */
    private $statementService;
    /**
     * @var array
     */
    private $createdStatements;
    /**
     * @var array
     */
    private $createdSegments;
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;
    /**
     * @var EntityManagerInterface
     */
    private $entityManager;
    /**
     * @var EventDispatcherPostInterface
     */
    private $eventDispatcher;
    /**
     * @var ExcelImporter
     */
    private $xlsxSegmentImporter;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var SegmentRepository
     */
    private $segmentRepository;

    public function __construct(
        CurrentUserInterface $currentUser,
        EntityManagerInterface $entityManager,
        EventDispatcherPostInterface $eventDispatcher,
        ExcelImporter $xlsxSegmentImporter,
        LoggerInterface $logger,
        SearchIndexTaskService $searchIndexTaskService,
        SegmentRepository $segmentRepository,
        StatementRepository $statementRepository,
        StatementService $statementService
    ) {
        $this->currentUser = $currentUser;
        $this->entityManager = $entityManager;
        $this->eventDispatcher = $eventDispatcher;
        $this->logger = $logger;
        $this->searchIndexTaskService = $searchIndexTaskService;
        $this->segmentRepository = $segmentRepository;
        $this->statementRepository = $statementRepository;
        $this->statementService = $statementService;
        $this->xlsxSegmentImporter = $xlsxSegmentImporter;
    }

    /**
     * Import statements from excel document, which is located in the given FileInfo.
     * The extracted statements will be validated, persisted, sliced (into segments) and indexed.
     * Also report-entries will be generated and the StatementCreatedEvent dispatched.
     * In case of an occurring error on generating the statements, the process will continued to getting all
     * invalid cases and therefore allow to return collection of errors.
     * The generated Statements will only be persisted, if the document was processed without an error.
     *
     * @param FileInfo $file Hands over basic information about the file
     *
     * @throws Exception
     * @throws RowAwareViolationsException
     * @throws ConnectionException
     */
    public function importFromFile(FileInfo $file): SegmentExcelImportResult
    {
        $fileInfo = new SplFileInfo($file->getAbsolutePath(), '', $file->getHash());

        //allow to rollback all in case of error
        $doctrineConnection = $this->entityManager->getConnection();
        try {
            $doctrineConnection->beginTransaction();
            $importResult = $this->xlsxSegmentImporter->processSegments($fileInfo);

            if ($importResult->hasErrors()) {
                $doctrineConnection->rollBack();

                return $importResult;
            }

            array_map([$this->entityManager, 'persist'], $this->xlsxSegmentImporter->getGeneratedTags());
            array_map([$this->entityManager, 'persist'], $importResult->getStatements());

            foreach ($importResult->getStatements() as $statement) {
                try {
                    $statementArray = $this->statementService->convertToLegacy($statement);
                    $this->statementService->addReportNewStatement($statementArray);
                } catch (Exception $exception) {
                    $doctrineConnection->rollBack();

                    $this->logger->warning('Add Report on importFromFile() failed Message: ', [$exception]);

                    throw $exception;
                }

                $this->eventDispatcher->post(new ManualOriginalStatementCreatedEvent($statement));
            }

            $this->entityManager->flush();

            foreach ($importResult->getStatements() as $statement) {
                $this->searchIndexTaskService->addIndexTask(Statement::class, $statement->getId());
            }

            $doctrineConnection->commit();

            return $importResult;
        } catch (Exception $exception) {
            $doctrineConnection->rollBack();

            throw $exception;
        }
    }

    public function hasErrors(): bool
    {
        return $this->xlsxSegmentImporter->hasErrors();
    }

    /**
     * @return array<int, Statement>
     */
    public function getCreatedStatements(): array
    {
        return $this->createdStatements;
    }

    /**
     * @return array<int, Segment>
     */
    public function getCreatedSegments(): array
    {
        return $this->createdSegments;
    }

    /**
     * @return array<int, array>
     */
    public function getErrorsAsArray(): array
    {
        return $this->xlsxSegmentImporter->getErrorsAsArray();
    }
}
