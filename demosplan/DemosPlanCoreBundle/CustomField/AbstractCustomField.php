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

    public function validate(array $newOptions): void
    {
        $this->validateBasicStructure($newOptions);
        $this->validateOptionIds($newOptions);

        $this->validateFieldSpecific($newOptions);
    }

    public function validateBasicStructure(array $newOptions): void
    {
        // Check all options have non-empty labels
        if (!collect($newOptions)->every(fn ($option) => !empty(trim($option->getLabel())))) {
            throw new InvalidArgumentException('All options must have a non-empty label');
        }

        // Check for duplicate labels using Collections
        $labels = collect($newOptions)->map(fn($option) => $option->getLabel())->map('trim');
        if ($labels->count() !== $labels->unique()->count()) {
            throw new InvalidArgumentException('Option labels must be unique');
        }
    }

    private function validateOptionIds(array $newOptions): void
    {
        collect($newOptions)
            ->filter(fn ($option) => $option->getId())
            ->map(fn($option) => $option->getId())
            ->each(function ($id) {
                if (null === $this->getCustomOptionValueById($id)) {
                    throw new InvalidArgumentException("Invalid option ID: {$id}");
                }
            });
    }
}
