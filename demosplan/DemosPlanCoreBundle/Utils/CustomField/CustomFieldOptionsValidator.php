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
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

class CustomFieldOptionsValidator
{
    public function validate(array $newOptions, CustomFieldInterface $customField): void
    {
        $this->validateBasicStructure($newOptions);
        $this->validateOptionIds($newOptions, $customField);
        $this->validateFieldTypeSpecific($newOptions, $customField->getType());
    }

    private function validateOptionIds(array $newOptions, CustomFieldInterface $customField): void
    {
        collect($newOptions)
            ->filter(fn ($option) => isset($option['id']))
            ->pluck('id')
            ->each(function ($id) use ($customField) {
                if (null === $customField->getCustomOptionValueById($id)) {
                    throw new InvalidArgumentException("Invalid option ID: {$id}");
                }
            });
    }

    private function validateBasicStructure(array $options): void
    {
        // Check all options have non-empty labels
        if (!collect($options)->every(fn ($option) => isset($option['label']) && !empty(trim($option['label'])))) {
            throw new InvalidArgumentException('All options must have a non-empty label');
        }

        // Check for duplicate labels using Collections
        $labels = collect($options)->pluck('label')->map('trim');
        if ($labels->count() !== $labels->unique()->count()) {
            throw new InvalidArgumentException('Option labels must be unique');
        }
    }

    private function validateFieldTypeSpecific(array $options, string $fieldType): void
    {
        match ($fieldType) {
            'singleSelect' => $this->validateRadioButtonOptions($options),
            // 'select' => $this->validateSelectOptions($options),
            // Future field types can be added here
            default => null, // No specific validation needed
        };
    }

    private function validateRadioButtonOptions(array $options): void
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('Radio is tebutton fields must have at least 2 options');
        }
    }

}
