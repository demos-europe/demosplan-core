<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\ApiRequest\ApiPaginationInterface;
use DemosEurope\DemosplanAddon\Contracts\ResourceType\JsonApiResourceTypeInterface;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPaginator;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceTypes\CachingResourceType;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\PathBuilding\End;
use EDT\PathBuilding\PropertyAutoPathInterface;
use EDT\PathBuilding\PropertyAutoPathTrait;
use EDT\Querying\Contracts\PathsBasedInterface;
use EDT\Querying\Contracts\PropertyPathInterface;
use EDT\Wrapping\Contracts\Types\ExposableRelationshipTypeInterface;
use EDT\Wrapping\Properties\UpdatableRelationship;
use IteratorAggregate;

/**
 * @template T of object
 *
 * @template-extends CachingResourceType<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, T>
 * @template-extends JsonApiResourceTypeInterface<T>
 * @template-extends IteratorAggregate<int, non-empty-string>
 *
 * @property-read End $id
 */
abstract class DplanResourceType extends CachingResourceType implements IteratorAggregate, PropertyAutoPathInterface, ExposableRelationshipTypeInterface, JsonApiResourceTypeInterface
{
    use PropertyAutoPathTrait;
    use DplanResourceTypeTrait;

    /**
     * @param array<string, mixed> $parameters
     *
     * @throws MessageBagException
     */
    public function addCreationErrorMessage(array $parameters): void
    {
        $this->dplanResourceTypeService->addCreationErrorMessage($parameters);
    }

    public function getDefaultSortMethods(): array
    {
        return [];
    }

    public function getIdentifierPropertyPath(): array
    {
        return $this->id->getAsNames();
    }

    public function getInternalProperties(): array
    {
        return $this->dplanResourceTypeService->getInternalProperties(
            $this,
            $this->getAutoPathProperties()
        );
    }

    public function isExposedAsPrimaryResource(): bool
    {
        return $this->dplanResourceTypeService->isExposedAsPrimaryResource($this);
    }

    /**
     * @deprecated do not implement or call this method, it will be removed as soon as possible
     */
    public function isExposedAsRelationship(): bool
    {
        return $this->isAvailable() && $this->isReferencable();
    }

    /**
     * @deprecated Move the permission-checks from the overrides of this method to the
     *             {@link self::getProperties()} method of the referencing resource type instead.
     *             Afterward, return `true` in the override of this method.
     */
    abstract public function isReferencable(): bool;

    /**
     * Convert the given array to an array with different mapping.
     *
     * The returned array will map using
     *
     * * as key: the dot notation of the property path
     * * as value: the corresponding {@link ResourceTypeInterface::getName} return value in case of a
     * relationship or `null` in case of an attribute
     *
     * The behavior for multiple given property paths with the same dot notation is undefined.
     *
     * @return array<non-empty-string, UpdatableRelationship|null>
     */
    protected function toProperties(PropertyPathInterface ...$propertyPaths): array
    {
        return $this->dplanResourceTypeService->toProperties($propertyPaths);
    }

    public function listEntities(array $conditions, array $sortMethods = []): array
    {
        return $this->dplanResourceTypeService->listEntities($this, $conditions, $sortMethods);
    }

    public function getEntityPaginator(
        ApiPaginationInterface $pagination,
        array $conditions,
        array $sortMethods = []
    ): DemosPlanPaginator {
        return $this->dplanResourceTypeService->getEntityPaginator($this, $pagination, $conditions, $sortMethods);
    }

    public function listPrefilteredEntities(
        array $dataObjects,
        array $conditions = [],
        array $sortMethods = []
    ): array {
        return $this->dplanResourceTypeService->listPrefilteredEntities($this, $dataObjects, $conditions, $sortMethods);
    }

    public function getEntityAsReadTarget(string $id): object
    {
        return $this->dplanResourceTypeService->getEntityAsReadTarget($this, $id);
    }

    public function getEntityCount(array $conditions): int
    {
        return $this->dplanResourceTypeService->getEntityCount($this, $conditions);
    }

    public function getEntityByTypeIdentifier(string $id): object
    {
        return $this->dplanResourceTypeService->getEntityByTypeIdentifier($this, $id);
    }

    public function listEntityIdentifiers(
        array $conditions,
        array $sortMethods
    ): array {
        return $this->dplanResourceTypeService->listEntityIdentifiers($this, $conditions, $sortMethods);
    }

    protected function processProperties(array $properties): array
    {
        return $this->dplanResourceTypeService->processProperties($this, $properties);
    }

    protected function formatDate(?DateTime $date): ?string
    {
        return $this->dplanResourceTypeService->formatDate($date);
    }

    /**
     * @return list<ClauseFunctionInterface<bool>>
     */
    abstract protected function getAccessConditions(): array;

    /**
     * @deprecated use and implement {@link DplanResourceType::getAccessConditions()} instead
     */
    public function getAccessCondition(): PathsBasedInterface
    {
        return $this->dplanResourceTypeService->getAccessCondition($this->getAccessConditions());
    }
}
