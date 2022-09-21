<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use demosplan\DemosPlanCoreBundle\Logic\ResourceChange;
use Doctrine\Common\Collections\Collection;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\Types\UpdatableTypeInterface;

/**
 * @template T of object
 *
 * @template-extends ResourceTypeInterface<T>
 * @template-extends UpdatableTypeInterface<T>
 */
interface UpdatableDqlResourceTypeInterface extends ResourceTypeInterface, UpdatableTypeInterface
{
    /**
     * Update an object of the type specified in {@link ResourceTypeInterface::getEntityClass}.
     *
     * When called via the generic JSON:API, attributes are present in the `$properties` parameter
     * as they were received from the client request. However, the relationship references received
     * in the request will have been automatically resolved and the actually referenced entity
     * will be present in the `$properties` array as either object for to-one relationships or
     * {@link Collection} for to-many relationships.
     *
     * When called via the generic JSON:API it was already ensured that only property names are present
     * in the `$properties` array that were returned by {@link UpdatableTypeInterface::getUpdatableProperties()}.
     *
     * Implementations are responsible for the validity of the resulting object state.
     *
     * @param T                   $object     the object to update with the given property values
     * @param array<string,mixed> $properties The values to set in the given object. The key
     *                                        must be the property name. to-many relationships must
     *                                        be given as {@link Collection} and handled in the
     *                                        method's implementation as such.
     *
     * @return ResourceChange<T> contains the updated object after it was processed
     *                           by the resource type and subscribers
     */
    public function updateObject(object $object, array $properties): ResourceChange;
}
