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
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DplanResourceType;
use demosplan\DemosPlanCoreBundle\Repository\FluentRepository;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Doctrine\ORM\EntityManager;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\Utilities\JoinFinder;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\JsonApi\RequestHandling\EntityFetcherInterface;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortException;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\ObjectProviders\PrefilledObjectProvider;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Sorter;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\Types\IdentifiableTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

class EntityFetcher implements EntityFetcherInterface
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
     * @deprecated use {@link DplanResourceType::listEntities()} instead
     */
    public function listEntities(TransferableTypeInterface $type, array $conditions, array $sortMethods = []): array
    {
        return $type->listEntities($conditions, $sortMethods);
    }

    /**
     * @deprecated use {@link DplanResourceType::getEntityPaginator()} instead
     */
    public function getEntityPaginator(
        TransferableTypeInterface $type,
        APIPagination $pagination,
        array $conditions,
        array $sortMethods = []
    ): DemosPlanPaginator {
        return $type->getEntityPaginator($pagination, $conditions, $sortMethods);
    }

    /**
     * @deprecated use {@link DplanResourceType::listPrefilteredEntities()} instead
     */
    public function listPrefilteredEntities(
        TransferableTypeInterface $type,
        array $dataObjects,
        array $conditions = [],
        array $sortMethods = []
    ): array {
        return $type->listPrefilteredEntities($dataObjects, $conditions, $sortMethods);
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
        $entityProvider = new PrefilledObjectProvider($this->conditionEvaluator, $this->sorter, $dataObjects);
        $entities = $entityProvider->getObjects($conditions, $sortMethods, $offset, $limit);

        return is_array($entities) ? $entities : iterator_to_array($entities);
    }

    /**
     * @deprecated use {@link FluentRepository::listEntities()} instead
     */
    public function listEntitiesUnrestricted(string $entityClass, array $conditions, array $sortMethods = [], int $offset = 0, int $limit = null): array
    {
        $entityProvider = $this->createOrmEntityProvider($entityClass);

        $entities = $entityProvider->getObjects($conditions, $sortMethods, $offset, $limit);

        return is_array($entities) ? $entities : iterator_to_array($entities);
    }

    /**
     * @deprecated use {@link FluentRepository::listPaginatedEntities()} instead
     */
    public function listPaginatedEntitiesUnrestricted(
        string $entityClass,
        array $conditions,
        int $page,
        int $pageSize,
        array $sortMethods = []
    ): DemosPlanPaginator {
        $entityProvider = $this->createOrmEntityProvider($entityClass);
        $queryBuilder = $entityProvider->generateQueryBuilder($conditions, $sortMethods);

        $queryAdapter = new QueryAdapter($queryBuilder);
        $paginator = new DemosPlanPaginator($queryAdapter);
        $paginator->setMaxPerPage($pageSize);
        $paginator->setCurrentPage($page);

        return $paginator;
    }

    /**
     * @template O of object
     *
     * @param IdentifiableTypeInterface<O>&TransferableTypeInterface<O> $type
     *
     * @return O
     *
     * @throws AccessException          thrown if the resource type denies the currently logged in user
     *                                  the access to the resource type needed to fulfill the request
     * @throws InvalidArgumentException thrown if no entity with the given ID and resource type was found
     *
     * @deprecated use {@link DplanResourceType::getEntityAsReadTarget()} instead
     */
    public function getEntityAsReadTarget(IdentifiableTypeInterface $type, string $id): object
    {
        return $type->getEntityAsReadTarget($id);
    }

    /**
     * @template O of object
     *
     * @param IdentifiableTypeInterface<O>&DeletableDqlResourceTypeInterface<O> $type
     *
     * @return O
     *
     * @throws AccessException          thrown if the resource type denies the currently logged in user
     *                                  the access to the resource type needed to fulfill the request
     * @throws InvalidArgumentException thrown if no entity with the given ID and resource type was found
     *
     * @deprecated use {@link DplanResourceType::getEntityByTypeIdentifier()} instead
     */
    public function getEntityAsDeletionTarget(IdentifiableTypeInterface $type, string $id): object
    {
        return $type->getEntityByTypeIdentifier($id);
    }

    /**
     * @template O of object
     *
     * @param IdentifiableTypeInterface<O>&TransferableTypeInterface<O> $type
     *
     * @return O
     *
     * @throws AccessException          thrown if the resource type denies the currently logged in user
     *                                  the access to the resource type needed to fulfill the request
     * @throws InvalidArgumentException thrown if no entity with the given ID and resource type was found
     *
     * @deprecated use {@link DplanResourceType::getEntityByTypeIdentifier()} instead
     */
    public function getEntityAsUpdateTarget(IdentifiableTypeInterface $type, string $id): object
    {
        return $type->getEntityByTypeIdentifier($id);
    }

    /**
     * @deprecated use {@link DplanResourceType::getEntityCount()} instead
     */
    public function getEntityCount(TransferableTypeInterface $type, array $conditions): int
    {
        return $type->getEntityCount($conditions);
    }

    /**
     * @deprecated use {@link DplanResourceType::getEntityByTypeIdentifier()} instead
     */
    public function getEntityByTypeIdentifier(IdentifiableTypeInterface $type, string $id): object
    {
        return $type->getEntityByTypeIdentifier($id);
    }

    /**
     * @deprecated use {@link DplanResourceType::listEntityIdentifiers()} instead
     */
    public function listEntityIdentifiers(
        TransferableTypeInterface $type,
        array $conditions,
        array $sortMethods
    ) {
        return $type->listEntityIdentifiers($conditions, $sortMethods);
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
     * @param class-string $entityClass
     */
    private function createOrmEntityProvider(string $entityClass): DoctrineOrmEntityProvider
    {
        $builderPreparer = $this->createQueryBuilderPreparer($entityClass);

        return new DoctrineOrmEntityProvider($this->entityManager, $builderPreparer);
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
