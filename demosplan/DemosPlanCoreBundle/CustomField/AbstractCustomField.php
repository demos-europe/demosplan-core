<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;

/**
 * Provide the generalized hashing functionality for stored queries.
 */
abstract class AbstractCustomField implements CustomFieldInterface
{
    protected string $name = '';

    protected string $description = '';

    abstract public function isValueValid(string $value): bool;

    abstract protected function validateFieldSpecific(array $options): void;

    public function validate(?array $newOptions = null): void
    {
        $options = $newOptions ?? $this->getOptions();

        $this->validateBasicStructure($options);
        $this->validateOptionIds($options);

        $this->validateFieldSpecific($options);
    }

    private function validateBasicStructure(array $options): void
    {
        // Check all options have non-empty labels
        if (!collect($options)->every(fn ($option) => isset($option['label']) && !in_array(trim((string) $option['label']), ['', '0'], true))) {
            throw new InvalidArgumentException('All options must have a non-empty label');
        }

        // Check for duplicate labels using Collections
        $labels = collect($options)->pluck('label')->map('trim');
        if ($labels->count() !== $labels->unique()->count()) {
            throw new InvalidArgumentException('Option labels must be unique');
        }
    }

    private function validateOptionIds(array $newOptions): void
    {
        collect($newOptions)
            ->filter(fn ($option) => isset($option['id']))
            ->pluck('id')
            ->each(function ($id) {
                if (!$this->getCustomOptionValueById($id) instanceof CustomFieldOption) {
                    throw new InvalidArgumentException("Invalid option ID: {$id}");
                }
            });
    }
}
