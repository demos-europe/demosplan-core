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

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseStatementVoteResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementVote;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, StatementVote> $firstname
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, StatementVote> $lastname
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, StatementVote> $email
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, StatementVote> $city
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, StatementVote> $postcode
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, StatementVote> $name
 */
class StatementVoteResourceConfigBuilder extends BaseStatementVoteResourceConfigBuilder
{
}
