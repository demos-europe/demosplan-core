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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\ClauseInterface;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\Contracts\OrderByInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\Pagination\OffsetPagination;

/**
 * Instances of this class will load specific properties only and wrap them in a {@link PartialDTO}.
 *
 * @template-extends DoctrineOrmEntityProvider<PartialDto>
 */
class DoctrineOrmPartialDTOProvider extends DoctrineOrmEntityProvider
{
    /**
     * @var array<int, string>
     */
    private $properties;

    public function __construct(EntityManager $entityManager, QueryBuilderPreparer $builderPreparer, string $property, string ...$properties)
    {
        parent::__construct($entityManager, $builderPreparer);
        array_unshift($properties, $property);
        $this->properties = $properties;
    }

    /**
     * @param array<int,ClauseFunctionInterface<bool>>        $conditions
     * @param array<int,SortMethodInterface|OrderByInterface> $sortMethods
     *
     * @return array<PartialDTO>
     *
     * @throws MappingException
     */
    public function getObjects(array $conditions, array $sortMethods = [], int $offset = 0, int $limit = null): iterable
    {
        $queryBuilder = $this->generateQueryBuilder($conditions, $sortMethods, $offset, $limit);
        $this->replaceSelect($queryBuilder);
        $result = $queryBuilder->getQuery()->getResult();

        return array_map(static fn(array $properties): PartialDTO => new PartialDTO($properties), $result);
    }

    /**
     * @param list<ClauseInterface>  $conditions
     * @param list<OrderByInterface> $sortMethods
     * @param OffsetPagination|null  $pagination
     *
     * @return iterable<PartialDTO>
     *
     * @throws MappingException
     * @throws PaginationException
     */
    public function getEntities(array $conditions, array $sortMethods, ?object $pagination): iterable
    {
        if (null === $pagination) {
            $offset = 0;
            $limit = null;
        } else {
            $offset = $pagination->getOffset();
            $limit = $pagination->getLimit();
        }

        $queryBuilder = $this->generateQueryBuilder($conditions, $sortMethods, $offset, $limit);
        $this->replaceSelect($queryBuilder);
        $result = $queryBuilder->getQuery()->getResult();

        return array_map(static fn(array $properties): PartialDTO => new PartialDTO($properties), $result);
    }

    /**
     * Replaces the `select` in the {@link QueryBuilder} with a `select` that only loads the
     * properties defined in {@link DoctrineOrmPartialDTOProvider::$properties}.
     *
     * @see https://www.doctrine-project.org/projects/doctrine-orm/en/2.9/reference/partial-objects.html#partial-objects
     */
    protected function replaceSelect(QueryBuilder $queryBuilder)
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

        // set the specific properties to load in the `select`
        $properties = array_map(static fn(string $property): string => "$tableAlias.$property", $this->properties);

        $queryBuilder->select(implode(',', $properties));
    }
}
