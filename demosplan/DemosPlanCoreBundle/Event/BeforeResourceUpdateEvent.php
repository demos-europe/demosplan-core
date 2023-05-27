<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;

/**
 * The event that is used to notify listeners/subscribers before an update of an object of
 * a resource type.
 *
 * The event will be posted **before** the values in the given target entity were persisted.
 *
 * @template T of object
 */
class BeforeResourceUpdateEvent extends DPlanEvent
{
    /**
     * @var T
     */
    private $entity;

    /**
     * @var ResourceTypeInterface<T>
     */
    private $resourceType;

    /**
     * @var array<string, mixed>
     */
    private $properties;

    /**
     * @param T                        $entity
     * @param ResourceTypeInterface<T> $resourceType
     * @param array<string, mixed>     $properties
     */
    public function __construct(object $entity, ResourceTypeInterface $resourceType, array $properties)
    {
        $this->entity = $entity;
        $this->resourceType = $resourceType;
        $this->properties = $properties;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @return T
     */
    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return ResourceTypeInterface<T>
     */
    public function getResourceType(): ResourceTypeInterface
    {
        return $this->resourceType;
    }
}
