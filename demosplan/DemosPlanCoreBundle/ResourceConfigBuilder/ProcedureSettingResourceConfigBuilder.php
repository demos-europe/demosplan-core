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
use demosplan\DemosPlanCoreBundle\ResourceTypes\ScaleResourceType;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $boundingBox
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $mapExtent
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $scales
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $informationUrl
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, ProcedureSettingsInterface> $copyright
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, ProcedureSettingsInterface, ScaleResourceType> $publicAvailableScales
 */
class ProcedureSettingResourceConfigBuilder extends MagicResourceConfigBuilder
{
}
