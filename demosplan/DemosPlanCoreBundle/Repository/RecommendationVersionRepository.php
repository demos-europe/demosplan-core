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

use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;

/**
 * @template-extends CoreRepository<RecommendationVersion>
 */
class RecommendationVersionRepository extends CoreRepository
{
    /**
     * Returns the highest version number for the given statement, or 0 if none exist.
     */
    public function getLatestVersionNumber(string $statementId): int
    {
        $result = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('MAX(rv.versionNumber)')
            ->from(RecommendationVersion::class, 'rv')
            ->where('rv.statement = :statementId')
            ->setParameter('statementId', $statementId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * @return RecommendationVersion[]
     */
    public function findByStatementId(string $statementId): array
    {
        return $this->findBy(
            ['statement' => $statementId],
            ['versionNumber' => 'ASC']
        );
    }

    public function findVersion(string $statementId, int $versionNumber): ?RecommendationVersion
    {
        return $this->findOneBy([
            'statement'     => $statementId,
            'versionNumber' => $versionNumber,
        ]);
    }

    /**
     * Batch query returning the highest version number per statement.
     *
     * @param string[] $statementIds
     *
     * @return array<string, int> statementId => max version number
     */
    public function getVersionCountsForStatementIds(array $statementIds): array
    {
        if ([] === $statementIds) {
            return [];
        }

        $results = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('IDENTITY(rv.statement) AS statementId', 'MAX(rv.versionNumber) AS maxVersion')
            ->from(RecommendationVersion::class, 'rv')
            ->where('rv.statement IN (:ids)')
            ->setParameter('ids', $statementIds)
            ->groupBy('rv.statement')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($results as $row) {
            $map[$row['statementId']] = (int) $row['maxVersion'];
        }

        return $map;
    }
}
