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

use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use Doctrine\ORM\EntityManager;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

/**
 * @template T
 *
 * @template-extends DoctrineOrmEntityProvider<T>
 */
class SinglePageEntityProvider extends DoctrineOrmEntityProvider
{
    public function __construct(string $className, EntityManager $entityManager, private readonly int $page, private readonly int $pageSize)
    {
        parent::__construct($className, $entityManager);
    }

    public function getObjects(array $conditions, array $sortMethods = [], int $offset = 0, int $limit = null): iterable
    {
        $queryBuilder = $this->generateQueryBuilder($conditions, $sortMethods, $offset, $limit);

        $queryAdapter = new QueryAdapter($queryBuilder);
        $paginator = new DemosPlanPaginator($queryAdapter);
        $paginator->setMaxPerPage($this->pageSize);
        $paginator->setCurrentPage($this->page);

        return $paginator->getIterator();
    }
}
