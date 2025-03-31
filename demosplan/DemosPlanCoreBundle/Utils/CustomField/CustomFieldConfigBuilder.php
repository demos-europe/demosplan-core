<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementVoteResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomField;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $type
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $name
 *
 */
class CustomFieldConfigBuilder extends MagicResourceConfigBuilder
{
}
