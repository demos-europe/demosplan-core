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
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $boundingBox
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $mapExtent
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $scales
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $informationUrl
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $copyright
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $availableScales
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $availableProjections
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $showOnlyOverlayCategory
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $coordinate
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $territory
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $defaultBoundingBox
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $defaultMapExtent
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $useGlobalInformationUrl
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $baseLayerUrl
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $baseLayerLayerNames
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $baseLayerProjection
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $defaultProjection
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $globalAvailableScales
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $publicSearchAutoZoom
 *
 * @template-extends MagicResourceConfigBuilder<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,ProcedureSettingsInterface>
 */
class ProcedureMapSettingResourceConfigBuilder extends MagicResourceConfigBuilder
{
}
