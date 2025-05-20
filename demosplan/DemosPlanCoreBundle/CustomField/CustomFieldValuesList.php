<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

class CustomFieldValuesList
{
    protected array $customFieldValues = [];

    public function fromJson(array $json): void
    {
        $this->customFieldValues = array_map(static function ($fieldData) {
            $customFieldValue = new CustomFieldValue();
            $customFieldValue->fromJson($fieldData);

            return $customFieldValue;
        }, $json);
    }

    public function toJson(): array
    {
        return array_map(static function ($customField) {
            return $customField->toJson();
        }, $this->customFieldValues);
    }

    public function getCustomFieldsValues(): ?array
    {
        return $this->customFieldValues;
    }

    public function addCustomFieldValue(CustomFieldValue $customFieldValue): void
    {
        // If no matching ID is found, add the new custom field value
        $this->customFieldValues[] = $customFieldValue;
    }

    public function findById(string $fieldId): ?CustomFieldValue
    {
        foreach ($this->getCustomFieldsValues() as $customFieldValue) {
            if ($customFieldValue->getId() === $fieldId) {
                return $customFieldValue;
            }
        }

        return null;
    }
}
