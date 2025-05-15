<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

class RadioButtonField extends AbstractCustomField
{

    protected string $id = '';

    protected string $name = '';

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
        $this->options = $json['options'];
    }

    public function toJson(): array
    {
        return [
            'fieldType'     => $this->fieldType,
            'name'          => $this->name,
            'description'   => $this->description,
            'options'       => $this->options,
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

    public function isValueValid(string $value): bool
    {
        if ('UNASSIGNED' === $value) {
            return true;
        }

        if (in_array($value, $this->options, true)) {
            return true;
        }

        return false;
    }

    public function setId($id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
