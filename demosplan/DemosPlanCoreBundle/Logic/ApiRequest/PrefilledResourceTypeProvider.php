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

use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\Querying\Contracts\EntityBasedInterface;
use EDT\Wrapping\Contracts\Types\NamedTypeInterface;
use EDT\Wrapping\TypeProviders\PrefilledTypeProvider;
use Webmozart\Assert\Assert;

/**
 * @template-extends PrefilledTypeProvider<ClauseFunctionInterface, OrderBySortMethodInterface>
 */
class PrefilledResourceTypeProvider extends PrefilledTypeProvider
{
    protected function getIdentifier(EntityBasedInterface $type): string
    {
        Assert::isInstanceOf($type, NamedTypeInterface::class);

        return $type->getTypeName();
    }
}
