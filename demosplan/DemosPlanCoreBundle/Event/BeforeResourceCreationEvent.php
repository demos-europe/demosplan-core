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
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
 */
class BeforeResourceCreationEvent extends DPlanEvent
{
    /**
     * @var CreatableDqlResourceTypeInterface<O>
     */
    private $resourceType;

    /**
     * @var array<string, mixed>
     */
    private $properties;

    /**
     * @param CreatableDqlResourceTypeInterface<O> $resourceType
     * @param array<string, mixed>                 $properties
     */
    public function __construct(
        CreatableDqlResourceTypeInterface $resourceType,
        array $properties
    ) {
        $this->resourceType = $resourceType;
        $this->properties = $properties;
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
