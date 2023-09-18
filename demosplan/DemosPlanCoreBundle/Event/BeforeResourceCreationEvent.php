<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use DemosEurope\DemosplanAddon\Contracts\ResourceType\CreatableDqlResourceTypeInterface;

/**
 * @template O of \DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface
 */
class BeforeResourceCreationEvent extends DPlanEvent
{
    /**
     * @param CreatableDqlResourceTypeInterface<O> $resourceType
     * @param array<string, mixed>                 $properties
     */
    public function __construct(private readonly CreatableDqlResourceTypeInterface $resourceType, private readonly array $properties)
    {
    }

    /**
     * @return CreatableDqlResourceTypeInterface<O>
     */
    public function getResourceType(): CreatableDqlResourceTypeInterface
    {
        return $this->resourceType;
    }

    /**
     * @return array<string, mixed>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }
}
