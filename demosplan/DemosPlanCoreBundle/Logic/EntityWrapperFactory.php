<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\Types\TransferableTypeInterface;
use EDT\Wrapping\WrapperFactories\WrapperObject;

/**
 * Service to wrap entities into an object that prevents access to properties not allowed by the
 * corresponding {@link ResourceTypeInterface}.
 */
class EntityWrapperFactory
{
    public function createWrapper(object $object, TransferableTypeInterface $type): WrapperObject
    {
        return new TwigableWrapperObject($object, $type);
    }
}
