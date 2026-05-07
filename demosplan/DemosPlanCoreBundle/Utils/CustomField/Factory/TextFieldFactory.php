<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\CustomField\TextField;

class TextFieldFactory implements CustomFieldFactoryInterface
{
    public function supports(string $fieldType): bool
    {
        return 'text' === $fieldType;
    }

    public function create(array $attributes): CustomFieldInterface
    {
        $field = new TextField();
        $field->setFieldType($attributes['fieldType']);
        $field->setName($attributes['name']);
        $field->setDescription($attributes['description']);
        $field->setRequired($attributes['isRequired'] ?? false);

        return $field;
    }
}
