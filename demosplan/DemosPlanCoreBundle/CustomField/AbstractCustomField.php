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
use ReflectionClass;
use ReflectionProperty;

/**
 * Base class that validates custom field options
 * and requires subclasses to implement value validation.
 */
abstract class AbstractCustomField implements CustomFieldInterface
{
    protected string $id = '';

    protected string $name = '';

    protected string $fieldType = '';

    protected string $description = '';

    abstract public function isValueValid(mixed $value): bool;

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

    public function getApiAttributes(): array
    {
        // static::class gets the name of the actual class being used, not the parent class
        // For example: if MultiSelectField calls this method, static::class = "MultiSelectField"
        // This lets us inspect the right class properties even though the method is in AbstractCustomField
        $reflection = new ReflectionClass(static::class);
        $properties = $reflection->getProperties(ReflectionProperty::IS_PROTECTED | ReflectionProperty::IS_PUBLIC);

        $attributes = [];
        foreach ($properties as $property) {
            $name = $property->getName();
            $attributes[] = $name;
        }

        return $attributes;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function setFieldType(string $type): void
    {
        $this->fieldType = $type;
    }
}
