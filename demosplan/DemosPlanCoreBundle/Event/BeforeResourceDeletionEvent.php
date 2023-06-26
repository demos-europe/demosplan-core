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

use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;

/**
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
 */
class BeforeResourceDeletionEvent extends DPlanEvent
{
    /**
     * @var O
     */
    private $entity;

    /**
     * @var ResourceTypeInterface<O>
     */
    private $resourceType;

    /**
     * @param O                        $entity
     * @param ResourceTypeInterface<O> $resourceType
     */
    public function __construct(object $entity, ResourceTypeInterface $resourceType)
    {
        $this->entity = $entity;
        $this->resourceType = $resourceType;
    }

    /**
     * @return O
     */
    public function getEntity(): object
    {
        return $this->entity;
    }

    /**
     * @return ResourceTypeInterface<O>
     */
    public function getResourceType(): ResourceTypeInterface
    {
        return $this->resourceType;
    }
}
