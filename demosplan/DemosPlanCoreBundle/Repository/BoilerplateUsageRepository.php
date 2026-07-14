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

class BoilerplateUsageRepository extends CoreRepository
{
    /**
     * Records that the given boilerplate was inserted into the recommendation
     * of the given segment. Idempotent: repeated insertions into the same
     * segment keep the single existing usage entry.
     */
    public function addUsage(Boilerplate $boilerplate, Segment $segment): BoilerplateUsage
    {
        $existingUsage = $this->findOneBy(['boilerplate' => $boilerplate, 'segment' => $segment]);
        if ($existingUsage instanceof BoilerplateUsage) {
            return $existingUsage;
        }

        $usage = new BoilerplateUsage($boilerplate, $segment);
        $this->getEntityManager()->persist($usage);
        $this->getEntityManager()->flush();

        return $usage;
    }

    /**
     * All usages of the given boilerplate whose segment still exists,
     * ordered by the segment externId (the "M-ID").
     *
     * @return BoilerplateUsage[]
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
