<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Map;

use demosplan\DemosPlanCoreBundle\ValueObject\ValueObject;

/**
 * Class MapOptions.
 *
 * @method string getId()
 * @method        setId(string $id)
 * @method array  getDefaultMaxExtent()
 * @method        setDefaultMaxExtent(array $extent)
 * @method array  getProcedureDefaultInitialExtent()
 * @method        setProcedureDefaultInitialExtent(array $extent)
 * @method array  getProcedureDefaultMaxExtent()
 * @method        setProcedureDefaultMaxExtent(array $extent)
 * @method array  getProcedureInitialExtent()
 * @method        setProcedureInitialExtent(array $extent)
 * @method array  getProcedureMaxExtent()
 * @method        setProcedureMaxExtent(array $extent)
 * @method array  getGlobalAvailableScales()
 * @method        setGlobalAvailableScales(array $scales)
 * @method array  getProcedureScales()
 * @method        setProcedureScales(array $scales)
 * @method string getBaseLayer()
 * @method        setBaseLayer(string $baseLayer)
 * @method string getBaselayerLayers()
 * @method        setBaselayerLayers(string $baselayerLayers)
 * @method string getPublicSearchAutoZoom()
 * @method        setPublicSearchAutoZoom(string $publicSearchAutoZoom)
 * @method array  getAvailableProjections()
 * @method        setAvailableProjections(array $availableProjections)
 * @method array  getDefaultProjection()
 * @method        setDefaultProjection(array $defaultProjection)
 * @method string getBaseLayerProjection()
 * @method        setBaseLayerProjection(string $baseLayerProjection)
 */
class MapOptions extends ValueObject
{
    /** @var string */
    protected $id;

    /** @var array */
    protected $defaultMaxExtent; // Aus parameters.yml map_public_extent

    /** @var array */
    protected $procedureDefaultInitialExtent; // master blaupause

    /** @var array */
    protected $procedureDefaultMaxExtent; // master blaupause

    /** @var array */
    protected $procedureInitialExtent; // procedure

    /** @var array */
    protected $procedureMaxExtent; // procedure

    /** @var array */
    protected $globalAvailableScales; // parameters.yml scales

    /** @var array */
    protected $procedureScales; // procedure

    /** @var string */
    protected $baseLayer; // parameters.yml

    /** @var string */
    protected $baselayerLayers; // parameters.yml

    /** @var string */
    protected $publicSearchAutoZoom; // parameters.yml

    /** @var array */
    protected $availableProjections; // parameters.yml

    /**
     * @var array
     */
    protected $defaultProjection; // parameters.yml

    /**
     * @var string
     */
    protected $baseLayerProjection;

}
