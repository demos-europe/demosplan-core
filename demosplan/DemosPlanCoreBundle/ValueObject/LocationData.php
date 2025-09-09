<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject;

/**
 * @method string|null getArs()
 * @method             setArs(?string $ars)
 * @method string|null getLocality()
 * @method             setLocality(?string $locality)
 * @method string|null getMunicipalCode()
 * @method             setMunicipalCode(?string $municipalCode)
 * @method string|null getPostalCode()
 * @method             setPostalCode(?string $postalCode)
 */
class LocationData extends ValueObject
{
    /**
     * @var string|null
     */
    protected $ars;

    /**
     * @var string|null
     */
    protected $locality;

    /**
     * @var string|null
     */
    protected $municipalCode;

    /**
     * @var string|null
     */
    protected $postalCode;
}
