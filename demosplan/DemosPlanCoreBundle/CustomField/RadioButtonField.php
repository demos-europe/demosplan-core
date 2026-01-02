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

    public function getFormat(): string
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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getOptions(): array
    {
        return $this->options;
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getCustomFieldsList(): ?array
    {
        return [];
    }

    public function setFieldType(string $type): void
    {
        $this->fieldType = $type;
    }

    public function getType(): string
    {
        return 'singleSelect';
    }

    public function isValueValid(?string $value): bool
    {
        if (null === $value) {
            return true;
        }

        return collect($this->options)->contains(fn ($option) => $option->getId() === $value);
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
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
            throw new InvalidArgumentException('Radio button fields must have at least 2 options');
        }
    }
}
