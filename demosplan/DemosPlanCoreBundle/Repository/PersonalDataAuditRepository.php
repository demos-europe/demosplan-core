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
use demosplan\DemosPlanCoreBundle\Entity\PersonalDataAuditLog;

/**
 * @template-extends CoreRepository<PersonalDataAuditLog>
 */
class PersonalDataAuditRepository extends CoreRepository
{
    private const DEFAULT_LIMIT = 1000;

    /**
     * @return array<int, PersonalDataAuditLog>
     */
    public function getChangesByEntityId(string $entityId, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.entityId = :entityId')
            ->setParameter('entityId', $entityId)
            ->orderBy('a.created', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, PersonalDataAuditLog>
     */
    public function getChangesByUserId(string $userId, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('a.created', 'DESC')
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * @return array<int, PersonalDataAuditLog>
     */
    public function getChangesByEntityType(string $entityType, ?DateTime $from = null, ?DateTime $to = null, int $limit = self::DEFAULT_LIMIT): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.entityType = :entityType')
            ->setParameter('entityType', $entityType)
            ->orderBy('a.created', 'DESC')
            ->setMaxResults($limit);

        if (null !== $from) {
            $qb->andWhere('a.created >= :from')
                ->setParameter('from', $from);
        }

        if (null !== $to) {
            $qb->andWhere('a.created <= :to')
                ->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Anonymize userName for GDPR data removal.
     */
    public function anonymizeUserName(string $userId, string $anonymizedName): int
    {
        $qb = $this->createQueryBuilder('a')
            ->update()
            ->set('a.userName', ':anonymizedName')
            ->where('a.userId = :userId')
            ->setParameter('anonymizedName', $anonymizedName)
            ->setParameter('userId', $userId);

        return $qb->getQuery()->execute();
    }

    public function deleteByEntityId(string $entityId): int
    {
        $qb = $this->createQueryBuilder('a')
            ->delete()
            ->where('a.entityId = :entityId')
            ->setParameter('entityId', $entityId);

        return $qb->getQuery()->execute();
    }
}
