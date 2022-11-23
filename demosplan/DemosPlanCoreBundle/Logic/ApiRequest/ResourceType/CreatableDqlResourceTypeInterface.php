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
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\Types\CreatableTypeInterface;

/**
 * @template T of object
 *
 * @template-extends ResourceTypeInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, T>
 * @template-extends CreatableTypeInterface<ClauseFunctionInterface<bool>>
 */
interface CreatableDqlResourceTypeInterface extends ResourceTypeInterface, CreatableTypeInterface
{
    /**
     * Create an object of the type specified in {@link ResourceTypeInterface::getEntityClass}
     * and set the fields as given in $properties.
     *
     * When called via the generic JSON:API, attributes are present in the `$properties` parameter
     * as they were received from the client request. However, the relationship references received
     * in the request will have been automatically resolved and the actually referenced entity
     * will be present in the `$properties` array as either object for to-one relationships or
     * {@link Collection} for to-many relationships.
     *
     * When called via the generic JSON:API implementation it was already ensured that only property names are present
     * in the `$properties` array that were marked as usable when creating instances of this
     * resource type. It was also ensured that properties marked as required are present in the
     * `$properties` array.
     *
     * Implementations are responsible for the validity of the resulting object state.
     *
     * @param array<string,mixed> $properties the values to set in the given object; the key
     *                                        must be the property name; to-many relationships must
     *                                        be given as {@link Collection} and handled in the
     *                                        method's implementation as such
     *
     * @return ResourceChange<T> contains the object created and all other entities that needs to be
     *                           persisted due to the creation
     */
    public function createObject(array $properties): ResourceChange;

    /**
     * Adds the Message when an error on creating a Resource Type occurs.
     *
     * @param array<string,mixed> $parameters
     */
    public function addCreationErrorMessage(array $parameters): void;
}
