<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository\Workflow;

use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\CoreRepository;
use Doctrine\ORM\NoResultException;

/**
 * @template-extends CoreRepository<Place>
 */
class PlaceRepository extends CoreRepository
{
    /**
     * @throws NoResultException
     */
    public function findWithCertainty(string $id): Place
    {
        $place = $this->find($id);
        if (null === $place) {
            throw new NoResultException();
        }

        return $place;
    }

    public function findFirstOrderedBySortIndex(string $procedureId): ?Place
    {
        return $this->findOneBy([
            'procedure' => $procedureId,
        ], [
            'sortIndex' => 'ASC',
        ]);
    }

    public function getMaxUsedIndex(string $procedureId): int
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        return (int) $qb->select($qb->expr()->max('place.sortIndex'))
            ->from(Place::class, 'place')
            ->where('place.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
