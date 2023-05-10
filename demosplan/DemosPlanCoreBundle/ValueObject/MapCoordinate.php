<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;

/**
 * @method int|float   getLatitude()
 * @method             setLatitude($latitude)
 * @method int|float   getLongitude()
 * @method             setLongitude($longitude)
 * @method string|null getCrs()
 * @method             setCrs(string $crs)
 */
class MapCoordinate extends ValueObject
{
    /**
     * @var int|float
     */
    protected $latitude;

    /**
     * @var int|float
     */
    protected $longitude;

    /**
     * @var string
     */
    protected $crs = MapService::PSEUDO_MERCATOR_PROJECTION_LABEL;
}
