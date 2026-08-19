<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Exception\ImportJobNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\SegmentExcelImportResult;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CsvStatementImport;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxSegmentImport;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Repository\ImportJobRepository;
use demosplan\DemosPlanCoreBundle\Types\ImportJobType;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ImportJobProcessor
{
    public function __construct(
        private readonly CsvStatementImport $csvStatementImport,
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserService $currentUserService,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ImportJobRepository $importJobRepository,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly TranslatorInterface $translator,
        private readonly XlsxSegmentImport $xlsxSegmentImport,
    ) {
    }

    /**
     * Process pending import jobs (called via ProcessImportJobsMessageHandler).
     * Returns number of jobs processed.
     */
    public function processPendingJobs(): int
    {
        $jobsProcessed = 0;

        // Begin transaction (required for PESSIMISTIC_WRITE lock)
        $this->entityManager->beginTransaction();

        try {
            // Find pending jobs (limit 1 to avoid concurrent processing issues)
            $pendingJobs = $this->importJobRepository->findPendingJobs(1);

            if ([] === $pendingJobs) {
                $this->entityManager->commit();

                return 0;
            }

            foreach ($pendingJobs as $job) {
                try {
                    $this->processJob($job);
                    ++$jobsProcessed;
                } catch (Exception $e) {
                    $this->handleJobProcessingFailure($job, $e);

                    // Return early - don't try to commit the rolled-back transaction
                    return $jobsProcessed;
                }
            }

            // Commit transaction after processing jobs. A job that found validation errors has already
            // rolled this transaction back and committed its own one to store the error, so there may
            // be nothing left to commit here.
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->commit();
            }
        } catch (Exception $e) {
            // Rollback transaction on error
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            throw $e;
        }

        return $jobsProcessed;
    }

    /**
     * Handle job processing failure by saving error status.
     *
     * After a Doctrine DBAL exception (e.g. UniqueConstraintViolationException),
     * the EntityManager is closed and cannot be used for ORM operations.
     * We first try ORM flush, then fall back to raw DBAL to prevent the job
     * from staying in 'pending' state and causing an infinite retry loop.
     */
    private function handleJobProcessingFailure(ImportJob $job, Exception $exception): void
    {
        // Rollback current transaction (it may be marked for rollback already)
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        $jobId = $job->getId();
        // The exception can be a raw DBAL/SQL failure (e.g. a unique-constraint violation) - the full
        // detail goes to the log via logJobFailure() below, never into the user-facing job record.
        $errorMessage = $this->translator->trans('error.import.job.unexpected');

        // Try ORM flush first (works for non-DBAL exceptions where EM is still open)
        if ($this->entityManager->isOpen()) {
            $this->entityManager->beginTransaction();
            try {
                $job->markAsFailed($errorMessage);
                $this->entityManager->flush();
                $this->entityManager->commit();
                $this->logJobFailure($jobId, $exception);

                return;
            } catch (Exception $flushException) {
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                }
                $this->logger->warning('ORM flush failed, falling back to raw DBAL', [
                    'jobId'     => $jobId,
                    'exception' => $flushException->getMessage(),
                ]);
            }
        }

        // Fallback: use raw DBAL connection (still usable when EntityManager is closed)
        try {
            $this->entityManager->getConnection()->executeStatement(
                'UPDATE import_job SET status = :status, error = :error, last_activity_at = :now WHERE id = :id',
                [
                    'status' => ImportJob::STATUS_FAILED,
                    'error'  => $errorMessage,
                    'now'    => (new DateTime())->format('Y-m-d H:i:s'),
                    'id'     => $jobId,
                ]
            );
        } catch (Exception $dbalException) {
            $this->logger->critical('Failed to save job failure status via DBAL fallback', [
                'jobId'     => $jobId,
                'exception' => $dbalException->getMessage(),
            ]);
        }

        $this->logJobFailure($jobId, $exception);
    }

    /**
     * Runs the importer the job asks for. Both importers return the same result object, so the
     * surrounding bookkeeping does not need to know which one ran.
     *
     * @throws Exception
     */
    private function runImport(ImportJob $job, FileInfo $file): SegmentExcelImportResult
    {
        return match ($job->getImportType()) {
            ImportJobType::STATEMENTS => $this->csvStatementImport->importFromFile($file),
            default                   => $this->xlsxSegmentImport->importFromFile($file),
        };
    }

    /**
     * A csv holds a single table, so naming a worksheet would only be confusing there. This depends on
     * the file's actual format, not on {@see ImportJob::getImportType()} - the two happen to line up
     * for every importer that exists today, but must not be conflated once a format gets a second
     * importer (e.g. a csv-based segment import).
     *
     * @param array<string, mixed> $error
     */
    private function describeError(ImportJob $job, array $error): string
    {
        $lineNumber = $error['lineNumber'] ?? '?';
        $message = $error['message'] ?? 'Unknown error';

        if ($this->isCsv($job)) {
            return sprintf("• Zeile %s: %s\n", $lineNumber, $message);
        }

        return sprintf(
            "• Arbeitsblatt \"%s\", Zeile %s: %s\n",
            $error['currentWorksheet'] ?? 'Unknown',
            $lineNumber,
            $message
        );
    }

    private function isCsv(ImportJob $job): bool
    {
        return 'csv' === mb_strtolower(pathinfo($job->getFileName(), PATHINFO_EXTENSION));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildJobResult(ImportJob $job, SegmentExcelImportResult $result): array
    {
        $jobResult = ['statements' => $result->getStatementCount()];

        if (ImportJobType::SEGMENTS === $job->getImportType()) {
            $jobResult['segments'] = $result->getSegmentCount();
        }

        // a completed job can still have skipped rows (e.g. a duplicate Eingangsnummer) - the user
        // needs to see that, or they would not know the file was not imported in full
        if ($result->hasWarnings()) {
            $jobResult['warnings'] = $result->getWarningsAsArray();
        }

        return $jobResult;
    }

    private function logJobFailure(string $jobId, Exception $exception): void
    {
        $this->logger->error('Import job failed with exception', [
            'jobId'     => $jobId,
            'exception' => $exception->getMessage(),
            'trace'     => $exception->getTraceAsString(),
        ]);
    }

    /**
     * Process a single import job.
     *
     * @throws Exception
     */
    private function processJob(ImportJob $job): void
    {
        $this->logger->info('Processing import job', [
            'jobId'    => $job->getId(),
            'fileName' => $job->getFileName(),
        ]);

        // Use the actual user who created the import job
        $user = $job->getUser();

        $customer = $job->getProcedure()->getCustomer();
        $this->globalConfig->setSubdomain($customer->getSubdomain());
        $this->currentUserService->setUser($user, $customer);

        // Restore organisation context if one was stored with the job
        $organisation = $job->getOrganisation();
        if ($organisation instanceof Orga) {
            $user->setCurrentOrganisation($organisation);
        }

        $this->permissions->setProcedure($job->getProcedure());
        $this->permissions->initPermissions($user);
        $this->permissions->setProcedurePermissions();
        $this->currentProcedureService->setProcedure($job->getProcedure());

        // Mark as processing
        $job->markAsProcessing();
        $this->entityManager->flush();

        // Retrieve file from S3/Flysystem using the stored file ID (ident)
        $fileIdent = $job->getFilePath();
        try {
            $fileInfo = $this->fileService->getFileInfo($fileIdent);
        } catch (Exception $e) {
            $job->markAsFailed($this->translator->trans('error.import.job.file.retrieval.failed'));
            $this->entityManager->flush();
            $this->logger->error('Import job file retrieval failed', [
                'jobId'       => $job->getId(),
                'fileIdent'   => $fileIdent,
                'procedureId' => $job->getProcedure()->getId(),
                'error'       => $e->getMessage(),
            ]);

            return;
        }

        // Download file locally for processing
        $localPath = null;
        try {
            $localPath = $this->fileService->ensureLocalFile($fileInfo->getAbsolutePath(), $fileIdent);
        } catch (Exception $e) {
            $job->markAsFailed($this->translator->trans('error.import.job.file.download.failed'));
            $this->entityManager->flush();
            $this->logger->error('Import job file download failed', [
                'jobId'     => $job->getId(),
                'fileIdent' => $fileIdent,
                'error'     => $e->getMessage(),
            ]);

            return;
        }

        try {
            // Create FileInfo with local path for import processing
            $localFileInfo = new FileInfo(
                $fileInfo->getHash(),
                $fileInfo->getFileName(),
                $fileInfo->getFileSize(),
                $fileInfo->getContentType(),
                dirname($localPath),
                $localPath,
                $fileInfo->getProcedure()
            );

            // Execute import (reuse existing optimized code)
            $result = $this->runImport($job, $localFileInfo);

            if ($result->hasErrors()) {
                // Rollback transaction before saving error (prevents nested transaction issues)
                if ($this->entityManager->getConnection()->isTransactionActive()) {
                    $this->entityManager->rollback();
                }

                // Clear EntityManager to detach all rolled-back entities
                // Without this, Doctrine tries to persist orphaned StatementMeta records
                // that reference non-existent statements, causing foreign key violations
                $this->entityManager->clear();

                // Re-fetch the ImportJob entity after clear (it was detached)
                $job = $this->importJobRepository->find($job->getId());
                if (null === $job) {
                    throw ImportJobNotFoundException::create($job->getId());
                }

                // Start new transaction to save error status
                $this->entityManager->beginTransaction();

                $errors = $result->getErrorsAsArray();
                $errorCount = count($errors);

                $this->logger->error('Import job failed with validation errors', [
                    'jobId'      => $job->getId(),
                    'errorCount' => $errorCount,
                ]);

                // Create concise error summary for display (TEXT column has 65KB limit)
                $showErrors = 40;
                $shownCount = min($showErrors, $errorCount);
                $firstErrorsLabel = 1 === $shownCount
                    ? 'Erster Fehler:'
                    : sprintf('Erste %d Fehler:', $shownCount);
                $errorSummary = sprintf(
                    "Validierungsfehler in der Import-Datei: %d Fehler gefunden.\n\n%s\n\n",
                    $errorCount,
                    $firstErrorsLabel
                );

                // Add first errors to summary
                $firstErrors = array_slice($errors, 0, $showErrors);
                foreach ($firstErrors as $error) {
                    $errorSummary .= $this->describeError($job, $error);
                }

                if ($errorCount > $showErrors) {
                    $remainingCount = $errorCount - $showErrors;
                    $errorSummary .= 1 === $remainingCount
                        ? "\n... und ein weiterer Fehler"
                        : sprintf("\n... und %d weitere Fehler", $remainingCount);
                }

                // Store full error details in result field (JSON can handle large data)
                $job->setResult([
                    'validationErrors' => $errors,
                    'errorCount'       => $errorCount,
                ]);

                // Mark as failed with summary (prevents TEXT column overflow)
                $job->markAsFailed($errorSummary);
                $this->entityManager->flush();
                $this->entityManager->commit();  // Commit the error status to database

                // Return early - error has been saved, no further processing needed
                return;
            }

            // Mark as completed with results
            $job->markAsCompleted($this->buildJobResult($job, $result));
            $this->entityManager->flush();

            $this->logger->info('Import job completed', [
                'jobId'      => $job->getId(),
                'importType' => $job->getImportType()->value,
                'statements' => $result->getStatementCount(),
                'segments'   => $result->getSegmentCount(),
            ]);
        } finally {
            // Always cleanup the local temp file downloaded from S3
            if (null !== $localPath) {
                try {
                    $this->fileService->deleteLocalFile($localPath);
                } catch (Exception $e) {
                    $this->logger->warning('Failed to cleanup local temp file', [
                        'jobId'     => $job->getId(),
                        'localPath' => $localPath,
                        'error'     => $e->getMessage(),
                    ]);
                }
            }

            // Always cleanup the original file from S3/Flysystem storage
            try {
                $this->fileService->deleteFile($fileIdent);
                $this->logger->info('Cleaned up S3 file after import job', [
                    'jobId'     => $job->getId(),
                    'fileIdent' => $fileIdent,
                ]);
            } catch (Exception $e) {
                $this->logger->warning('Failed to cleanup S3 file', [
                    'jobId'     => $job->getId(),
                    'fileIdent' => $fileIdent,
                    'error'     => $e->getMessage(),
                ]);
            }
        }
    }
}
