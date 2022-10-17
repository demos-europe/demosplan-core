<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use demosplan\DemosPlanCoreBundle\ValueObject\APIPagination;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\MappingException;
use EDT\DqlQuerying\ObjectProviders\DoctrineOrmEntityProvider;
use EDT\DqlQuerying\PropertyAccessors\ProxyPropertyAccessor;
use EDT\DqlQuerying\Utilities\QueryGenerator;
use EDT\JsonApi\RequestHandling\EntityFetcherInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\FunctionInterface;
use EDT\Querying\Contracts\ObjectProviderInterface;
use EDT\Querying\Contracts\PaginationException;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\SortException;
use EDT\Querying\Contracts\SortMethodInterface;
use EDT\Querying\ObjectProviders\PrefilledObjectProvider;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\Types\IdentifiableTypeInterface;
use EDT\Wrapping\Contracts\Types\ReadableTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\Contracts\WrapperFactoryInterface;
use EDT\Wrapping\Utilities\GenericEntityFetcher;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use function is_array;
use Pagerfanta\Doctrine\ORM\QueryAdapter;

class EntityFetcher implements EntityFetcherInterface
{
    /**
     * @var EntityManager
     */
    private $managerRegistry;

    /**
     * @var WrapperFactoryInterface
     */
    private $wrapperFactory;

    /**
     * @var DqlConditionFactory
     */
    private $conditionFactory;

    /**
     * @var QueryGenerator
     */
    private $queryGenerator;

    /**
     * @var SchemaPathProcessor
     */
    private $schemaPathProcessor;

    public function __construct(ManagerRegistry $managerRegistry, SchemaPathProcessor $schemaPathProcessor)
    {
        $this->managerRegistry = $managerRegistry->getManager();
        $this->conditionFactory = new DqlConditionFactory();
        $this->wrapperFactory = new class() implements WrapperFactoryInterface {
            public function createWrapper(object $object, ReadableTypeInterface $type): object
            {
                return $object;
            }
        };
        $this->queryGenerator = new QueryGenerator($this->managerRegistry);
        $this->schemaPathProcessor = $schemaPathProcessor;
    }

