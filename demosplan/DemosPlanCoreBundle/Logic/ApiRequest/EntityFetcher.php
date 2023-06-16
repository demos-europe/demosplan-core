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

use DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\Utilities\JoinFinder;
use EDT\DqlQuerying\Utilities\QueryBuilderPreparer;
use EDT\JsonApi\RequestHandling\EntityFetcherInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortException;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\EntityProviders\EntityProviderInterface;
use EDT\Querying\ObjectProviders\PrefilledObjectProvider;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Iterables;
use EDT\Querying\Utilities\Sorter;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\Types\FilterableTypeInterface;
use EDT\Wrapping\Contracts\Types\IdentifiableTypeInterface;
use EDT\Wrapping\Contracts\Types\SortableTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

use function is_array;

class EntityFetcher implements EntityFetcherInterface
{
    private readonly JoinFinder $joinFinder;

    public function __construct(
        private readonly ConditionEvaluator $conditionEvaluator,
        private readonly DqlConditionFactory $conditionFactory,
        private readonly EntityManager $entityManager,
        private readonly SchemaPathProcessor $schemaPathProcessor,
        private readonly Sorter $sorter
    ) {
        $this->joinFinder = new JoinFinder($this->entityManager->getMetadataFactory());
    }

    /**
     * Will return all entities matching the given condition with the specified sorting.
     *
     * For all properties accessed while filtering/sorting it is checked if:
     *
     * * the given type and the types in the property paths are
     *  {@link TypeInterface::isAvailable() available at all} and
     *  {@link TransferableTypeInterface readable}
     * * the property is available for
     *  {@link FilterableTypeInterface::getFilterableProperties() filtering}/
     *  {@link SortableTypeInterface::getSortableProperties() sorting}
     *
     * @template O of \DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface
     *
     * @param TransferableTypeInterface<O>       $type
     * @param array<int,FunctionInterface<bool>> $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>     $sortMethods Order matters. Lower positions imply
     *                                                        higher priority. Ie. a second sort method
     *                                                        will be applied to each subset individually
     *                                                        that resulted from the first sort method.
     *                                                        The array keys will be ignored.
     *
     * @return array<int,O>
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listEntities(TransferableTypeInterface $type, array $conditions, array $sortMethods = []): array
    {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $entityProvider = $this->createOrmEntityProvider($type->getEntityClass());

        return $this->listTypeEntities($entityProvider, $type, $conditions, $sortMethods);
    }

    /**
     * Unlike {@link EntityFetcher::listPaginatedEntitiesUnrestricted} this method accepts conditions and sort methods using the
     * schema of a resource type instead of the schema of the backing entity.
     *
     * It will automatically check access rights and apply aliases before creating a
     * {@link QueryBuilder} and using it to create the returned {@link DemosPlanPaginator}.
     *
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     * @param array<int, OrderBySortMethodInterface>    $sortMethods
     *
     * @throws MappingException
     * @throws PaginationException
     * @throws PathException
     */
    public function getEntityPaginator(
        TransferableTypeInterface $type,
        APIPagination $pagination,
        array $conditions,
        array $sortMethods = []
    ): DemosPlanPaginator {
        if (!$type->isAvailable()) {
            throw AccessException::typeNotAvailable($type);
        }

        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $conditions = $this->mapConditions($type, $conditions);
        $sortMethods = $this->mapSortMethods($type, $sortMethods);

        $entityProvider = $this->createOrmEntityProvider($type->getEntityClass());
        $queryBuilder = $entityProvider->generateQueryBuilder($conditions, $sortMethods);

        $queryAdapter = new QueryAdapter($queryBuilder);
        $paginator = new DemosPlanPaginator($queryAdapter);
        $paginator->setMaxPerPage($pagination->getSize());
        $paginator->setCurrentPage($pagination->getNumber());

        return $paginator;
    }

