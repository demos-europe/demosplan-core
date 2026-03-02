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

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Exception\ImportJobNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\ImportJobUserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxSegmentImport;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Repository\ImportJobRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;

class ImportJobProcessor
{
    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly CurrentUserService $currentUserService,
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
        private readonly GlobalConfigInterface $globalConfig,
        private readonly ImportJobRepository $importJobRepository,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
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

            if (empty($pendingJobs)) {
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

            // Commit transaction after processing jobs
            $this->entityManager->commit();
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
     */
    private function handleJobProcessingFailure(ImportJob $job, Exception $exception): void
    {
        // Rollback current transaction (it may be marked for rollback already)
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        // Start new transaction to save error status
        $this->entityManager->beginTransaction();

        try {
            $job->markAsFailed($exception->getMessage());
            $this->entityManager->flush();
            $this->entityManager->commit();
        } catch (Exception $flushException) {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
            $this->logger->error('Failed to save job failure status', [
                'jobId'     => $job->getId(),
                'exception' => $flushException->getMessage(),
            ]);
        }

        $this->logger->error('Import job failed with exception', [
            'jobId'     => $job->getId(),
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
        if (null === $user) {
            throw ImportJobUserNotFoundException::create($job->getId());
        }

        $customer = $job->getProcedure()->getCustomer();
        $this->globalConfig->setSubdomain($customer->getSubdomain());
        $this->currentUserService->setUser($user, $customer);

        // Restore organisation context if one was stored with the job
        $organisation = $job->getOrganisation();
        if (null !== $organisation) {
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
            $job->markAsFailed('Failed to retrieve file from storage: '.$e->getMessage());
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
            $job->markAsFailed('Failed to download file locally: '.$e->getMessage());
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
            $result = $this->xlsxSegmentImport->importFromFile($localFileInfo);

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
                $errorSummary = sprintf(
                    "Validierungsfehler in der Import-Datei: %d Fehler gefunden.\n\nErste %d Fehler:\n\n",
                    $errorCount,
                    min($showErrors, $errorCount)
                );

                // Add first errors to summary
                $firstErrors = array_slice($errors, 0, $showErrors);
                foreach ($firstErrors as $error) {
                    $worksheet = $error['currentWorksheet'] ?? 'Unknown';
                    $lineNumber = $error['lineNumber'] ?? '?';
                    $message = $error['message'] ?? 'Unknown error';
                    $errorSummary .= sprintf("• Arbeitsblatt \"%s\", Zeile %s: %s\n", $worksheet, $lineNumber, $message);
                }

                if ($errorCount > $showErrors) {
                    $errorSummary .= sprintf("\n... und %d weitere Fehler", $errorCount - $showErrors
                    );
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
            $job->markAsCompleted([
                'statements' => $result->getStatementCount(),
                'segments'   => $result->getSegmentCount(),
            ]);
            $this->entityManager->flush();

            $this->logger->info('Import job completed', [
                'jobId'      => $job->getId(),
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
