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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
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
        private readonly ImportJobRepository $importJobRepository,
        private readonly LoggerInterface $logger,
        private readonly PermissionsInterface $permissions,
        private readonly XlsxSegmentImport $xlsxSegmentImport,
    ) {
    }

    /**
     * Process pending import jobs (called from MaintenanceCommand).
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
                    $jobsProcessed++;
                } catch (Exception $e) {
                    // Rollback current transaction (it may be marked for rollback already)
                    if ($this->entityManager->getConnection()->isTransactionActive()) {
                        $this->entityManager->rollback();
                    }

                    // Start new transaction to save error status
                    $this->entityManager->beginTransaction();

                    try {
                        $job->markAsFailed($e->getMessage());
                        $this->entityManager->flush();
                        $this->entityManager->commit();
                    } catch (Exception $flushException) {
                        if ($this->entityManager->getConnection()->isTransactionActive()) {
                            $this->entityManager->rollback();
                        }
                        $this->logger->error('Failed to save job failure status', [
                            'jobId' => $job->getId(),
                            'exception' => $flushException->getMessage(),
                        ]);
                    }

                    $this->logger->error('Import job failed with exception', [
                        'jobId' => $job->getId(),
                        'exception' => $e->getMessage(),
                        'trace' => $e->getTraceAsString(),
                    ]);

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
     * Process a single import job.
     *
     * @throws Exception
     */
    private function processJob(ImportJob $job): void
    {
        $this->logger->info('Processing import job', [
            'jobId' => $job->getId(),
            'fileName' => $job->getFileName(),
        ]);

        // Use the actual user who created the import job
        $user = $job->getUser();
        if (null === $user) {
            throw new Exception('Import job has no associated user');
        }

        $customer = $this->entityManager->getRepository(Customer::class)->findOneBy(['subdomain' => 'sh']);
        $this->currentUserService->setUser($user, $customer);
        $this->permissions->setProcedure($job->getProcedure());
        $this->permissions->initPermissions($user);
        $this->permissions->setProcedurePermissions();
        $this->currentProcedureService->setProcedure($job->getProcedure());

        // Mark as processing
        $job->markAsProcessing();
        $this->entityManager->flush();

        // Create FileInfo from stored path
        $fileInfo = new FileInfo(
            $job->getId(),  // Use job ID as hash identifier
            $job->getFileName(),
            filesize($job->getFilePath()),
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            $job->getFilePath(),
            $job->getFilePath(),
            $job->getProcedure()
        );

        // Set progress callback (updates every 300 statements per batch)
        $this->xlsxSegmentImport->setProgressCallback(function($processed, $total) use ($job) {
            $job->updateProgress($processed, $total);

            // Flush every 3000 statements to reduce DB overhead by 90%
            if ($processed % 3000 === 0) {
                $this->entityManager->flush();
            }
        });

        // Execute import (reuse existing optimized code)
        $result = $this->xlsxSegmentImport->importFromFile($fileInfo);

        if ($result->hasErrors()) {
            $errors = $result->getErrorsAsArray();

            $this->logger->error('Import job failed with validation errors', [
                'jobId' => $job->getId(),
                'errors' => $errors,
            ]);

            // Format errors in human-readable format
            $errorMessages = [];
            foreach ($errors as $error) {
                $worksheet = $error['currentWorksheet'] ?? 'Unknown';
                $lineNumber = $error['lineNumber'] ?? '?';
                $message = $error['message'] ?? 'Unknown error';
                $errorMessages[] = "Arbeitsblatt \"{$worksheet}\", Zeile {$lineNumber}: {$message}";
            }
            $errorMessage = "Validierungsfehler in der Import-Datei:\n\n" . implode("\n", $errorMessages);

            // Throw exception to trigger proper error handling with transaction rollback
            throw new Exception($errorMessage);
        }

        // Mark as completed with results
        $job->markAsCompleted([
            'statements' => $result->getStatementCount(),
            'segments' => $result->getSegmentCount(),
        ]);
        $job->setTotalItems($result->getStatementCount());
        $job->setProcessedItems($result->getStatementCount());
        $this->entityManager->flush();

        // Cleanup uploaded file
        try {
            $this->fileService->deleteLocalFile($job->getFilePath());
        } catch (Exception $e) {
            $this->logger->warning('Failed to cleanup import file', [
                'jobId' => $job->getId(),
                'error' => $e->getMessage(),
            ]);
        }

        $this->logger->info('Import job completed', [
            'jobId' => $job->getId(),
            'statements' => $result->getStatementCount(),
            'segments' => $result->getSegmentCount(),
        ]);
    }
}
