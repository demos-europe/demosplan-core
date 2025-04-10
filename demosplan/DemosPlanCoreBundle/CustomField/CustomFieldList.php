<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

class CustomFieldList implements CustomFieldInterface
{
    /**
     * @var string
     */
    protected $name = '';

    /**
     * List of custom custom fields.
     *
     * @var array
     */
    protected $customFields = [];

    public const TYPE_CLASSES = [
        'radio_button' => RadioButtonField::class,
        // 'dropdown' => DropdownField::class,
        // Add other custom field types here
    ];

    public function getFormat(): string
    {
        return 'segment_custom_fields';
    }

    public function getType(): string
    {
        return 'segment_custom_fields';
    }

    public function fromJson(array $json): void
    {
        $this->name = $json['name'];
        $this->customFields = array_map(function ($fieldData) {
            $type = $fieldData['fieldType'];
            if (!isset(self::TYPE_CLASSES[$type])) {
                return [];
                // throw new RuntimeException('Unknown custom field type: ' . $type);
            }
            $customFieldClass = self::TYPE_CLASSES[$type];
            $customField = new $customFieldClass();
            $customField->fromJson($fieldData);

            return $customField;
        }, $json['customFields']);
    }

    public function toJson(): array
    {
        return [
            'name'         => $this->name,
            'customFields' => array_map(function ($customField) {
                return $customField->toJson();
            }, $this->customFields),
        ];
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCustomFields(): array
    {
        return $this->customFields;
    }

    public function setCustomFields(array $customFields): void
    {
        $this->customFields = $customFields;
    }

    public function getCustomFieldsList(): ?array
    {
        return $this->customFields;
    }
}
