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
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\Utilities\JoinFinder;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortException;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\ObjectProviders\PrefilledEntityProvider;
use EDT\Querying\Pagination\OffsetPagination;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Sorter;

use const PHP_INT_MAX;

class EntityFetcher
{
    private readonly JoinFinder $joinFinder;

    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly EntityManager $entityManager,
        private readonly Sorter $sorter
    ) {
        $this->joinFinder = new JoinFinder($this->entityManager->getMetadataFactory());
    }

    /**
     * @template O
     *
     * @param array<int,O>                       $dataObjects
     * @param array<int,FunctionInterface<bool>> $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>     $sortMethods Order matters. Lower positions imply
     *                                                        higher priority. Ie. a second sort method
     *                                                        will be applied to each subset individually
     *                                                        that resulted from the first sort method.
     *                                                        The array keys will be ignored.
     *
     * @return array<int, O>
     *
     * @throws PathException
     * @throws PaginationException
     * @throws SortException
     */
    public function listPrefilteredEntitiesUnrestricted(array $dataObjects, array $conditions, array $sortMethods = [], int $offset = 0, int $limit = null): array
    {
        $entityProvider = new PrefilledEntityProvider($this->conditionEvaluator, $this->sorter, $dataObjects);

        $pagination = null;
        if (0 !== $offset || null === $limit) {
            $limit = PHP_INT_MAX;
            $pagination = new OffsetPagination($offset, $limit);
        }

        $entities = $entityProvider->getEntities($conditions, $sortMethods, $pagination);

        return is_array($entities) ? $entities : iterator_to_array($entities);
    }

    /**
     * @deprecated use {@link FluentRepository::getEntities()} instead
     */
    public function listEntitiesUnrestricted(string $entityClass, array $conditions, array $sortMethods = [], int $offset = 0, int $limit = null): array
    {
        $entityProvider = $this->createOrmEntityProvider($entityClass);
        $entities = $entityProvider->getEntities($conditions, $sortMethods, new OffsetPagination($offset, $limit ?? PHP_INT_MAX));

        return is_array($entities) ? $entities : iterator_to_array($entities);
    }

    /**
     * Check if the given object matches any of the given conditions.
     *
     * @param array<int, ClauseFunctionInterface<bool>> $conditions at least one condition must match for `true`
     *                                                              to be returned; must not be empty
     */
    public function objectMatchesAny(object $object, array $conditions): bool
    {
        if ([] === $conditions) {
            throw new InvalidArgumentException('at least one condition must be given');
        }

        return $this->objectMatches($object, $this->conditionFactory->anyConditionApplies(...$conditions));
    }

    /**
     * Check if the given object matches the given condition.
     *
     * @param FunctionInterface<bool> $condition
     */
    public function objectMatches(object $object, FunctionInterface $condition): bool
    {
        return $this->objectMatchesAll($object, [$condition]);
    }

    /**
     * Check if the given object matches all of the given conditions.
     *
     * @param array<int, FunctionInterface<bool>> $conditions all conditions must match for `true`
     *                                                        to be returned; must not be empty
     */
    public function objectMatchesAll(object $object, array $conditions): bool
    {
        if ([] === $conditions) {
            throw new InvalidArgumentException('at least one condition must be given');
        }

        $matches = $this->listPrefilteredEntitiesUnrestricted([$object], $conditions);

        return 0 !== count($matches);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $entityClass
     *
     * @return DoctrineOrmEntityProvider<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, T>
     */
    private function createOrmEntityProvider(string $entityClass): DoctrineOrmEntityProvider
    {
        $builderPreparer = $this->createQueryBuilderPreparer($entityClass);

        return new DoctrineOrmEntityProvider($this->entityManager, $builderPreparer, $entityClass);
    }

    /**
     * @param class-string $entityClass
     */
    private function createQueryBuilderPreparer(string $entityClass): QueryBuilderPreparer
    {
        return new QueryBuilderPreparer(
            $entityClass,
            $this->entityManager->getMetadataFactory(),
            $this->joinFinder
        );
    }
}
