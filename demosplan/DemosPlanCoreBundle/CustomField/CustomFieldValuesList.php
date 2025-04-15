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
    /**
     * List of custom custom fields values
     *
     * @var array
     */
    protected $customFieldValues = [];

    public function fromJson(array $json): void
    {
        $this->customFieldValues = array_map(function ($fieldData) {
            $customFieldValue = new CustomFieldValue();
            $customFieldValue->fromJson($fieldData);

            return $customFieldValue;
        }, $json['customFields']);
    }

    public function toJson(): array
    {
        return [
            'customFields' => array_map(function ($customField) {
                return $customField->toJson();
            }, $this->customFieldValues),
        ];
    }

    public function setCustomFieldValues(array $customFieldValues): void
    {
        $this->customFieldValues = $customFieldValues;
    }

    public function getCustomFieldsValues(): ?array
    {
        return $this->customFieldValues;
    }
}
