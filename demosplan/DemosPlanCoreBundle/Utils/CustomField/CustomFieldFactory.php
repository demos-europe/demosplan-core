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

class CustomFieldFactory
{
    public function __construct(private readonly CustomFieldValidator $customFieldValidator)
    {
    }

    public function createCustomField(array $attributes): CustomFieldInterface
    {
        $this->customFieldValidator->validate($attributes);

        $type = $attributes['fieldType'];

        $customFieldClass = CustomFieldInterface::TYPE_CLASSES[$type];
        $customField = new $customFieldClass();
        $customField->setFieldType($type);
        $customField->setName($attributes['name']);
        $customField->setDescription($attributes['description']);

        if (isset($attributes['options']) && method_exists($customField, 'setOptions')) {
            $customField->setOptions($attributes['options']);
        }

        return $customField;
    }
}
