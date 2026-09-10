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

use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateUsage;
use Exception;

class BoilerplateUsageRepository extends CoreRepository
{
    /**
     * Records that the given boilerplate was inserted into the recommendation
     * of the given Statement or Segment. Idempotent: repeated insertions into the
     * same one keep the single existing usage entry.
     *
     * @throws Exception
     */
    public function addUsage(Boilerplate $boilerplate, StatementInterface|SegmentInterface $statementOrSegment): BoilerplateUsage
    {
        return $this->addUsages($boilerplate, [$statementOrSegment])[0];
    }

    /**
     * Records that the given boilerplate was inserted into the recommendations
     * of the given Statements/Segments. Idempotent per boilerplate/target pair and
     * per call: duplicate targets in the input yield a single usage entry.
     *
     * @param array<int, StatementInterface|SegmentInterface> $statementsOrSegments
     *
     * @return BoilerplateUsage[]
     *
     * @throws Exception
     */
    public function addUsages(Boilerplate $boilerplate, array $statementsOrSegments): array
    {
        $targetsById = [];
        foreach ($statementsOrSegments as $statementOrSegment) {
            $targetsById[$statementOrSegment->getId()] = $statementOrSegment;
        }
        if ([] === $targetsById) {
            return [];
        }

        $existingUsagesById = $this->findUsagesByStatementOrSegmentId($boilerplate, array_keys($targetsById));

        $usages = [];
        foreach ($targetsById as $id => $statementOrSegment) {
            if (isset($existingUsagesById[$id])) {
                $usages[] = $existingUsagesById[$id];
                continue;
            }

            $usage = new BoilerplateUsage($boilerplate, $statementOrSegment);
            $this->getEntityManager()->persist($usage);
            $usages[] = $usage;
        }
        $this->getEntityManager()->flush();

        return $usages;
    }

    /**
     * Existing usages of the given boilerplate for the given Statement/Segment IDs,
     * keyed by that ID. Fetched in a single query so bulk inserts do not trigger one
     * existence lookup per target.
     *
     * @param array<int, string> $ids
     *
     * @return array<string, BoilerplateUsage>
     *
     * @throws Exception
     */
    private function findUsagesByStatementOrSegmentId(Boilerplate $boilerplate, array $ids): array
    {
        $existingUsages = $this->createQueryBuilder('boilerplateUsage')
            ->join('boilerplateUsage.statementOrSegment', 'statementOrSegment')
            ->where('boilerplateUsage.boilerplate = :boilerplate')
            ->andWhere('statementOrSegment.id IN (:ids)')
            ->setParameter('boilerplate', $boilerplate)
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $usagesById = [];
        foreach ($existingUsages as $existingUsage) {
            $usagesById[$existingUsage->getStatementOrSegment()->getId()] = $existingUsage;
        }

        return $usagesById;
    }

    /**
     * All usages of any boilerplate in the given Statement's/Segment's recommendation,
     * keyed by boilerplate id. This is the "statement/segment -> boilerplates" direction
     * the editor's load path needs (DPLAN-18271); the other methods in this repository
     * only support the reverse "boilerplate -> statements/segments" direction (admin
     * usage display).
     *
     * @return array<string, BoilerplateUsage> keyed by boilerplate id
     */
    public function findUsagesForStatementOrSegment(StatementInterface|SegmentInterface $statementOrSegment): array
    {
        $usages = $this->createQueryBuilder('boilerplateUsage')
            ->where('boilerplateUsage.statementOrSegment = :statementOrSegment')
            ->setParameter('statementOrSegment', $statementOrSegment)
            ->getQuery()
            ->getResult();

        $usagesByBoilerplateId = [];
        foreach ($usages as $usage) {
            $usagesByBoilerplateId[$usage->getBoilerplate()->getId()] = $usage;
        }

        return $usagesByBoilerplateId;
    }

    /**
     * All usages of the given boilerplate whose Statement/Segment still exists,
     * ordered by its externId (the "M-ID").
     *
     * @return BoilerplateUsage[]
     *
     * @throws Exception
     */
    public function getUsagesForBoilerplate(string $boilerplateId): array
    {
        return $this->createQueryBuilder('boilerplateUsage')
            ->join('boilerplateUsage.statementOrSegment', 'statementOrSegment')
            ->where('boilerplateUsage.boilerplate = :boilerplateId')
            ->andWhere('statementOrSegment.deleted = false')
            ->setParameter('boilerplateId', $boilerplateId)
            ->orderBy('statementOrSegment.externId', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
