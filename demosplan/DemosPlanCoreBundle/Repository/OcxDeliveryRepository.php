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
use demosplan\DemosPlanCoreBundle\Entity\Ocx\OcxDelivery;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Types\OcxDeliveryStatus;

/**
 * @method OcxDelivery|null find($id, $lockMode = null, $lockVersion = null)
 *
 * @template-extends CoreRepository<OcxDelivery>
 */
class OcxDeliveryRepository extends CoreRepository
{
    public function findOneByIdempotencyKey(User $user, string $idempotencyKey): ?OcxDelivery
    {
        return $this->findOneBy(['user' => $user, 'idempotencyKey' => $idempotencyKey]);
    }

    /**
     * @return list<OcxDelivery>
     */
    public function findOpenCreatedBefore(DateTime $threshold): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.createdAt < :threshold')
            ->setParameter('status', OcxDeliveryStatus::OPEN)
            ->setParameter('threshold', $threshold)
            ->getQuery()
            ->getResult();
    }

    /**
     * Queued or processing deliveries completed before the first threshold whose progress was last
     * reported before the second.
     *
     * @return list<OcxDelivery>
     */
    public function findUnfinishedCompletedBefore(DateTime $completedBefore, DateTime $progressBefore): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.status IN (:statuses)')
            ->andWhere('d.completedAt < :completedBefore')
            ->andWhere('d.progressUpdatedAt IS NULL OR d.progressUpdatedAt < :progressBefore')
            ->setParameter('statuses', [OcxDeliveryStatus::QUEUED, OcxDeliveryStatus::PROCESSING])
            ->setParameter('completedBefore', $completedBefore)
            ->setParameter('progressBefore', $progressBefore)
            ->getQuery()
            ->getResult();
    }
}
