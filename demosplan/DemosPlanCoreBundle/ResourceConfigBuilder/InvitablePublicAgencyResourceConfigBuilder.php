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
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;

/**
 * @template-extends BaseOrgaResourceConfigBuilder<OrgaInterface>
 *
 * @property-read AttributeConfigBuilderInterface<OrgaInterface> $legalName
 * @property-read AttributeConfigBuilderInterface<OrgaInterface> $competenceDescription
 * @property-read AttributeConfigBuilderInterface<OrgaInterface> $participationFeedbackEmailAddress
 * @property-read AttributeConfigBuilderInterface<OrgaInterface> $ccEmailAddresses
 * @property-read ToManyRelationshipConfigBuilderInterface<OrgaInterface,AddressInterface> $locationContacts
 */
class InvitablePublicAgencyResourceConfigBuilder extends BaseOrgaResourceConfigBuilder
{
}
