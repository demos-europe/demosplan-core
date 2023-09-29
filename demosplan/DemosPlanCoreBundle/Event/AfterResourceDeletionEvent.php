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

use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType\DeletableDqlResourceTypeInterface;

/**
 * Currently instances of this class provide the {@link DeletableDqlResourceTypeInterface} only.
 * Be careful if you want to provide the deleted entity instance. Subscribers may unintentionally
 * persist it or re-store it by other means. Alternative approaches would be to
 * provide the ID of the deleted entity only, a value object or an array containing the properties,
 * when/if any of those data is actually needed in the future.
 *
 * > After an entity has been removed its in-memory state is the same as before the removal, except for generated identifiers.
 *
 * ({@link https://www.doctrine-project.org/projects/doctrine-orm/en/latest/reference/working-with-objects.html#removing-entities removing-entities})
 *
 * @template O of \DemosEurope\DemosplanAddon\Contracts\Entities\UuidEntityInterface
 */
class AfterResourceDeletionEvent extends DPlanEvent
{
    /**
     * @param DeletableDqlResourceTypeInterface<O> $resourceType
     */
    public function __construct(private readonly DeletableDqlResourceTypeInterface $resourceType)
    {
    }

    /**
     * @return DeletableDqlResourceTypeInterface<O>
     */
    public function getResourceType(): DeletableDqlResourceTypeInterface
    {
        return $this->resourceType;
    }
}
