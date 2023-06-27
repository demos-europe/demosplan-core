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

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Doctrine\ORM\QueryBuilder;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\Querying\Contracts\ObjectProviderInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\FluentQueries\ConditionDefinition;
use EDT\Querying\FluentQueries\FluentQuery;
use EDT\Querying\FluentQueries\SliceDefinition;
use EDT\Querying\FluentQueries\SortDefinition;

/**
 * @template T of object
 *
 * @template-extends FluentQuery<T>
 */
class DqlFluentQuery extends FluentQuery
{
    /**
     * @var DoctrineOrmEntityProvider<T>
     */
    protected ObjectProviderInterface $objectProvider;

    /**
     * @param DoctrineOrmEntityProvider<T> $objectProvider
     */
    public function __construct(
        DoctrineOrmEntityProvider $objectProvider,
        ConditionDefinition $conditionDefinition,
        SortDefinition $sortDefinition,
        SliceDefinition $sliceDefinition
    ) {
        parent::__construct(
            $objectProvider,
            $conditionDefinition,
            $sortDefinition,
            $sliceDefinition
        );
        $this->objectProvider = $objectProvider;
    }

    /**
     * Get the count of rows found by the configuration of this query.
     *
     * Use the `$idAttributeName` parameter if your entity does not have an `id` attribute to
     * store the entity identifier but something like `ident` instead.
     *
     * @throws MappingException
     * @throws PaginationException
     */
    public function getCount(string $idAttributeName = 'id'): int
    {
        $queryBuilder = $this->generateEntitiesQueryBuilder();
        $this->replaceSelectWithCount($queryBuilder, $idAttributeName);

        return (int) $queryBuilder->getQuery()->getSingleScalarResult();
    }

    public function generateCountQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->generateEntitiesQueryBuilder();
        $this->replaceSelectWithCount($queryBuilder);

        return $queryBuilder;
    }

    public function generateEntitiesQueryBuilder(): QueryBuilder
    {
        return $this->objectProvider->generateQueryBuilder(
            $this->getConditionDefinition()->getConditions(),
            $this->getSortDefinition()->getSortMethods(),
            $this->getSliceDefinition()->getOffset(),
            $this->getSliceDefinition()->getLimit()
        );
    }

    protected function replaceSelectWithCount(QueryBuilder $queryBuilder, string $idAttributeName = 'id'): void
    {
        // extract the original `select`, because it contains the table alias
        $selects = $queryBuilder->getDQLPart('select');
        $selectsCount = is_countable($selects) ? count($selects) : 0;
        if (1 !== $selectsCount) {
            // we only expect a single `select`, otherwise something is wrong
            throw new InvalidArgumentException("Unexpected number of selects in query. Expected exactly one, got $selectsCount");
        }
        $tableAlias = array_pop($selects);

        // delete the previous `select`
        $queryBuilder->resetDQLPart('select');

        // set the count in the select
        $queryBuilder->select("count($tableAlias.$idAttributeName)");
    }
}
