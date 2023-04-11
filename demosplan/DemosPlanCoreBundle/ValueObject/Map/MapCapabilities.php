<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * @method string getType()
 * @method string getXml()
 * @method void   setType(string $type)
 * @method void   setXml(string $xml)
 */
class MapCapabilities extends ValueObject
{
    /**
     * @const string
     */
    public const TYPE_WMS = 'wms';

    /**
     * @const string
     */
    public const TYPE_WMS_XMLNS = 'http://www.opengis.net/wms';

    /**
     * @const string
     */
    public const TYPE_WMTS = 'wmts';

    /**
     * @const string
     */
    public const TYPE_WMTS_XMLNS = 'http://www.opengis.net/wmts/1.0';

    /**
     * @const string
     */
    public const TYPE_UNKNOWN = 'unknown';

    /**
     * @var string
     */
    protected $xml;

    /**
     * @var string
     */
    protected $type;
}
