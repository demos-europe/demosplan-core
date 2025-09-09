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

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;
use geoPHP\Geometry\Geometry;
use Illuminate\Support\Collection;

/**
 * @method Collection           getPrintLayers();
 * @method CoordinatesViewport  getViewport();
 * @method Geometry             getGeometry();
 */
class Feature extends ValueObject
{
    /**
     * @var Collection<int, PrintLayer>
     */
    protected $printLayers;

    /**
     * @var CoordinatesViewport
     */
    protected $viewport;

    /**
     * @var Geometry
     */
    protected $geometry;

    public function __construct(
        Collection $printLayers,
        CoordinatesViewport $viewport,
        Geometry $geometry)
    {
        $this->printLayers = $printLayers;
        $this->viewport = $viewport;
        $this->geometry = $geometry;

        $this->lock();
    }

    public function getLeft(): float
    {
        return $this->viewport->getLeft();
    }

    public function getBottom(): float
    {
        return $this->viewport->getBottom();
    }

    public function getRight(): float
    {
        return $this->viewport->getRight();
    }

    public function getTop(): float
    {
        return $this->viewport->getTop();
    }
}
