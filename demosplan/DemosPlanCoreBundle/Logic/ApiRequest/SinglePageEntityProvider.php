<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use ArrayIterator;
use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\Querying\Pagination\OffsetPagination;
use Pagerfanta\Doctrine\ORM\QueryAdapter;
use Webmozart\Assert\Assert;

/**
 * @template T of EntityInterface
 *
 * @template-extends DoctrineOrmEntityProvider<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, T>
 */
class SinglePageEntityProvider extends DoctrineOrmEntityProvider
{
    public function __construct(
        string $className,
        EntityManagerInterface $entityManager,
        QueryBuilderPreparer $builderPreparer,
        private readonly int $page,
        private readonly int $pageSize
    ) {
        parent::__construct($entityManager, $builderPreparer, $className);
    }

    public function getEntities(array $conditions, array $sortMethods, ?OffsetPagination $pagination): array
    {
        $queryBuilder = $this->generateQueryBuilder($conditions, $sortMethods, $pagination?->getOffset() ?? 0, $pagination?->getLimit());

        $queryAdapter = new QueryAdapter($queryBuilder);
        $paginator = new DemosPlanPaginator($queryAdapter);
        $paginator->setMaxPerPage($this->pageSize);
        $paginator->setCurrentPage($this->page);
        $iterator = $paginator->getIterator();
        Assert::isInstanceOf($iterator, ArrayIterator::class);

        return $iterator->getArrayCopy();
    }
}
