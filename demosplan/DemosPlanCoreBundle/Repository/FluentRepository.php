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

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\Querying\Pagination\PagePagination;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Pagerfanta\Pagerfanta;

/**
 * @template T of object
 *
 * @template-extends CoreRepository<T>
 */
abstract class FluentRepository extends CoreRepository
{
    /**
     * Will provide access to all entities matching the given condition via a paginator.
     * The entities will be sorted by the specified sorting.
     *
     * Unlike {@link DplanResourceType::listEntities} this method won't apply any restrictions
     * beside the provided conditions.
     *
     * @param array<int,ClauseFunctionInterface<bool>> $conditions  will be applied in an `AND` conjunction
     * @param array<int,OrderBySortMethodInterface>    $sortMethods will be applied in the given order
     *
     * @return DemosPlanPaginator&Pagerfanta<T>
     */
    public function getEntitiesForPage(
        array $conditions,
        array $sortMethods,
        PagePagination $pagination
    ): DemosPlanPaginator {
        $queryBuilder = $this->objectProvider->generateQueryBuilder($conditions, $sortMethods);

        $queryAdapter = new QueryAdapter($queryBuilder);
        $paginator = new DemosPlanPaginator($queryAdapter);
        $paginator->setMaxPerPage($pagination->getSize());
        $paginator->setCurrentPage($pagination->getNumber());

        return $paginator;
    }
}
