<?php

declare(strict_types=1);

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
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\UuidEntityInterface
 */
class AfterResourceDeletionEvent extends DPlanEvent
{
    /**
     * @var DeletableDqlResourceTypeInterface
     */
    private $resourceType;

    /**
     * @param DeletableDqlResourceTypeInterface<O> $resourceType
     */
    public function __construct(
        DeletableDqlResourceTypeInterface $resourceType
    ) {
        $this->resourceType = $resourceType;
    }

    /**
     * @return DeletableDqlResourceTypeInterface<O>
     */
    public function getResourceType(): DeletableDqlResourceTypeInterface
    {
        return $this->resourceType;
    }
}
