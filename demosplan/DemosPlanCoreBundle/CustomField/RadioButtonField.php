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

class RadioButtonField extends AbstractCustomField
{
    protected string $id = '';

    protected string $fieldType = 'singleSelect';

    /**
     * Radio button options.
     */
    protected array $options = [];

    protected string $description = '';

    public function getFieldType(): string
    {
        return 'singleSelect';
    }

    public function fromJson(array $json): void
    {
        $this->fieldType = $json['fieldType'];
        $this->name = $json['name'];
        $this->description = $json['description'];
        $this->options = array_map(static function ($optionData) {
            $customFieldOption = new CustomFieldOption();
            $customFieldOption->fromJson($optionData);

            return $customFieldOption;
        }, $json['options']);
    }

    public function toJson(): array
    {
        $options = array_map(static fn ($customField) => $customField->toJson(), $this->options);

        return [
            'fieldType'     => $this->fieldType,
            'name'          => $this->name,
            'description'   => $this->description,
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

    public function getCustomOptionValueById(string $customFieldOptionValueId): ?CustomFieldOption
    {
        foreach ($this->options as $option) {
            if ($customFieldOptionValueId === $option->getId()) {
                return $option;
            }
        }

        return null;
    }

    public function getCustomOptionLabelById(mixed $customFieldOptionValueId): ?string
    {
        foreach ($this->options as $option) {
            if ($customFieldOptionValueId === $option->getId()) {
                return $option->getLabel();
            }
        }

        return null;
    }

    protected function validateFieldSpecific(array $options): void
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('Radio button fields must have at least 2 options');
        }
    }
}
