<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceTypes\ResourceTypeInterface;
use EDT\Wrapping\Contracts\Types\TypeInterface;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;

/**
 * @template-extends PrefilledTypeProvider<ClauseFunctionInterface, OrderBySortMethodInterface>
 */
class PrefilledResourceTypeProvider extends PrefilledTypeProvider
{
    protected function getIdentifier(TypeInterface $type): string
    {
        if ($type instanceof ResourceTypeInterface) {
            return $type::getName();
        }

        throw new InvalidArgumentException('Expected ResourceTypeInterface, got '.$type::class);
    }
}
