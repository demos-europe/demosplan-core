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

use DemosEurope\DemosplanAddon\Contracts\Entities\DepartmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseUserResourceConfigBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, UserInterface> $profileCompleted
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, UserInterface> $accessConfirmed
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, UserInterface> $invited
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, UserInterface> $newsletter
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, UserInterface> $noPiwik
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,UserInterface,RoleInterface> $roles
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,UserInterface,DepartmentInterface> $department
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,\DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface,OrgaInterface> $orga
 */
class UserResourceConfigBuilder extends BaseUserResourceConfigBuilder
{
}
