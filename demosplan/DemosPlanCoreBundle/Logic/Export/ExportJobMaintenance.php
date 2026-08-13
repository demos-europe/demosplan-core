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

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Export\AsyncExportJobInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureExportJob;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

/**
 * Keeps the export job tables and the files they point at from growing without bound, and stops jobs
 * from sitting in a non-final state forever.
 */
class ExportJobMaintenance
{
    /**
     * How long a finished export stays downloadable. Bounded because the artefact is a document full
     * of personal data, not just to save storage.
     */
    public const RESULT_RETENTION = '-7 days';

    /**
     * A job still unfinished after this long is not slow, it is abandoned - the worker was killed
     * mid-export, or none is running at all. Matches {@link RunningExportJobLookup::STALE_AFTER}, so
     * a job stops being handed back and gets closed out at the same point.
     */
    public const STALE_AFTER = RunningExportJobLookup::STALE_AFTER;

    private const STALE_REASON = 'error.export';

    /**
     * @var array<int, class-string<AsyncExportJobInterface>>
     */
    private const JOB_CLASSES = [
        AssessmentTableExportJob::class,
        ProcedureExportJob::class,
    ];

    /**
     * @var string[]
     */
    private const ACTIVE_STATUSES = [
        AsyncExportJobInterface::STATUS_PENDING,
        AsyncExportJobInterface::STATUS_PROCESSING,
    ];

    /**
     * @var string[]
     */
    private const FINAL_STATUSES = [
        AsyncExportJobInterface::STATUS_COMPLETED,
        AsyncExportJobInterface::STATUS_FAILED,
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FileService $fileService,
        private readonly LoggerInterface $logger,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Mark jobs that never reached a final status as failed, so the browser stops polling them and
     * the export can be started again.
     */
    public function failStaleJobs(): int
    {
        $failed = 0;
        foreach (self::JOB_CLASSES as $jobClass) {
            foreach ($this->findJobsBefore($jobClass, self::ACTIVE_STATUSES, self::STALE_AFTER) as $job) {
                $job->setStatus(AsyncExportJobInterface::STATUS_FAILED);
                $job->setErrorMessage($this->translator->trans(self::STALE_REASON));
                $job->setModifiedDate(new DateTime());
                ++$failed;
            }
        }
        $this->entityManager->flush();

        if (0 < $failed) {
            $this->logger->warning('Maintenance: marked abandoned export jobs as failed', ['count' => $failed]);
        }

        return $failed;
    }

    /**
     * Delete finished jobs past the retention window together with the exported file.
     */
    public function purgeExpiredResults(): int
    {
        $purged = 0;
        foreach (self::JOB_CLASSES as $jobClass) {
            foreach ($this->findJobsBefore($jobClass, self::FINAL_STATUSES, self::RESULT_RETENTION) as $job) {
                $fileHash = $job->getFileHash();
                if (null !== $fileHash) {
                    try {
                        $this->fileService->deleteFile($fileHash);
                    } catch (Throwable $e) {
                        // Keep going: an unreferenced file is cleaned up by removeOrphanedFiles(),
                        // whereas keeping the row would retry the same failure every run.
                        $this->logger->warning('Maintenance: could not delete expired export file', [
                            'jobId'     => $job->getId(),
                            'fileHash'  => $fileHash,
                            'exception' => $e->getMessage(),
                        ]);
                    }
                }
                $this->entityManager->remove($job);
                ++$purged;
            }
        }
        $this->entityManager->flush();

        if (0 < $purged) {
            $this->logger->info('Maintenance: purged expired export jobs', ['count' => $purged]);
        }

        return $purged;
    }

    /**
     * @param class-string<AsyncExportJobInterface> $jobClass
     * @param string[]                              $statuses
     *
     * @return AsyncExportJobInterface[]
     */
    private function findJobsBefore(string $jobClass, array $statuses, string $maxAge): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('job')
            ->from($jobClass, 'job')
            ->andWhere('job.status IN (:statuses)')
            ->andWhere('job.modifiedDate < :before')
            ->setParameter('statuses', $statuses)
            ->setParameter('before', new DateTime($maxAge))
            ->getQuery()
            ->getResult();
    }
}