    /**
     * Will return all entities matching the given condition with the specified sorting.
     *
     * For all properties accessed while filtering/sorting it is checked if:
     *
     * * the given type and the types in the property paths are {@link TypeInterface::isAvailable() available at all} and {@link ReadableTypeInterface readable}
     * * the property is available for {@link ReadableTypeInterface::getFilterableProperties() filtering}/{@link ReadableTypeInterface::getSortableProperties() sorting}
     *
     * @template O of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
     *
     * @param ReadableTypeInterface<O>           $type
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
    public function listEntities(ReadableTypeInterface $type, array $conditions, array $sortMethods = []): array
    {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $entityProvider = new DoctrineOrmEntityProvider(
            $type->getEntityClass(),
            $this->managerRegistry
        );
        $entityFetcher = $this->createGenericEntityFetcher($entityProvider);

        return $entityFetcher->listEntities($type, $conditions, ...$sortMethods);
    }

    /**
     * Unlike {@link EntityFetcher::listPaginatedEntitiesUnrestricted} this method accepts conditions and sort methods using the
     * schema of a resource type instead of the schema of the backing entity.
     *
     * It will automatically check access rights and apply aliases before creating a
     * {@link QueryBuilder} and using it to create the returned {@link DemosPlanPaginator}.
     *
     * @param array<int, FunctionInterface<bool>> $conditions
     * @param array<int, SortMethodInterface>     $sortMethods
     *
     * @throws MappingException
     * @throws PaginationException
     * @throws PathException
     */
    public function getEntityPaginator(ReadableTypeInterface $type, APIPagination $pagination, array $conditions, array $sortMethods = []): DemosPlanPaginator
    {
        if (!$type->isAvailable()) {
            throw AccessException::typeNotAvailable($type);
        }

        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $conditions = $this->schemaPathProcessor->mapConditions($type, ...$conditions);
        $sortMethods = $this->schemaPathProcessor->mapSortMethods($type, ...$sortMethods);
        $queryBuilder = $this->queryGenerator->generateQueryBuilder($type->getEntityClass(), $conditions, $sortMethods);

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
     * * the given type and the types in the property paths are {@link TypeInterface::isAvailable() available at all} and {@link ReadableTypeInterface readable}
     * * the property is available for {@link ReadableTypeInterface::getFilterableProperties() filtering}/{@link ReadableTypeInterface::getSortableProperties() sorting}
     *
     * @template O of object
     *
     * @param ReadableTypeInterface<O>           $type
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
    public function listPrefilteredEntities(ReadableTypeInterface $type, array $dataObjects, array $conditions = [], array $sortMethods = []): array
    {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $entityProvider = new PrefilledObjectProvider(new ProxyPropertyAccessor($this->managerRegistry), $dataObjects);
        $entityFetcher = $this->createGenericEntityFetcher($entityProvider);

        return $entityFetcher->listEntities($type, $conditions, ...$sortMethods);
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
        $entityProvider = new PrefilledObjectProvider(new ProxyPropertyAccessor($this->managerRegistry), $dataObjects);
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
        $entityProvider = new DoctrineOrmEntityProvider(
            $entityClass,
            $this->managerRegistry
        );

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
     * @param class-string<object>               $entityClass
     * @param array<int,FunctionInterface<bool>> $conditions  will be applied in an `AND` conjunction
     * @param array<int,SortMethodInterface>     $sortMethods will be applied in the given order
     */
    public function listPaginatedEntitiesUnrestricted(
        string $entityClass,
        array $conditions,
        int $page,
        int $pageSize,
        array $sortMethods = []
    ): DemosPlanPaginator {
        $entityProvider = new DoctrineOrmEntityProvider(
            $entityClass,
            $this->managerRegistry
        );

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
     * @param IdentifiableTypeInterface<O>&ReadableTypeInterface|<|O> $type
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
     * @param IdentifiableTypeInterface<O>&DeletableDqlResourceTypeInterface|<|O> $type
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
     * @param IdentifiableTypeInterface<O>&ReadableTypeInterface|<|O> $type
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
     * @param array<int, FunctionInterface<bool>> $conditions
     */
    public function getEntityCount(ReadableTypeInterface $type, array $conditions): int
    {
        $pagination = new APIPagination();
        $pagination->setSize(1);
        $pagination->setNumber(1);
        $pagination->lock();

        $paginator = $this->getEntityPaginator($type, $pagination, $conditions);

        return $paginator->getAdapter()->getNbResults();
    }

    public function getEntityByTypeIdentifier(IdentifiableTypeInterface $type, string $id): object
    {
        $entityProvider = new DoctrineOrmEntityProvider(
            $type->getEntityClass(),
            $this->managerRegistry
        );
        $entityFetcher = $this->createGenericEntityFetcher($entityProvider);

        try {
            return $entityFetcher->getEntityByIdentifier($type, $id);
        } catch (AccessException $e) {
            $typeName = $type::getName();
            throw new \demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException("Could not retrieve entity for type '$typeName' with ID '$id'.", 0, $e);
        }
    }

    /**
     * @param IdentifiableTypeInterface&ReadableTypeInterface $type
     * @param array<int,FunctionInterface<bool>>              $conditions  Always conjuncted as AND. Order does not matter
     * @param array<int,SortMethodInterface>                  $sortMethods Order matters. Lower positions imply
     *                                                                     higher priority. Ie. a second sort method
     *                                                                     will be applied to each subset individually
     *                                                                     that resulted from the first sort method.
     *                                                                     The array keys will be ignored.
     *
     * @return array<int, string> the identifiers of the entities, sorted by the given $sortMethods
     *
     * @throws AccessException thrown if the resource type denies the currently logged in user
     *                         the access to the resource type needed to fulfill the request
     */
    public function listEntityIdentifiers(
        ReadableTypeInterface $type,
        array $conditions,
        array $sortMethods
    ) {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        $entityIdProperty = $this->getEntityIdentifierProperty($type);

        $entityProvider = new DoctrineOrmPartialDTOProvider(
            $type->getEntityClass(),
            $this->managerRegistry,
            $entityIdProperty
        );

        $entityFetcher = $this->createGenericEntityFetcher($entityProvider);
        $partialDtos = $entityFetcher->listEntities($type, $conditions, ...$sortMethods);

        return array_map(static function (PartialDTO $dto) use ($entityIdProperty): string {
            return $dto->getProperty($entityIdProperty);
        }, $partialDtos);
    }

    /**
     * Check if the given object matches any of the given conditions.
     *
     * @param array<int, FunctionInterface<bool>> $conditions at least one condition must match for `true`
     *                                                        to be returned; must not be empty
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
        if (1 !== count($entityIdPath)) {
            throw new NotYetImplementedException('Usage of a property within a entity relationship as ID is not yet supported');
        }

        return array_pop($entityIdPath);
    }

    protected function createGenericEntityFetcher(ObjectProviderInterface $objectProvider): GenericEntityFetcher
    {
        return new GenericEntityFetcher(
            $objectProvider,
            $this->conditionFactory,
            $this->schemaPathProcessor,
            $this->wrapperFactory
        );
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
        $conditions[] = $this->conditionFactory->propertyHasValue($id, ...$type->getIdentifierPropertyPath());

        $entities = $this->listEntities($type, $conditions);

        if (1 !== count($entities)) {
            throw new InvalidArgumentException("No {$type::getName()} resource found with ID '$id' that matches the given conditions.");
        }

        return array_pop($entities);
    }
}
