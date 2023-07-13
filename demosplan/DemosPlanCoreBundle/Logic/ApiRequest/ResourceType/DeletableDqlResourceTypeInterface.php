<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceType;

use DemosEurope\DemosplanAddon\Logic\ResourceChange;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;

/**
 * @template T of object
 *
 * @template-extends ResourceTypeInterface<T>
 */
interface DeletableDqlResourceTypeInterface extends ResourceTypeInterface
{
    /**
     * @param T $entity
     *
     * @return ResourceChange<T>
     */
    public function delete(object $entity): ResourceChange;

    /**
     * @return list<non-empty-string> list of permission identifiers, each and all permissions must be enabled for the resource to be deletable
     */
    public function getRequiredDeletionPermissions(): array;
}
