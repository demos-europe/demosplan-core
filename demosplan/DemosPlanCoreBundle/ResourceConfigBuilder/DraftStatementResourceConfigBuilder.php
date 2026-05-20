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

use DemosEurope\DemosplanAddon\Contracts\Entities\DraftStatementInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseDraftStatementResourceConfigBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, DraftStatementInterface> $customFields
 */
class DraftStatementResourceConfigBuilder extends BaseDraftStatementResourceConfigBuilder
{
}
