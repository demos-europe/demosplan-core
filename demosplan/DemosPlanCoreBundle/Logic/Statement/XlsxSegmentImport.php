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
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\ExcelValidationService;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\SegmentExcelImportResult;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Finder\SplFileInfo;
use Throwable;

class XlsxSegmentImport
{
    /**
     * Batch size for processing statements to prevent memory overflow.
     * ~5,200 entities per batch (300 statements × ~17 segments avg + metadata).
     * This determines how often we flush to database during import.
     */
    private const BATCH_SIZE = 300;

    /**
     * Elasticsearch bulk indexing batch size.
     * Larger than BATCH_SIZE since ES can handle bigger bulks efficiently.
     */
    private const ES_BULK_INDEX_BATCH_SIZE = 1000;

    /**
     * @var array
     */
    private $createdStatements;
    /**
     * @var array
     */
    private $createdSegments;
    /**
     * @var array<string> Collected segment IDs for bulk indexing
     */
    private array $segmentIdsForIndexing = [];
    /**
     * @var array Collected statement arrays for deferred report generation
     */
    private array $statementsForReports = [];

    /**
     * @var callable|null Optional progress callback for tracking import progress
     */
    private $progressCallback;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly EntityManagerInterface $entityManager,
        private readonly EventDispatcherPostInterface $eventDispatcher,
        private readonly EventDispatcherInterface $dispatcher,
        private readonly ExcelImporter $xlsxSegmentImporter,
        private readonly ExcelValidationService $excelValidationService,
        private readonly LoggerInterface $logger,
        private readonly ObjectPersisterInterface $segmentPersister,
        private readonly ReportService $reportService,
        private readonly SegmentRepository $segmentRepository,
        private readonly StatementReportEntryFactory $statementReportEntryFactory,
        private readonly StatementRepository $statementRepository,
        private readonly StatementService $statementService,
    ) {
    }

    /**
     * Set a progress callback for tracking import progress.
     * The callback receives ($processedCount, $totalCount) as parameters.
     */
    public function setProgressCallback(?callable $callback): void
    {
        $this->progressCallback = $callback;
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
        $startTime = microtime(true);
        $this->logger->info('=== SEGMENT IMPORT START (TWO-PASS) ===', [
            'file'      => $file->getFileName(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $fileInfo = new SplFileInfo($file->getAbsolutePath(), '', $file->getHash());

        $validationResult = $this->runValidationPass($fileInfo, $startTime);
        if (null !== $validationResult) {
            return $validationResult;
        }

        $disabledListeners = $this->disableElasticsearchListeners();

        try {
            $importResult = $this->runPersistencePass($fileInfo);

            $this->logger->info('=== SEGMENT IMPORT COMPLETE (TWO-PASS) ===', [
                'total_duration_sec' => round(microtime(true) - $startTime, 2),
                'peak_memory_mb'     => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $importResult;
        } catch (Exception $exception) {
            $this->logger->error('Segment import failed', [
                'exception'    => $exception->getMessage(),
                'duration_sec' => round(microtime(true) - $startTime, 2),
            ]);

            throw $exception;
        } finally {
            $this->reEnableElasticsearchListeners($disabledListeners);
        }
    }

    /**
     * Persist tags in batches to prevent memory overflow.
     *
     * @param array $tags Array of Tag entities to persist
     */
    private function persistTagsInBatches(array $tags): void
    {
        $batchCount = 0;
        $batchNumber = 0;
        $totalTags = count($tags);

        foreach ($tags as $tag) {
            $this->entityManager->persist($tag);

            if (0 === ++$batchCount % self::BATCH_SIZE) {
                $batchStart = microtime(true);
                $this->entityManager->flush();
                // Don't clear() - keeps shared entities (Topic, Procedure) managed
                ++$batchNumber;

                $this->logger->debug('Tag batch flushed', [
                    'batch'              => $batchNumber,
                    'processed'          => $batchCount,
                    'total'              => $totalTags,
                    'flush_duration_sec' => round(microtime(true) - $batchStart, 2),
                    'memory_mb'          => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);

                $batchCount = 0;
            }
        }

        // Flush remaining tags
        if ($batchCount > 0) {
            $batchStart = microtime(true);
            $this->entityManager->flush();
            // Don't clear() - keeps shared entities (Topic, Procedure) managed

            $this->logger->debug('Final tag batch flushed', [
                'remaining'          => $batchCount,
                'flush_duration_sec' => round(microtime(true) - $batchStart, 2),
            ]);
        }
    }

    /**
     * Persist statements with segments in batches to prevent memory overflow.
     *
     * @param array $statements Array of Statement entities to persist
     *
     * @throws Exception
     */
    private function persistStatementsInBatches(array $statements): void
    {
        $statementBatch = [];
        $batchNumber = 0;
        $totalStatements = count($statements);
        $processedStatements = 0;

        foreach ($statements as $statement) {
            $this->entityManager->persist($statement);
            $statementBatch[] = $statement;
            ++$processedStatements;

            // Flush batch when reaching batch size
            if (count($statementBatch) >= self::BATCH_SIZE) {
                ++$batchNumber;
                $this->processBatch($statementBatch, $batchNumber, $processedStatements, $totalStatements);
                $statementBatch = [];
            }
        }

        // Flush remaining statements
        if (!empty($statementBatch)) {
            ++$batchNumber;
            $this->processBatch($statementBatch, $batchNumber, $processedStatements, $totalStatements);
        }
    }

    /**
     * Process a batch of statements: flush, dispatch events, and call progress callback.
     *
     * @throws Exception
     */
    private function processBatch(
        array $statementBatch,
        int $batchNumber,
        int $processedCount,
        int $totalCount,
    ): void {
        $this->flushAndClearBatch($batchNumber, $processedCount, $totalCount);

        // Call progress callback if set
        if (null !== $this->progressCallback) {
            call_user_func($this->progressCallback, $processedCount, $totalCount);
        }

        // Dispatch events AFTER flush to ensure addons can query statements from database
        $this->dispatchStatementEvents($statementBatch);
    }

    /**
     * Dispatch events for flushed statements.
     *
     * @throws Exception
     */
    private function dispatchStatementEvents(array $statementBatch): void
    {
        foreach ($statementBatch as $flushedStatement) {
            // Defer report generation - collect statement data for batch processing after commit
            try {
                $statementArray = $this->statementService->convertToLegacy($flushedStatement);
                $this->statementsForReports[] = $statementArray;
            } catch (Exception $exception) {
                $this->logger->warning('Convert to legacy failed: ', [$exception]);
                throw $exception;
            }

            // Dispatch ManualOriginalStatementCreatedEvent AFTER flush (addon safety)
            $this->eventDispatcher->dispatch(
                new ManualOriginalStatementCreatedEvent($flushedStatement),
                ManualOriginalStatementCreatedEventInterface::class
            );
        }
    }

    /**
     * Flush current batch and clear EntityManager to free memory.
     */
    private function flushAndClearBatch(int $batchNumber, int $processedCount, int $totalCount): void
    {
        $batchStart = microtime(true);

        $this->entityManager->flush();

        $this->logger->info('Statement batch flushed', [
            'batch'              => $batchNumber,
            'processed'          => $processedCount,
            'total'              => $totalCount,
            'progress_pct'       => round(($processedCount / $totalCount) * 100, 1),
            'flush_duration_sec' => round(microtime(true) - $batchStart, 2),
            'memory_mb'          => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);
    }

    /**
     * Batch create report entries for all imported statements.
     * This avoids 1,500 individual flush operations during statement persistence.
     */
    private function batchCreateReportEntries(): void
    {
        try {
            if (empty($this->statementsForReports)) {
                $this->logger->warning('No statements collected for report generation');

                return;
            }

            $reportEntries = [];
            foreach ($this->statementsForReports as $statementArray) {
                $entry = $this->statementReportEntryFactory->createStatementCreatedEntry($statementArray);
                $reportEntries[] = $entry;
            }

            // Persist all report entries in one batch
            $this->reportService->persistAndFlushReportEntries(...$reportEntries);

            $this->logger->info('Report entries batch created', [
                'count' => count($reportEntries),
            ]);
        } catch (Exception $exception) {
            // Log error but don't fail the import
            $this->logger->error(
                'Failed to create report entries, but import succeeded',
                [
                    'exception' => $exception->getMessage(),
                    'trace'     => $exception->getTraceAsString(),
                ]
            );
        }
    }

    /**
     * Bulk index all segments in Elasticsearch after database commit.
     * Uses FOSElasticaBundle's insertMany() for efficient bulk indexing.
     *
     * Loads segments in batches from database by ID to avoid memory issues.
     */
    private function bulkIndexSegments(): void
    {
        try {
            if (empty($this->segmentIdsForIndexing)) {
                $this->logger->warning('No segment IDs collected for indexing');

                return;
            }

            $totalSegments = count($this->segmentIdsForIndexing);
            $this->logger->info('Starting bulk Elasticsearch indexing', [
                'total_segments' => $totalSegments,
            ]);

            // Process segments in batches to avoid loading all into memory at once
            $segmentIdBatches = array_chunk($this->segmentIdsForIndexing, self::ES_BULK_INDEX_BATCH_SIZE);
            $indexed = 0;

            foreach ($segmentIdBatches as $batchNum => $segmentIdBatch) {
                $batchStart = microtime(true);

                // Load segments from database by ID
                $segments = $this->segmentRepository->findBy(['id' => $segmentIdBatch]);

                if (empty($segments)) {
                    $this->logger->warning('No segments found for batch', ['batch' => $batchNum + 1]);
                    continue;
                }

                // Bulk index this batch
                $this->segmentPersister->insertMany($segments);
                $indexed += count($segments);

                $this->logger->info('ES batch indexed', [
                    'batch'              => $batchNum + 1,
                    'batch_count'        => count($segments),
                    'total_indexed'      => $indexed,
                    'total_segments'     => $totalSegments,
                    'progress_pct'       => round(($indexed / $totalSegments) * 100, 1),
                    'batch_duration_sec' => round(microtime(true) - $batchStart, 2),
                    'memory_mb'          => round(memory_get_usage(true) / 1024 / 1024, 2),
                ]);

                // Clear batch array - segments are still managed by EntityManager
                unset($segments);
            }

            $this->logger->info('Elasticsearch bulk indexing completed', [
                'total_indexed' => $indexed,
            ]);
        } catch (Exception $exception) {
            // Log error but don't fail the import - database changes are already committed
            $this->logger->error(
                'Failed to index segments in Elasticsearch after successful database import',
                [
                    'exception' => $exception->getMessage(),
                    'trace'     => $exception->getTraceAsString(),
                ]
            );
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

    /**
     * Run Pass 1: Lightweight validation without creating entities.
     * Returns SegmentExcelImportResult with errors if validation fails, null if successful.
     */
    private function runValidationPass(SplFileInfo $fileInfo, float $startTime): ?SegmentExcelImportResult
    {
        $phaseStart = microtime(true);
        $validationResult = $this->excelValidationService->validateExcelFile($fileInfo);
        $this->logger->info('Phase 1 (Validation Pass): Completed', [
            'duration_sec' => round(microtime(true) - $phaseStart, 2),
            'errors'       => $validationResult->getErrorCount(),
            'memory_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        if (!$validationResult->hasErrors()) {
            $this->logger->info('Pass 1 validation successful - proceeding to Pass 2 (persistence)', [
                'memory_freed_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);

            return null;
        }

        $this->logger->warning('Import aborted due to validation errors', [
            'error_count'        => $validationResult->getErrorCount(),
            'total_duration_sec' => round(microtime(true) - $startTime, 2),
        ]);

        $importResult = new SegmentExcelImportResult();
        foreach ($validationResult->getErrors() as $error) {
            $importResult->addError(
                $error['message'],
                $error['lineNumber'],
                $error['currentWorksheet']
            );
        }

        return $importResult;
    }

    /**
     * Disable Elasticsearch auto-indexing listeners during import.
     * Returns array of disabled listeners to re-enable later.
     */
    private function disableElasticsearchListeners(): array
    {
        $eventManager = $this->entityManager->getEventManager();
        $disabledListeners = [];

        $eventsToCheck = [
            'prePersist', 'postPersist',
            'preUpdate', 'postUpdate',
            'preRemove', 'postRemove',
            'postLoad', 'preFlush', 'onFlush', 'postFlush', 'onClear',
        ];

        foreach ($eventsToCheck as $eventName) {
            try {
                $listeners = $eventManager->getListeners($eventName);
            } catch (Throwable $e) {
                // Skip events that have no registered listeners
                continue;
            }

            foreach ($listeners as $listener) {
                if (str_contains(get_class($listener), 'Elastica')) {
                    $eventManager->removeEventListener($eventName, $listener);
                    $disabledListeners[$eventName][] = $listener;
                }
            }
        }

        $this->logger->info('Elasticsearch auto-indexing disabled during import', [
            'listeners_disabled' => count($disabledListeners),
        ]);

        return $disabledListeners;
    }

    /**
     * Re-enable Elasticsearch listeners that were disabled during import.
     */
    private function reEnableElasticsearchListeners(array $disabledListeners): void
    {
        $eventManager = $this->entityManager->getEventManager();

        foreach ($disabledListeners as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $eventManager->addEventListener($eventName, $listener);
                $this->logger->info('Re-enabled Elasticsearch listener after import', [
                    'event'    => $eventName,
                    'listener' => get_class($listener),
                ]);
            }
        }
    }

    /**
     * Run Pass 2: Entity creation and persistence phases.
     */
    private function runPersistencePass(SplFileInfo $fileInfo): SegmentExcelImportResult
    {
        $phaseStart = microtime(true);
        $importResult = $this->xlsxSegmentImporter->processSegments($fileInfo);
        $this->logger->info('Phase 2 (Persistence Pass): Excel parsing completed', [
            'duration_sec'     => round(microtime(true) - $phaseStart, 2),
            'statements_count' => count($importResult->getStatements()),
            'memory_mb'        => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        if ($importResult->hasErrors()) {
            $this->logger->error('Unexpected errors during persistence pass', [
                'error_count' => count($importResult->getErrorsAsArray()),
            ]);

            return $importResult;
        }

        $this->persistEntitiesAndIndex($importResult);

        return $importResult;
    }

    /**
     * Persist all entities (tags, statements, segments) and index them.
     */
    private function persistEntitiesAndIndex(SegmentExcelImportResult $importResult): void
    {
        $phaseStart = microtime(true);
        $this->persistTagsInBatches($this->xlsxSegmentImporter->getGeneratedTags());
        $this->logger->info('Phase 3: New tags flushed', [
            'duration_sec'   => round(microtime(true) - $phaseStart, 2),
            'new_tags_count' => count($this->xlsxSegmentImporter->getGeneratedTags()),
            'memory_mb'      => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $phaseStart = microtime(true);
        $this->persistStatementsInBatches($importResult->getStatements());
        $this->logger->info('Phase 4: Statements and segments persisted', [
            'duration_sec'     => round(microtime(true) - $phaseStart, 2),
            'statements_count' => count($importResult->getStatements()),
            'memory_mb'        => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $this->collectSegmentIdsForIndexing($importResult);

        $phaseStart = microtime(true);
        $this->batchCreateReportEntries();
        $this->logger->info('Phase 5: Report entries created', [
            'duration_sec'  => round(microtime(true) - $phaseStart, 2),
            'reports_count' => count($this->statementsForReports),
            'memory_mb'     => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $phaseStart = microtime(true);
        $this->bulkIndexSegments();
        $this->logger->info('Phase 6: Elasticsearch bulk indexing completed', [
            'duration_sec' => round(microtime(true) - $phaseStart, 2),
            'memory_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $this->dispatchStatementCreatedEvents($importResult);
    }

    /**
     * Collect segment IDs for bulk Elasticsearch indexing.
     */
    private function collectSegmentIdsForIndexing(SegmentExcelImportResult $importResult): void
    {
        $this->segmentIdsForIndexing = [];
        foreach ($importResult->getSegments() as $segment) {
            $segmentId = $segment->getId();
            if (null !== $segmentId) {
                $this->segmentIdsForIndexing[] = $segmentId;
            }
        }
        $this->logger->info('Segment IDs collected for bulk indexing', [
            'segments_count' => count($this->segmentIdsForIndexing),
        ]);
    }

    /**
     * Dispatch StatementCreatedViaExcelEvent for all statements.
     */
    private function dispatchStatementCreatedEvents(SegmentExcelImportResult $importResult): void
    {
        $phaseStart = microtime(true);
        foreach ($importResult->getStatements() as $statement) {
            $this->dispatcher->dispatch(
                new StatementCreatedViaExcelEvent($statement),
                StatementCreatedViaExcelEventInterface::class
            );
        }
        $this->logger->info('Phase 7: Events dispatched', [
            'duration_sec' => round(microtime(true) - $phaseStart, 2),
            'memory_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);
    }
}
