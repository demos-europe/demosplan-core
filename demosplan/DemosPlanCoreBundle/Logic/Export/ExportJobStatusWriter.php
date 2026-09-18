<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJobInterface;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Records the outcome of a background export job.
 *
 * A Doctrine exception during the export (deadlock, lost connection, constraint violation) closes the
 * EntityManager, so a plain flush would throw and leave the row in 'processing' - where the browser
 * polls it forever and the duplicate-suppression lookup keeps refusing to start the export again.
 * Falls back to raw DBAL, which stays usable on a closed EntityManager, mirroring
 * {@link \demosplan\DemosPlanCoreBundle\Logic\Import\ImportJobProcessor}.
 */
class ExportJobStatusWriter
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function persist(AsyncExportJobInterface $job): void
    {
        // Read the table name up front: the metadata factory needs no connection, so it is still
        // available if the EntityManager turns out to be closed below.
        $tableName = $this->entityManager->getClassMetadata($job::class)->getTableName();

        try {
            if ($this->entityManager->getConnection()->isTransactionActive()) {
                $this->entityManager->rollback();
            }
        } catch (Throwable $e) {
            $this->logger->warning('Could not roll back transaction before writing export job status', [
                'jobId'     => $job->getId(),
                'exception' => $e->getMessage(),
            ]);
        }

        if ($this->entityManager->isOpen()) {
            try {
                $this->entityManager->flush();

                return;
            } catch (Throwable $e) {
                $this->logger->warning('ORM flush of export job status failed, falling back to raw DBAL', [
                    'jobId'     => $job->getId(),
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        try {
            $this->entityManager->getConnection()->executeStatement(
                "UPDATE {$tableName} SET status = :status, error_message = :errorMessage, file_hash = :fileHash,"
                .' file_name = :fileName, modified_date = :modifiedDate WHERE id = :id',
                [
                    'status'       => $job->getStatus(),
                    'errorMessage' => $job->getErrorMessage(),
                    'fileHash'     => $job->getFileHash(),
                    'fileName'     => $job->getFileName(),
                    'modifiedDate' => $job->getModifiedDate()->format('Y-m-d H:i:s'),
                    'id'           => $job->getId(),
                ]
            );
        } catch (Throwable $e) {
            $this->logger->critical('Failed to write export job status via DBAL fallback', [
                'jobId'     => $job->getId(),
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
