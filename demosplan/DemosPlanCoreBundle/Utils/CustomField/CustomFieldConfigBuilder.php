<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\DqlQuerying\Contracts\OrderBySortMethodInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\PropertyConfig\Builder\ToOneRelationshipConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $fieldType
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $name
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $description
 * @property-read ToOneRelationshipConfigBuilderInterface<ClauseFunctionInterface<bool>,OrderBySortMethodInterface,CustomFieldInterface,Procedure> $templateEntity
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $targetEntity
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $sourceEntity
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomFieldInterface> $sourceEntityId
 */
class CustomFieldConfigBuilder extends MagicResourceConfigBuilder
{
}
