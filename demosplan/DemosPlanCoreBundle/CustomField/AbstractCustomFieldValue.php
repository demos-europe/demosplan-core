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

abstract class AbstractCustomFieldValue
{
    protected string $id = '';

    /**
     * Get the field configuration ID.
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set the field configuration ID.
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Get the value stored in this field.
     * Return type varies by implementation (string, array, DateTime, etc.)
     */
    abstract public function getValue(): mixed;

    /**
     * Set the value for this field.
     * Each implementation validates and enforces its own type.
     */
    abstract public function setValue(mixed $value): void;

    /**
     * Serialize to JSON array for database storage.
     */
    abstract public function toJson(): array;

    /**
     * Deserialize from JSON array.
     * Factory method that creates the correct value type based on field configuration.
     */
    abstract public static function fromJson(array $json, CustomFieldInterface $fieldConfig): static;
}
