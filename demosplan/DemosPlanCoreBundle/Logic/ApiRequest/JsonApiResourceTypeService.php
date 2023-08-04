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

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiPaginationInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeServiceInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Repository\FluentRepository;
use Doctrine\ORM\EntityManagerInterface;
use EDT\DqlQuerying\ConditionFactories\DqlConditionFactory;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Querying\Contracts\PathException;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Querying\ObjectProviders\PrefilledObjectProvider;
use EDT\Querying\Pagination\PagePagination;
use EDT\Querying\Utilities\ConditionEvaluator;
use EDT\Querying\Utilities\Iterables;
use EDT\Querying\Utilities\Sorter;
use EDT\Wrapping\Contracts\AccessException;
use EDT\Wrapping\Contracts\TypeProviderInterface;
use EDT\Wrapping\Contracts\Types\FilterableTypeInterface;
use EDT\Wrapping\Contracts\Types\SortableTypeInterface;
use EDT\Wrapping\Properties\UpdatableRelationship;
use EDT\Wrapping\Utilities\SchemaPathProcessor;
use Pagerfanta\Pagerfanta;
use Psr\EventDispatcher\EventDispatcherInterface;

use function in_array;
use function is_array;

/**
 * This class is intended as helper class for the {@link DplanResourceType} and {@link AddonResourceType} only to
 * reduce code duplication.
 *
 * Do not use this class or its method in any non-resource-type class.
 */
