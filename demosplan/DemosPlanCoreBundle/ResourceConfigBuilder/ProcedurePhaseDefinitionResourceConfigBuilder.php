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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @template-extends MagicResourceConfigBuilder<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,ProcedurePhaseDefinition>
 *
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,ProcedurePhaseDefinition> $name
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,ProcedurePhaseDefinition> $audience
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,ProcedurePhaseDefinition> $permissionSet
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,ProcedurePhaseDefinition> $participationState
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,ProcedurePhaseDefinition> $orderInAudience
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,ProcedurePhaseDefinition,Customer> $customer
 */
class ProcedurePhaseDefinitionResourceConfigBuilder extends MagicResourceConfigBuilder
{
}
