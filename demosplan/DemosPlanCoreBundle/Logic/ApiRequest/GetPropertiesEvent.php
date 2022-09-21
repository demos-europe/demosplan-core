<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\ApiRequest;

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use EDT\JsonApi\ResourceTypes\GetableProperty;
use EDT\Wrapping\Contracts\Types\TypeInterface;

/**
 * @template O of \demosplan\DemosPlanCoreBundle\Entity\EntityInterface
 */
class GetPropertiesEvent extends DPlanEvent
{
    /**
     * @var array<int, GetableProperty>
     */
    private $properties;

    /**
     * @var TypeInterface<O>
     */
    private $type;

    /**
     * @param TypeInterface<O>            $type
     * @param array<int, GetableProperty> $properties
     */
    public function __construct(TypeInterface $type, array $properties)
    {
        $this->properties = $properties;
        $this->type = $type;
    }

    /**
     * @return array<int, GetableProperty>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(GetableProperty $property): void
    {
        $this->properties[] = $property;
    }

    /**
     * @return TypeInterface<O>
     */
    public function getType(): TypeInterface
    {
        return $this->type;
    }
}
