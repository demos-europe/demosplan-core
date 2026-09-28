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
use Doctrine\ORM\EntityManagerInterface;

/**
 * Finds the export job that is already running for a given request, so a repeated request joins it
 * instead of queueing a duplicate on the serial worker.
 *
 * Jobs past {@link STALE_AFTER} are deliberately ignored: a worker killed mid-export (OOM, deploy
 * restart) leaves its row in 'processing' forever, and without an age bound that row would keep being
 * handed back - the browser polling a job that will never finish, with no way to start the export
 * again. {@link ExportJobMaintenance::failStaleJobs()} marks those rows failed on the next
 * maintenance run; this bound makes the endpoint usable again immediately.
 */
class RunningExportJobLookup
{
    /**
     * Generous enough to cover the large exports this feature exists for, so a job that is merely
     * slow is never mistaken for a dead one.
     */
    public const STALE_AFTER = '-6 hours';

    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    /**
     * @param class-string<AsyncExportJobInterface> $jobClass
     * @param array<string, string>                 $criteria       field name => value, all matched with AND
     * @param string[]                              $activeStatuses statuses that count as still running
     */
    public function find(string $jobClass, array $criteria, array $activeStatuses): ?AsyncExportJobInterface
    {
        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('job')
            ->from($jobClass, 'job')
            ->andWhere('job.status IN (:activeStatuses)')
            ->andWhere('job.createdDate >= :notBefore')
            ->setParameter('activeStatuses', $activeStatuses)
            ->setParameter('notBefore', new DateTime(self::STALE_AFTER))
            ->orderBy('job.createdDate', 'DESC')
            ->setMaxResults(1);

        foreach ($criteria as $field => $value) {
            $queryBuilder
                ->andWhere(sprintf('job.%s = :%s', $field, $field))
                ->setParameter($field, $value);
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }
}
