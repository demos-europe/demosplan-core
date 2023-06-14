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

use DemosEurope\DemosplanAddon\Contracts\Events\GetPropertiesEventInterface;
use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use EDT\JsonApi\ResourceTypes\PropertyBuilder;
use EDT\Wrapping\Contracts\Types\TypeInterface;

/**
 * @template O of \DemosEurope\DemosplanAddon\Contracts\Entities\EntityInterface
 */
class GetPropertiesEvent extends DPlanEvent implements GetPropertiesEventInterface
{
    /**
     * @var array<int, PropertyBuilder>
     */
    private $properties;

    /**
     * @var TypeInterface<O>
     */
    private $type;

    /**
     * @param TypeInterface<O>            $type
     * @param array<int, PropertyBuilder> $properties
     */
    public function __construct(TypeInterface $type, array $properties)
    {
        $this->properties = $properties;
        $this->type = $type;
    }

    /**
     * @return array<int, PropertyBuilder>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(PropertyBuilder $property): void
    {
        $this->properties[] = $property;
    }

    /**
     * @param list<PropertyBuilder> $properties
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    /**
     * @return TypeInterface<O>
     */
    public function getType(): TypeInterface
    {
        return $this->type;
    }
}
