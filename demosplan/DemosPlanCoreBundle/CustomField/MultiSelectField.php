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

class MultiSelectField extends AbstractCustomField
{
    protected string $id = '';

    protected string $fieldType = 'multiSelect';

    /**
     * Options for multi-select field (checkboxes).
     */
    protected array $options = [];

    protected string $description = '';

    protected bool $isRequired = false;

    public function getFieldType(): string
    {
        return 'multiSelect';
    }

    public function fromJson(array $json): void
    {
        $this->fieldType = $json['fieldType'];
        $this->name = $json['name'];
        $this->description = $json['description'];
        $this->isRequired = $json['isRequired'];
        $this->options = array_map(static function ($optionData) {
            $customFieldOption = new CustomFieldOption();
            $customFieldOption->fromJson($optionData);

            return $customFieldOption;
        }, $json['options']);
    }

    public function toJson(): array
    {
        $options = array_map(static function ($customField) {
            return $customField->toJson();
        }, $this->options);

        return [
            'fieldType'     => $this->fieldType,
            'name'          => $this->name,
            'description'   => $this->description,
            'isRequired'    => $this->isRequired,
            'options'       => $options,
        ];
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function setRequired(bool $isRequired): void
    {
        $this->isRequired = $isRequired;
    }

    public function getRequired(): bool
    {
        return $this->isRequired;
    }

    public function getCustomOptionValueById(string $customFieldOptionValueId): ?CustomFieldOption
    {
        foreach ($this->options as $option) {
            if ($customFieldOptionValueId === $option->getId()) {
                return $option;
            }
        }

        return null;
    }

    protected function validateFieldSpecific(array $options): void
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('Multi select fields must have at least 2 options');
        }
    }
}