    /**
     * Will return all entities matching the given condition with the specified sorting. The dataObjects array is the data source from which
     * matching entities will be returned (This is the only difference to the listEntities function above!).
     *
     * For all properties accessed while filtering/sorting it is checked if:
     *
     * * the given type and the types in the property paths are
     *   {@link TypeInterface::isAvailable() available at all} and
     *   {@link TransferableTypeInterface readable}
     * * the property is available for
     *   {@link FilterableTypeInterface::getFilterableProperties() filtering}/
     *   {@link SortableTypeInterface::getSortableProperties() sorting}
     *
     * @template O of object
     *
     * @param TransferableTypeInterface<O>       $type
     * @param array<int,O>                       $dataObjects
     * @param array<int,FunctionInterface<bool>> $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>     $sortMethods Order matters. Lower positions imply
     *                                                        higher priority. Ie. a second sort method
     *                                                        will be applied to each subset individually
     *                                                        that resulted from the first sort method.
     *                                                        The array keys will be ignored.
     *
     * @return array<int,O>
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listPrefilteredEntities(
        TransferableTypeInterface $type,
        array $dataObjects,
        array $conditions = [],
        array $sortMethods = []
    ): array {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $entityProvider = new PrefilledObjectProvider($this->conditionEvaluator, $this->sorter, $dataObjects);

        return $this->listTypeEntities($entityProvider, $type, $conditions, $sortMethods);
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
     * Will return all entities matching the given condition with the specified sorting.
     *
     * Unlike {@link EntityFetcher::listEntities} this method won't apply any restrictions
     * beside the provided conditions.
     *
     * The values in the returned array will be of the type of the given entity class.
     *
     * @template O of object
     *
     * @param class-string<O>                    $entityClass
     * @param array<int,FunctionInterface<bool>> $conditions  will be applied in an `AND` conjunction
     * @param array<int,SortMethodInterface>     $sortMethods will be applied in the given order
     *
     * @return array<int,O>
     */
    public function listEntitiesUnrestricted(string $entityClass, array $conditions, array $sortMethods = [], int $offset = 0, int $limit = null): array
    {
        $entityProvider = $this->createOrmEntityProvider($entityClass);

        $entities = $entityProvider->getObjects($conditions, $sortMethods, $offset, $limit);

        return is_array($entities) ? $entities : iterator_to_array($entities);
    }

