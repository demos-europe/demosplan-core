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

use DemosEurope\DemosplanAddon\Contracts\Entities\GdprConsentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, GdprConsentInterface> $isConsented
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, GdprConsentInterface> $consentChangeDate
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, StatementMeta, StatementInterface> $statement
 */
class GdprConsentResourceConfigBuilder extends MagicResourceConfigBuilder
{

}
