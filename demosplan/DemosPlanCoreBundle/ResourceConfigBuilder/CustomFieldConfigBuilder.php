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

use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomField;
use EDT\DqlQuerying\Contracts\ClauseFunctionInterface;
use EDT\JsonApi\PropertyConfig\Builder\AttributeConfigBuilderInterface;
use EDT\JsonApi\ResourceConfig\Builder\MagicResourceConfigBuilder;

/**
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $templateEntityId
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $templateEntityClass
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $valueEntityClass
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $configuration
 *
 * Virtual properties
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $fieldType
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $name
 * @property-read AttributeConfigBuilderInterface<ClauseFunctionInterface<bool>, CustomField> $description
 */
class CustomFieldConfigBuilder extends MagicResourceConfigBuilder
{
}
