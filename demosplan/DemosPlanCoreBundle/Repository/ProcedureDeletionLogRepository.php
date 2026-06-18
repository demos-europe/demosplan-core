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

use DateInterval;
use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureDeletionLog;

/**
 * @template-extends CoreRepository<ProcedureDeletionLog>
 */
class ProcedureDeletionLogRepository extends CoreRepository
{
    /**
     * Returns all log entries whose deletedAt is older than the given interval from now,
     * sorted oldest first.
     *
     * @return ProcedureDeletionLog[]
     */
    public function getAllOlderThan(DateInterval $interval): array
    {
        $threshold = (new DateTime())->sub($interval);

        return $this->getEntityManager()
            ->createQueryBuilder()
            ->select('log')
            ->from(ProcedureDeletionLog::class, 'log')
            ->where('log.deletedAt < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('log.deletedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
