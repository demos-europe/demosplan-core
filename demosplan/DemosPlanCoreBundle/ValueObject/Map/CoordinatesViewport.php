<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Map;

use DemosEurope\DemosplanAddon\Contracts\ValueObject\CoordinatesViewportInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Coordinates may be stored only in EPSG:3857 Pseudo Mercator.
 */
class CoordinatesViewport extends ValueObject implements CoordinatesViewportInterface
{
    /**
     * @var float
     */
    protected $left;

    /**
     * @var float
     */
    protected $bottom;

    /**
     * @var float
     */
    protected $right;

    /**
     * @var float
     */
    protected $top;

    public function __construct(float $left, float $bottom, float $right, float $top)
    {
        $this->left = $left;
        $this->bottom = $bottom;
        $this->right = $right;
        $this->top = $top;

        $this->lock();
    }

    public function getLeft(): float
    {
        return $this->getProperty('left');
    }

    public function getBottom(): float
    {
        return $this->getProperty('bottom');
    }

    public function getRight(): float
    {
        return $this->getProperty('right');
    }

    public function getTop(): float
    {
        return $this->getProperty('top');
    }

    /**
     * @return float[]
     */
    public function toArray(): array
    {
        return [$this->left, $this->bottom, $this->right, $this->top];
    }
}
