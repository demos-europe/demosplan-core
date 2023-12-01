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

use DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\ResourceConfig\Builder\ResourceConfigBuilderInterface;
use EDT\Querying\Contracts\EntityBasedInterface;

/**
 * @template O of EntityInterface
 */
class GetPropertiesEvent extends DPlanEvent implements GetPropertiesEventInterface
{
    /**
     * @param EntityBasedInterface<O> $type
     */
    public function __construct(
        private readonly EntityBasedInterface $type,
        private ResourceConfigBuilderInterface $resourceConfigBuilder
    ) {
    }

    /**
     * @return ResourceConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, O>
     */
    public function getConfigBuilder(): ResourceConfigBuilderInterface
    {
        return $this->resourceConfigBuilder;
    }

    /**
     * @param ResourceConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, O> $configBuilder
     */
    public function setConfigBuilder(ResourceConfigBuilderInterface $configBuilder): void
    {
        $this->resourceConfigBuilder = $configBuilder;
    }

    /**
     * @return EntityBasedInterface<O>
     */
    public function getType(): EntityBasedInterface
    {
        return $this->type;
    }
}
