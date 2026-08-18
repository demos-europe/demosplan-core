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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;

/**
 * Adds the virtual properties of {@link AdminStatementCrossProcedureSearchResourceType} to the
 * statement configuration. They are declared here instead of in {@link StatementResourceConfigBuilder}
 * to keep them out of the configuration surface of the other statement resource types.
 *
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, Statement> $claimedByOthers
 */
class AdminStatementCrossProcedureSearchResourceConfigBuilder extends StatementResourceConfigBuilder
{
}
