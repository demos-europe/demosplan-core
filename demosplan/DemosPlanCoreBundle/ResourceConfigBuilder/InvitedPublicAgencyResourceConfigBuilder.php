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

use DemosEurope\DemosplanAddon\Contracts\Entities\AddressInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaInterface;
use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseOrgaResourceConfigBuilder;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;

/**
 * @template-extends BaseOrgaResourceConfigBuilder<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,OrgaInterface>
 *
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,OrgaInterface> $hasReceivedInvitationMailInCurrentProcedurePhase
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,OrgaInterface> $originalStatementsCountInProcedure
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,OrgaInterface> $legalName
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,OrgaInterface> $competenceDescription
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>,OrgaInterface> $participationFeedbackEmailAddress
 * @property-read ToManyRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,OrgaInterface,AddressInterface> $locationContacts
 */
class InvitedPublicAgencyResourceConfigBuilder extends BaseOrgaResourceConfigBuilder
{
}
