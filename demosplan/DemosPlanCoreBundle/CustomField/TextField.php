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

class TextField extends AbstractCustomField
{
    protected string $id = '';

    protected string $fieldType = 'text';

    protected string $description = '';

    protected bool $isRequired = false;

    public function getFieldType(): string
    {
        return 'text';
    }

    public function fromJson(array $json): void
    {
        $this->fieldType   = $json['fieldType'];
        $this->name        = $json['name'];
        $this->description = $json['description'];
        $this->isRequired  = $json['isRequired'] ?? false;
    }

    public function toJson(): array
    {
        return [
            'fieldType'   => $this->fieldType,
            'name'        => $this->name,
            'description' => $this->description,
            'isRequired'  => $this->isRequired,
        ];
    }

    public function getOptions(): array
    {
        return [];
    }

    public function getCustomOptionValueById(string $customFieldOptionValueId): ?CustomFieldOption
    {
        return null;
    }

    public function setRequired(bool $isRequired): void
    {
        $this->isRequired = $isRequired;
    }

    public function getRequired(): bool
    {
        return $this->isRequired;
    }

    protected function validateFieldSpecific(array $options): void
    {
        // Text fields have no predefined options — nothing to validate here
    }
}
