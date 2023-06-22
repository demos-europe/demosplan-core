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
     * @param T                        $entity
     * @param ResourceTypeInterface<T> $resourceType
     * @param array<string, mixed>     $properties
     */
    public function __construct(private readonly object $entity, private readonly ResourceTypeInterface $resourceType, private readonly array $properties)
    {
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
