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

use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTag;
use demosplan\DemosPlanCoreBundle\Entity\User\InstitutionTagCategory;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToManyRelationshipConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<InstitutionTagCategory> $name
 * @property-read ToManyRelationshipConfigBuilderInterface<InstitutionTagCategory,InstitutionTag> $tags
 * @property-read ToOneRelationshipConfigBuilderInterface<InstitutionTagCategory,CustomerInterface> $customer
 * @property-read AttributeConfigBuilderInterface<InstitutionTagCategory> $creationDate
 *
 * @template-extends MagicResourceConfigBuilder<InstitutionTagCategory>
 */
class InstitutionTagCategoryResourceConfigBuilder extends MagicResourceConfigBuilder
{
}
