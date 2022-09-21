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
}
