<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Event;

use EDT\JsonApi\ResourceTypes\PropertyBuilder;
use EDT\Wrapping\Contracts\Types\TypeInterface;

class GetOriginalStatementPropertiesEvent extends DPlanEvent
{
    /**
     * @var TypeInterface<O>
     */
    private $type;

    /**
     * @var array
     */
    private $properties;

    public function __construct(TypeInterface $type, array $properties)
    {
        $this->properties = $properties;
        $this->type = $type;
    }

    public function getProperties(): array
    {
        return $this->properties;
    }

    public function addProperty(PropertyBuilder $property): void
    {
        $this->properties[] = $property;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }
}
