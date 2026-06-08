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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldOption;
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use Ramsey\Uuid\Uuid;

class SingleSelectFieldFactory implements CustomFieldFactoryInterface
{
    public function supports(string $fieldType): bool
    {
        return 'singleSelect' === $fieldType;
    }

    public function create(array $attributes): CustomFieldInterface
    {
        $field = new RadioButtonField();
        $field->setFieldType($attributes['fieldType']);
        $field->setName($attributes['name']);
        $field->setDescription($attributes['description']);
        $field->setOptions($this->normalizeOptions($attributes['options']));

        return $field;
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
