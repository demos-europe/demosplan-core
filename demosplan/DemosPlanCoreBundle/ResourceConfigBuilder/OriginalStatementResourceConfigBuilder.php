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

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $fullText
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $shortText
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $submitDate
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $isSubmittedByCitizen
 */
class OriginalStatementResourceConfigBuilder extends BaseStatementResourceConfigBuilder
{
}
