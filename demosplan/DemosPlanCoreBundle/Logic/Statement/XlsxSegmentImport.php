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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\ManualOriginalStatementCreatedEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\StatementCreatedViaExcelEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Exceptions\AddonResourceNotFoundException;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualOriginalStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\Event\Statement\StatementCreatedViaExcelEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Exception\RowAwareViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelImporter;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\SegmentExcelImportResult;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\SplFileInfo;

class XlsxSegmentImport
{
    /**
     * @var array
     */
    private $createdStatements;
    /**
     * @var array
     */
    private $createdSegments;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherPostInterface $eventDispatcher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ExcelImporter $xlsxSegmentImporter,
        private readonly LoggerInterface $logger,
        private readonly SegmentRepository $segmentRepository,
        private readonly StatementRepository $statementRepository,
        private readonly StatementService $statementService,
    ) {
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
     * @throws AddonResourceNotFoundException
     */
    public function importFromFile(FileInfo $file): SegmentExcelImportResult
    {
        $fileInfo = new SplFileInfo($file->getAbsolutePath(), '', $file->getHash());

        // allow to rollback all in case of error
        $doctrineConnection = $this->entityManager->getConnection();
        try {
            $doctrineConnection->beginTransaction();
            $importResult = $this->xlsxSegmentImporter->processSegments($fileInfo);

            if ($importResult->hasErrors()) {
                $doctrineConnection->rollBack();

                return $importResult;
            }

            array_map($this->entityManager->persist(...), $this->xlsxSegmentImporter->getGeneratedTags());
            array_map($this->entityManager->persist(...), $importResult->getStatements());

            foreach ($importResult->getStatements() as $statement) {
                try {
                    $statementArray = $this->statementService->convertToLegacy($statement);
                    $this->statementService->addReportNewStatement($statementArray);
                } catch (Exception $exception) {
                    $doctrineConnection->rollBack();

                    $this->logger->warning('Add Report on importFromFile() failed Message: ', [$exception]);

                    throw $exception;
                }

                $this->eventDispatcher->dispatch(
                    new ManualOriginalStatementCreatedEvent($statement),
                    ManualOriginalStatementCreatedEventInterface::class
                );
            }

            $this->entityManager->flush();

            $doctrineConnection->commit();

            foreach ($importResult->getStatements() as $statement) {
                // this event allows to send segmentation proposals requests
                $this->dispatcher->dispatch(
                    new StatementCreatedViaExcelEvent($statement),
                    StatementCreatedViaExcelEventInterface::class
                );
            }

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
     * @return list<array{id: int, currentWorksheet: string, lineNumber: int, message: string}>
     */
    public function getErrorsAsArray(): array
    {
        return $this->xlsxSegmentImporter->getErrorsAsArray();
    }
}
