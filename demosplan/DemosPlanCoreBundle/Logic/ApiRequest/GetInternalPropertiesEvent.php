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

use demosplan\DemosPlanCoreBundle\Event\DPlanEvent;
use EDT\Wrapping\Contracts\Types\TypeInterface;

class GetInternalPropertiesEvent extends DPlanEvent
{
    /**
     * @var array<non-empty-string, non-empty-string|null>
     */
    private array $properties;

    private TypeInterface $type;

    /**
     * @param array<non-empty-string, non-empty-string|null> $properties
     */
    public function __construct(array $properties, TypeInterface $type)
    {
        $this->properties = $properties;
        $this->type = $type;
    }

    /**
     * @return array<non-empty-string, non-empty-string|null>
     */
    public function getProperties(): array
    {
        return $this->properties;
    }

    /**
     * @param array<non-empty-string, non-empty-string|null> $properties
     */
    public function setProperties(array $properties): void
    {
        $this->properties = $properties;
    }

    public function getType(): TypeInterface
    {
        return $this->type;
    }
}