    /**
     * Will provide access to all entities matching the given condition via a paginator.
     * The entities will be sorted by the specified sorting.
     *
     * Unlike {@link EntityFetcher::listEntities} this method won't apply any restrictions
     * beside the provided conditions.
     *
     * The type of the entities will be of the type of the given entity class.
     *
     * @param class-string<object>                     $entityClass
     * @param array<int,ClauseFunctionInterface<bool>> $conditions  will be applied in an `AND` conjunction
     * @param array<int,OrderBySortMethodInterface>    $sortMethods will be applied in the given order
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
     */
    public function getEntityAsReadTarget(IdentifiableTypeInterface $type, string $id): object
    {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        return $this->getEntityByTypeIdentifier($type, $id);
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
     */
    public function getEntityAsDeletionTarget(IdentifiableTypeInterface $type, string $id): object
    {
        return $this->getEntityByTypeIdentifier($type, $id);
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
     */
    public function getEntityAsUpdateTarget(IdentifiableTypeInterface $type, string $id): object
    {
        return $this->getEntityByTypeIdentifier($type, $id);
    }

    /**
     * @param array<int, ClauseFunctionInterface<bool>> $conditions
     */
    public function getEntityCount(TransferableTypeInterface $type, array $conditions): int
    {
        $pagination = new APIPagination();
        $pagination->setSize(1);
        $pagination->setNumber(1);
        $pagination->lock();

        $paginator = $this->getEntityPaginator($type, $pagination, $conditions, []);

        return $paginator->getAdapter()->getNbResults();
    }

    public function getEntityByTypeIdentifier(IdentifiableTypeInterface $type, string $id): object
    {
        $entityProvider = $this->createOrmEntityProvider($type->getEntityClass());

        try {
            $identifierPath = $type->getIdentifierPropertyPath();
            $identifierCondition = $this->conditionFactory->propertyHasValue($id, $identifierPath);
            $entities = $this->listTypeEntities($entityProvider, $type, [$identifierCondition], []);

            switch (count($entities)) {
                case 0:
                    throw AccessException::noEntityByIdentifier($type);
                case 1:
                    return array_pop($entities);
                default:
                    throw AccessException::multipleEntitiesByIdentifier($type);
            }
        } catch (AccessException $e) {
            $typeName = $type::getName();
            throw new InvalidArgumentException("Could not retrieve entity for type '$typeName' with ID '$id'.", 0, $e);
        }
    }

    /**
     * @param IdentifiableTypeInterface&TransferableTypeInterface $type
     * @param array<int,FunctionInterface<bool>>                  $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>                      $sortMethods Order matters. Lower positions imply
     *                                                                         higher priority. Ie. a second sort method
     *                                                                         will be applied to each subset individually
     *                                                                         that resulted from the first sort method.
     *                                                                         The array keys will be ignored.
     *
     * @return array<int, string> the identifiers of the entities, sorted by the given $sortMethods
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listEntityIdentifiers(
        TransferableTypeInterface $type,
        array $conditions,
        array $sortMethods
    ) {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $entityIdProperty = $this->getEntityIdentifierProperty($type);

        $entityProvider = new DoctrineOrmPartialDTOProvider(
            $this->entityManager,
            $this->createQueryBuilderPreparer($type->getEntityClass()),
            $entityIdProperty
        );

        $partialDtos = $this->listTypeEntities($entityProvider, $type, $conditions, $sortMethods);

        return array_map(static fn (PartialDTO $dto): string => $dto->getProperty($entityIdProperty), $partialDtos);
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
     * This method determines the property within an entity that should be used as ID, as
     * not all entities use `id` for this but some use `ident`.
     *
     * It can only get the identifier if it is not nested within a relationship, because
     * support for nested IDs was not added yet.
     *
     * You probably want to utilize {@link SchemaPathProcessor::processPropertyPath} when
     * you add support for paths (nested IDs).
     *
     * @throws NotYetImplementedException if the ID is either nested within a relationship of the resource/entity
     */
    protected function getEntityIdentifierProperty(IdentifiableTypeInterface $type): string
    {
        // get the resource identifier attribute
        $resourceIdPath = $type->getIdentifierPropertyPath();
        if (1 !== count($resourceIdPath)) {
            throw new NotYetImplementedException('Usage of a property within a resource relationship as ID is not yet supported');
        }

        // map the resource identifier attribute to an entity property
        $resourceIdProperty = array_pop($resourceIdPath);
        $entityIdPath = $type->getAliases()[$resourceIdProperty] ?? [$resourceIdProperty];
        if (1 !== (is_countable($entityIdPath) ? count($entityIdPath) : 0)) {
            throw new NotYetImplementedException('Usage of a property within a entity relationship as ID is not yet supported');
        }

        return array_pop($entityIdPath);
    }

    private function listTypeEntities(
        EntityProviderInterface $entityProvider,
        TypeInterface $type,
        array $conditions,
        array $sortMethods
    ): array {
        if (!$type->isAvailable()) {
            throw AccessException::typeNotAvailable($type);
        }

        $conditions = $this->mapConditions($type, $conditions);
        $sortMethods = $this->mapSortMethods($type, $sortMethods);

        // get the actual entities
        $entities = $entityProvider->getEntities($conditions, $sortMethods, null);

        // get and map the actual entities
        $entities = Iterables::asArray($entities);

        return array_values($entities);
    }

    /**
     * @template O of UuidEntityInterface
     *
     * @param ResourceTypeInterface<O>            $type
     * @param array<int, FunctionInterface<bool>> $conditions
     *
     * @return O
     *
     * @throws PathException
     */
    public function getUniqueEntity(ResourceTypeInterface $type, string $id, array $conditions): UuidEntityInterface
    {
        $conditions[] = $this->conditionFactory->propertyHasValue($id, $type->getIdentifierPropertyPath());

        $entities = $this->listEntities($type, $conditions);

        if (1 !== count($entities)) {
            throw new InvalidArgumentException("No {$type::getName()} resource found with ID '$id' that matches the given conditions.");
        }

        return array_pop($entities);
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

    /**
     * @param list<ClauseFunctionInterface<bool>> $conditions
     *
     * @return list<ClauseFunctionInterface<bool>>
     *
     * @throws PathException
     */
    private function mapConditions(TypeInterface $type, array $conditions): array
    {
        if ([] !== $conditions && $type instanceof FilterableTypeInterface) {
            $this->schemaPathProcessor->mapFilterConditions($type, $conditions);
        }
        $conditions[] = $this->schemaPathProcessor->processAccessCondition($type);

        return $conditions;
    }

    /**
     * @param list<OrderBySortMethodInterface> $sortMethods
     *
     * @return list<OrderBySortMethodInterface>
     *
     * @throws PathException
     */
    private function mapSortMethods(TypeInterface $type, array $sortMethods): array
    {
        if ([] !== $sortMethods && $type instanceof SortableTypeInterface) {
            $this->schemaPathProcessor->mapSorting($type, $sortMethods);
        }
        $defaultSortMethods = $this->schemaPathProcessor->processDefaultSortMethods($type);

        return [...$sortMethods, ...$defaultSortMethods];
    }
}
