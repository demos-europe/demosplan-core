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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use Ramsey\Uuid\Uuid;

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
            // Transform options to the new format if they come as strings
            $options = $this->normalizeOptions($attributes['options']);
            $customField->setOptions($options);
        }

        return $customField;
    }

    /**
     * Ensure options are in the new object format.
     */
    private function normalizeOptions(array $options): array
    {
        $normalizedOptions = [];

        foreach ($options as $option) {
            // Already in new format or ensure it has required keys
            $customFieldOption = new CustomFieldOption();
            $customFieldOption->setId(Uuid::uuid4()->toString());
            $customFieldOption->setLabel($option['label']);
            $normalizedOptions[] = $customFieldOption;
        }

        return $normalizedOptions;
    }
}