class JsonApiResourceTypeService implements JsonApiResourceTypeServiceInterface
{
    public function __construct(
        protected readonly EventDispatcherInterface $eventDispatcher,
        protected readonly TypeProviderInterface $typeProvider,
        protected readonly SchemaPathProcessor $schemaPathProcessor,
        protected readonly ConditionEvaluator $conditionEvaluator,
        protected readonly Sorter $sorter,
        protected readonly DqlConditionFactory $conditionFactory,
        protected readonly MessageBagInterface $messageBag,
        protected readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getInternalProperties(JsonApiResourceTypeInterface $type, array $autoPathProperties): array
    {
        $properties = array_map(static function (string $className): ?string {
            $classImplements = class_implements($className);
            if (is_array($classImplements) && in_array(ResourceTypeInterface::class, $classImplements, true)) {
                /* @var ResourceTypeInterface $className */
                return $className::getName();
            }

            return null;
        }, $autoPathProperties);

        $event = new GetInternalPropertiesEvent($properties, $type);
        $this->eventDispatcher->dispatch($event);

        return array_map(
            fn (?string $typeIdentifier): ?JsonApiResourceTypeInterface => null === $typeIdentifier
                ? null
                : $this->typeProvider->requestType($typeIdentifier)->getInstanceOrThrow(),
            $event->getProperties(),
        );
    }

    public function toProperties(array $propertyPaths): array
    {
        return collect($propertyPaths)
            ->mapWithKeys(static function (PropertyPathInterface $propertyPath): array {
                $key = $propertyPath->getAsNamesInDotNotation();
                $value = $propertyPath instanceof ResourceTypeInterface
                    ? new UpdatableRelationship([])
                    : null;

                return [$key => $value];
            })->all();
    }

    public function listEntities(JsonApiResourceTypeInterface $type, array $conditions, array $sortMethods): array
    {
        $this->assertDirectlyAvailable($type);

        $conditions = $this->mapConditions($type, $conditions);
        $sortMethods = $this->mapSortMethods($type, $sortMethods);

        return $this->getRepository($type)->getEntities($conditions, $sortMethods);
    }

    public function getEntityPaginator(
        JsonApiResourceTypeInterface $type,
        ApiPaginationInterface $pagination,
        array $conditions,
        array $sortMethods
    ): Pagerfanta {
        $this->assertDirectlyAvailable($type);

        $conditions = $this->mapConditions($type, $conditions);
        $sortMethods = $this->mapSortMethods($type, $sortMethods);
        $pagePagination = new PagePagination($pagination->getSize(), $pagination->getNumber());

        return $this->getRepository($type)->getEntitiesForPage($conditions, $sortMethods, $pagePagination);
    }

    public function listPrefilteredEntities(JsonApiResourceTypeInterface $type, array $dataObjects, array $conditions, array $sortMethods): array
    {
        $this->assertDirectlyAvailable($type);

        $conditions = $this->mapConditions($type, $conditions);
        $sortMethods = $this->mapSortMethods($type, $sortMethods);

        $entityProvider = new PrefilledObjectProvider($this->conditionEvaluator, $this->sorter, $dataObjects);
        $entities = $entityProvider->getEntities($conditions, $sortMethods, null);
        $entities = Iterables::asArray($entities);

        return array_values($entities);
    }

    public function getEntityCount(JsonApiResourceTypeInterface $type, array $conditions): int
    {
        $this->assertDirectlyAvailable($type);

        $conditions = $this->mapConditions($type, $conditions);

        return $this->getRepository($type)->getEntityCount($conditions);
    }

    public function getEntityByTypeIdentifier(JsonApiResourceTypeInterface $type, string $id): object
    {
        if (!$type->isAvailable()) {
            throw AccessException::typeNotAvailable($type);
        }

        try {
            return $this->getRepository($type)->getEntityByIdentifier($id, [], $type->getIdentifierPropertyPath());
        } catch (AccessException $e) {
            $typeName = $type::getName();
            throw new InvalidArgumentException("Could not retrieve entity for type '$typeName' with ID '$id'.", 0, $e);
        }
    }

    public function listEntityIdentifiers(JsonApiResourceTypeInterface $type, array $conditions, array $sortMethods): array
    {
        $this->assertDirectlyAvailable($type);

        $conditions = $this->mapConditions($type, $conditions);
        $sortMethods = $this->mapSortMethods($type, $sortMethods);
        $entityIdentifierProperty = $this->getEntityIdentifierProperty($type);

        return $this->getRepository($type)->getEntityIdentifiers($conditions, $sortMethods, $entityIdentifierProperty);
    }

    public function getAccessCondition(array $accessConditions): PathsBasedInterface
    {
        if ([] === $accessConditions) {
            return $this->conditionFactory->true();
        }

        return $this->conditionFactory->allConditionsApply(...$accessConditions);
    }

    public function formatDate(?DateTime $date): ?string
    {
        if (null === $date) {
            return null;
        }

        return Carbon::instance($date)->toIso8601String();
    }

    public function processProperties(JsonApiResourceTypeInterface $type, array $properties): array
    {
        $event = new GetPropertiesEvent($type, $properties);
        $this->eventDispatcher->dispatch($event, GetPropertiesEventInterface::class);

        return $event->getProperties();
    }

    public function getEntityAsReadTarget(JsonApiResourceTypeInterface $type, string $id): object
    {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        return $this->getEntityByTypeIdentifier($type, $id);
    }

    public function isExposedAsPrimaryResource(JsonApiResourceTypeInterface $type): bool
    {
        return $type->isAvailable() && $type->isDirectlyAccessible();
    }

    /**
     * @throws MessageBagException
     */
    public function addCreationErrorMessage(array $parameters): void
    {
        $this->messageBag->add('error', 'generic.error');
    }

    /**
     * @throws AccessException
     */
    protected function assertDirectlyAvailable(JsonApiResourceTypeInterface $type): void
    {
        if (!$type->isDirectlyAccessible()) {
            throw AccessException::typeNotDirectlyAccessible($type);
        }

        if (!$type->isAvailable()) {
            throw AccessException::typeNotAvailable($type);
        }
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
    protected function getEntityIdentifierProperty(JsonApiResourceTypeInterface $type): string
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

    protected function getRepository(JsonApiResourceTypeInterface $type): FluentRepository
    {
        $repository = $this->entityManager->getRepository($type->getEntityClass());
        if (!$repository instanceof FluentRepository) {
            $fluentRepositoryClass = FluentRepository::class;
            throw new InvalidArgumentException("No repository found extending `$fluentRepositoryClass` for entity `{$type->getEntityClass()}`.");
        }

        return $repository;
    }

    /**
     * @param list<ClauseFunctionInterface<bool>> $conditions
     *
     * @return list<ClauseFunctionInterface<bool>>
     *
     * @throws PathException
     */
    private function mapConditions(JsonApiResourceTypeInterface $type, array $conditions): array
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
    private function mapSortMethods(JsonApiResourceTypeInterface $type, array $sortMethods): array
    {
        if ([] !== $sortMethods && $type instanceof SortableTypeInterface) {
            $this->schemaPathProcessor->mapSorting($type, $sortMethods);
        }
        $defaultSortMethods = $this->schemaPathProcessor->processDefaultSortMethods($type);

        return [...$sortMethods, ...$defaultSortMethods];
    }
}
