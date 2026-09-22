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
use demosplan\DemosPlanCoreBundle\Entity\PersonalDataLogEntry;

/**
 * @template-extends CoreRepository<PersonalDataLogEntry>
 */
class PersonalDataLogEntryRepository extends CoreRepository
{
    private const DEFAULT_LIMIT = 1000;

    /**
     * @return array<int, PersonalDataLogEntry>
     */
    public function findByContentHash(string $hash, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.contentHash = :hash')
            ->setParameter('hash', $hash)
            ->orderBy('p.created', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, PersonalDataLogEntry>
     */
    public function findByProcedureId(string $procedureId, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.procedureId = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->orderBy('p.created', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, PersonalDataLogEntry>
     */
    public function findByOrgaId(string $orgaId, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.orgaId = :orgaId')
            ->setParameter('orgaId', $orgaId)
            ->orderBy('p.created', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, PersonalDataLogEntry>
     */
    public function findByRequestId(string $requestId): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.requestId = :requestId')
            ->setParameter('requestId', $requestId)
            ->orderBy('p.created', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, PersonalDataLogEntry>
     */
    public function findInRange(DateTime $from, DateTime $to, int $limit = self::DEFAULT_LIMIT): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.created BETWEEN :from AND :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->orderBy('p.created', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
