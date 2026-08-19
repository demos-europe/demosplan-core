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

use DemosEurope\DemosplanAddon\Contracts\Events\ManualOriginalStatementCreatedEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Statement\ManualOriginalStatementCreatedEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\CsvStatementImporter;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\SegmentExcelImportResult;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Logic\Report\StatementReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use FOS\ElasticaBundle\Persister\ObjectPersisterInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;

/**
 * Imports statements from a CSV file: parses it, persists the resulting statements in batches,
 * creates the report entries and indexes the statements in Elasticsearch.
 *
 * Nothing is persisted when the file contains violations - the caller is expected to roll back the
 * surrounding transaction in that case, see {@link ImportJobProcessor}.
 */
readonly class CsvStatementImport
{
    /**
     * How many statements are persisted before the changes are written to the database.
     */
    private const BATCH_SIZE = 300;

    /**
     * Elasticsearch handles larger bulks efficiently, so it gets its own, bigger batch size.
     */
    private const ES_BULK_INDEX_BATCH_SIZE = 1000;

    public function __construct(
        private CsvStatementImporter $csvStatementImporter,
        private ElasticsearchIndexingToggleService $elasticsearchIndexingToggleService,
        private EntityManagerInterface $entityManager,
        private EventDispatcherPostInterface $eventDispatcher,
        private LoggerInterface $logger,
        private ObjectPersisterInterface $statementPersister,
        private ReportService $reportService,
        private StatementReportEntryFactory $statementReportEntryFactory,
        private StatementRepository $statementRepository,
        private StatementService $statementService,
    ) {
    }

    /**
     * @throws Exception
     */
    public function importFromFile(FileInfo $file): SegmentExcelImportResult
    {
        $startTime = microtime(true);
        $this->logger->info('=== CSV STATEMENT IMPORT START ===', [
            'file'      => $file->getFileName(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $fileInfo = new SplFileInfo($file->getAbsolutePath(), '', $file->getHash());
        $result = $this->csvStatementImporter->process($fileInfo, $file->getFileName());

        if ($result->hasErrors()) {
            $this->logger->warning('CSV statement import aborted due to violations', [
                'error_count'  => count($result->getErrors()),
                'duration_sec' => round(microtime(true) - $startTime, 2),
            ]);

            return $result;
        }

        $disabledListeners = $this->elasticsearchIndexingToggleService->disableAutoIndexing();

        try {
            $statementIds = $this->persistStatementsInBatches($result->getStatements());
            $this->bulkIndexStatements($statementIds);

            $this->logger->info('=== CSV STATEMENT IMPORT COMPLETE ===', [
                'statements'         => $result->getStatementCount(),
                'total_duration_sec' => round(microtime(true) - $startTime, 2),
                'peak_memory_mb'     => round(memory_get_peak_usage(true) / 1024 / 1024, 2),
            ]);

            return $result;
        } catch (Exception $exception) {
            $this->logger->error('CSV statement import failed', [
                'exception'    => $exception->getMessage(),
                'duration_sec' => round(microtime(true) - $startTime, 2),
            ]);

            throw $exception;
        } finally {
            $this->elasticsearchIndexingToggleService->reEnableAutoIndexing($disabledListeners);
        }
    }

    /**
     * Persists the statements in batches and returns their ids in import order.
     *
     * Report entries and events are handled per batch, so that no data derived from a statement is
     * accumulated across the whole import.
     *
     * @param array<int, Statement> $statements
     *
     * @return list<string>
     *
     * @throws Exception
     */
    private function persistStatementsInBatches(array $statements): array
    {
        $statementIds = [];
        $batch = [];
        $batchNumber = 0;
        $processed = 0;
        $total = count($statements);

        foreach ($statements as $statement) {
            $this->statementRepository->persistEntities([$statement]);
            $batch[] = $statement;
            ++$processed;

            if (count($batch) >= self::BATCH_SIZE) {
                ++$batchNumber;
                $statementIds = [...$statementIds, ...$this->processBatch($batch, $batchNumber, $processed, $total)];
                $batch = [];
            }
        }

        if ([] !== $batch) {
            ++$batchNumber;
            $statementIds = [...$statementIds, ...$this->processBatch($batch, $batchNumber, $processed, $total)];
        }

        return $statementIds;
    }

    /**
     * Flushes one batch, then creates its report entries and dispatches its events.
     *
     * @param array<int, Statement> $batch
     *
     * @return list<string>
     *
     * @throws Exception
     */
    private function processBatch(array $batch, int $batchNumber, int $processed, int $total): array
    {
        $batchStart = microtime(true);
        $this->entityManager->flush();

        $this->logger->info('Statement batch flushed', [
            'batch'              => $batchNumber,
            'processed'          => $processed,
            'total'              => $total,
            'flush_duration_sec' => round(microtime(true) - $batchStart, 2),
            'memory_mb'          => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        $this->createReportEntries($batch);

        $statementIds = [];
        foreach ($batch as $statement) {
            $statementId = $statement->getId();
            if (null !== $statementId) {
                $statementIds[] = $statementId;
            }

            // dispatched after the flush so addons can query the statement from the database
            $this->eventDispatcher->dispatch(
                new ManualOriginalStatementCreatedEvent($statement),
                ManualOriginalStatementCreatedEventInterface::class
            );
        }

        return $statementIds;
    }

    /**
     * A failing report entry must not fail the import, the statements themselves are already written.
     *
     * @param array<int, Statement> $batch
     */
    private function createReportEntries(array $batch): void
    {
        try {
            $reportEntries = array_map(
                fn (Statement $statement) => $this->statementReportEntryFactory->createStatementCreatedEntry(
                    $this->statementService->convertToLegacy($statement)
                ),
                $batch
            );

            $this->reportService->persistAndFlushReportEntries(...$reportEntries);
        } catch (Exception $exception) {
            $this->logger->error('Failed to create report entries, but import succeeded', [
                'exception' => $exception->getMessage(),
                'trace'     => $exception->getTraceAsString(),
            ]);
        }
    }

    /**
     * Indexes the imported statements in bulk, since the automatic indexing was switched off for the
     * duration of the import.
     *
     * @param list<string> $statementIds
     */
    private function bulkIndexStatements(array $statementIds): void
    {
        if ([] === $statementIds) {
            return;
        }

        try {
            $indexed = 0;
            foreach (array_chunk($statementIds, self::ES_BULK_INDEX_BATCH_SIZE) as $idBatch) {
                $statements = $this->statementRepository->findBy(['id' => $idBatch]);
                if ([] === $statements) {
                    continue;
                }

                $this->statementPersister->insertMany($statements);
                $indexed += count($statements);
            }

            $this->logger->info('Elasticsearch bulk indexing completed', [
                'total_indexed' => $indexed,
                'total'         => count($statementIds),
            ]);
        } catch (Exception $exception) {
            // the database changes are already committed, so a failed index must not fail the import
            $this->logger->error(
                'Failed to index statements in Elasticsearch after successful database import',
                [
                    'exception' => $exception->getMessage(),
                    'trace'     => $exception->getTraceAsString(),
                ]
            );
        }
    }
}
