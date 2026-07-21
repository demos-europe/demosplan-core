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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateUsage;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Exception;

class BoilerplateUsageRepository extends CoreRepository
{
    /**
     * Records that the given boilerplate was inserted into the recommendation
     * of the given segment. Idempotent: repeated insertions into the same
     * segment keep the single existing usage entry.
     *
     * @throws Exception
     */
    public function addUsage(Boilerplate $boilerplate, Segment $segment): BoilerplateUsage
    {
        return $this->addUsages($boilerplate, [$segment])[0];
    }

    /**
     * Records that the given boilerplate was inserted into the recommendations
     * of the given segments. Idempotent per boilerplate/segment pair and per
     * call: duplicate segments in the input yield a single usage entry.
     *
     * @param Segment[] $segments
     *
     * @return BoilerplateUsage[]
     *
     * @throws Exception
     */
    public function addUsages(Boilerplate $boilerplate, array $segments): array
    {
        $segmentsById = [];
        foreach ($segments as $segment) {
            $segmentsById[$segment->getId()] = $segment;
        }
        if ([] === $segmentsById) {
            return [];
        }

        $existingUsagesBySegmentId = $this->findUsagesBySegmentId($boilerplate, array_keys($segmentsById));

        $usages = [];
        foreach ($segmentsById as $segmentId => $segment) {
            if (isset($existingUsagesBySegmentId[$segmentId])) {
                $usages[] = $existingUsagesBySegmentId[$segmentId];
                continue;
            }

            $usage = new BoilerplateUsage($boilerplate, $segment);
            $this->getEntityManager()->persist($usage);
            $usages[] = $usage;
        }
        $this->getEntityManager()->flush();

        return $usages;
    }

    /**
     * Existing usages of the given boilerplate for the given segment IDs,
     * keyed by segment ID. Fetched in a single query so bulk inserts do not
     * trigger one existence lookup per segment.
     *
     * @param array<int, string> $segmentIds
     *
     * @return array<string, BoilerplateUsage>
     *
     * @throws Exception
     */
    private function findUsagesBySegmentId(Boilerplate $boilerplate, array $segmentIds): array
    {
        $existingUsages = $this->createQueryBuilder('boilerplateUsage')
            ->join('boilerplateUsage.segment', 'segment')
            ->where('boilerplateUsage.boilerplate = :boilerplate')
            ->andWhere('segment.id IN (:segmentIds)')
            ->setParameter('boilerplate', $boilerplate)
            ->setParameter('segmentIds', $segmentIds)
            ->getQuery()
            ->getResult();

        $usagesBySegmentId = [];
        foreach ($existingUsages as $existingUsage) {
            $usagesBySegmentId[$existingUsage->getSegment()->getId()] = $existingUsage;
        }

        return $usagesBySegmentId;
    }

    /**
     * All usages of the given boilerplate whose segment still exists,
     * ordered by the segment externId (the "M-ID").
     *
     * @return BoilerplateUsage[]
     *
     * @throws Exception
     */
    public function getUsagesForBoilerplate(string $boilerplateId): array
    {
        return $this->createQueryBuilder('boilerplateUsage')
            ->join('boilerplateUsage.segment', 'segment')
            ->where('boilerplateUsage.boilerplate = :boilerplateId')
            ->andWhere('segment.deleted = false')
            ->setParameter('boilerplateId', $boilerplateId)
            ->orderBy('segment.externId', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
