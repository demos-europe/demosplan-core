<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\DBAL\LockMode;

/**
 * @method ImportJob|null find($id, $lockMode = null, $lockVersion = null)
 * @method ImportJob|null findOneBy(array $criteria, array $orderBy = null)
 * @method ImportJob[]    findAll()
 * @method ImportJob[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 *
 * @template-extends CoreRepository<ImportJob>
 */
class ImportJobRepository extends CoreRepository
{
    /**
     * Find pending jobs that need to be processed with pessimistic locking.
     * This prevents race conditions if multiple MaintenanceCommand instances run.
     *
     * @return ImportJob[]
     */
    public function findPendingJobs(int $limit = 1): array
    {
        return $this->createQueryBuilder('ij')
            ->where('ij.status = :status')
            ->setParameter('status', ImportJob::STATUS_PENDING)
            ->orderBy('ij.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            ->getResult();
    }

    /**
     * Find recent jobs for a specific procedure and user (last 20 only).
     * No pagination needed as import jobs are created infrequently.
     *
     * @return ImportJob[]
     */
    public function findJobsForProcedure(Procedure $procedure, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('ij')
            ->where('ij.procedure = :procedure')
            ->setParameter('procedure', $procedure)
            ->orderBy('ij.createdAt', 'DESC')
            ->setMaxResults(20);

        if (null !== $user) {
            $qb->andWhere('ij.user = :user')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find currently processing jobs (to prevent concurrent processing).
     *
     * @return ImportJob[]
     */
    public function findProcessingJobs(): array
    {
        return $this->createQueryBuilder('ij')
            ->where('ij.status = :status')
            ->setParameter('status', ImportJob::STATUS_PROCESSING)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clean up old completed jobs.
     */
    public function cleanupOldJobs(DateTime $before): int
    {
        return $this->createQueryBuilder('ij')
            ->delete()
            ->where('ij.status IN (:statuses)')
            ->andWhere('ij.lastActivityAt < :before')
            ->setParameter('statuses', [ImportJob::STATUS_COMPLETED, ImportJob::STATUS_FAILED])
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}
