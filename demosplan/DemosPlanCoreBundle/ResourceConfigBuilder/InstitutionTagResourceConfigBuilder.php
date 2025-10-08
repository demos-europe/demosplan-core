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

use DemosEurope\DemosplanAddon\ResourceConfigBuilder\BaseInstitutionTagResourceConfigBuilder;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;

/**
 * @property-read AttributeConfigBuilderInterface<InstitutionTag> $name
 * @property-read AttributeConfigBuilderInterface<InstitutionTag> $isUsed
 * @property-read ToOneRelationshipConfigBuilderInterface<InstitutionTag,InstitutionTagCategory> $category
 * @property-read AttributeConfigBuilderInterface<InstitutionTag> $creationDate
 */
class InstitutionTagResourceConfigBuilder extends BaseInstitutionTagResourceConfigBuilder
{
}
