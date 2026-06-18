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
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\CustomFieldFactoryRegistry;

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

        return $factory->create($attributes);
    }
}
