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

use BadMethodCallException;

class CustomFieldValuesList
{
    /** @var AbstractCustomFieldValue[] */
    protected array $customFieldValues = [];

    public function fromJson(array $json): void
    {
        // NOTE: This method signature is kept for backward compatibility,
        // but should not be used directly. Use CustomFieldValueCreator instead
        // which uses the factory to create proper typed values.

        // For now, this creates generic CustomFieldValue objects
        // The actual type-specific creation happens in CustomFieldValueCreator
        throw new BadMethodCallException('fromJson() is deprecated. Use CustomFieldValueFactory via CustomFieldValueCreator instead.');
    }

    public function toJson(): array
    {
        return array_map(
            static fn (AbstractCustomFieldValue $customField) => $customField->toJson(),
            $this->customFieldValues
        );
    }

    /**
     * @return AbstractCustomFieldValue[]|null
     */
    public function getCustomFieldsValues(): ?array
    {
        return $this->customFieldValues;
    }

    public function addCustomFieldValue(AbstractCustomFieldValue $customFieldValue): void
    {
        $this->customFieldValues[] = $customFieldValue;
    }

    public function findById(string $fieldId): ?AbstractCustomFieldValue
    {
        foreach ($this->customFieldValues as $customFieldValue) {
            if ($customFieldValue->getId() === $fieldId) {
                return $customFieldValue;
            }
        }

        return null;
    }

    public function removeCustomFieldValue(AbstractCustomFieldValue $customFieldValue): void
    {
        $this->customFieldValues = array_filter(
            $this->customFieldValues,
            static fn (AbstractCustomFieldValue $fieldValue) => $fieldValue->getId() !== $customFieldValue->getId()
        );
    }

    public function reindexValues(): void
    {
        $this->customFieldValues = array_values($this->customFieldValues);
    }

    public function sortByFieldId(): void
    {
        usort(
            $this->customFieldValues,
            fn (AbstractCustomFieldValue $a, AbstractCustomFieldValue $b) => strcmp($a->getId(), $b->getId())
        );
    }

    public function isEmpty(): bool
    {
        return [] === $this->customFieldValues;
    }
}
