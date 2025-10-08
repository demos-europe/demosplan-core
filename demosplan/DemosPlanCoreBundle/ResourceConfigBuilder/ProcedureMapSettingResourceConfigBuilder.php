<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ResourceConfigBuilder;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureSettingsInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $boundingBox
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $mapExtent
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $scales
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $informationUrl
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $copyright
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $availableScales
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $availableProjections
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $showOnlyOverlayCategory
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $coordinate
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $territory
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $defaultBoundingBox
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $defaultMapExtent
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $useGlobalInformationUrl
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $baseLayerUrl
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $baseLayerLayerNames
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $baseLayerProjection
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $defaultProjection
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $globalAvailableScales
 * @property-read AttributeConfigBuilderInterface<ProcedureSettingsInterface> $publicSearchAutoZoom
 *
 * @template-extends MagicResourceConfigBuilder<ProcedureSettingsInterface>
 */
class ProcedureMapSettingResourceConfigBuilder extends MagicResourceConfigBuilder
{
}
