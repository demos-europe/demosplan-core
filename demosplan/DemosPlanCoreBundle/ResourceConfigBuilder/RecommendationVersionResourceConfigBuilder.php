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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\RecommendationVersion;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, RecommendationVersion> $versionNumber
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, RecommendationVersion> $recommendationText
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, RecommendationVersion> $createdAt
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, RecommendationVersion, StatementInterface> $statement
 *
 * @template-extends MagicResourceConfigBuilder<ClauseFunctionInterface<bool>, OrderBySortMethodInterface, RecommendationVersion>
 */
class RecommendationVersionResourceConfigBuilder extends MagicResourceConfigBuilder
{
}
