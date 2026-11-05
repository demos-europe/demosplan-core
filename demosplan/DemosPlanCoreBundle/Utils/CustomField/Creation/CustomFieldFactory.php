<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Creation;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Creation\Factory\CustomFieldFactoryRegistry;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Creation\TypeValidator\CustomFieldTypeValidatorRegistry;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;

class CustomFieldFactory
{
    public function __construct(
        private readonly CustomFieldTypeValidatorRegistry $validatorRegistry,
        private readonly CustomFieldFactoryRegistry $factoryRegistry)
    {
    }

    public function createCustomField(array $attributes): CustomFieldInterface
    {
        $type = $attributes['fieldType'];
        $validator = $this->validatorRegistry->getValidatorForFieldType($type);
        $validator->validate($attributes);

        $factory = $this->factoryRegistry->getFactory($type);

        $customField = $factory->create($attributes);
        $customField->setSeparateColumn(
            CustomFieldSupportedEntity::statement->value === $attributes['targetEntity']
            && true === ($attributes['isSeparateColumn'] ?? false)
        );

        return $customField;
    }
}
