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

abstract class AbstractOptionsCustomField extends AbstractCustomField
{
    /**
     * @var list<CustomFieldOption>
     */
    protected array $options = [];

    public function toJson(): array
    {
        return [
            'fieldType'        => $this->getFieldType(),
            'name'             => $this->name,
            'description'      => $this->description,
            'isSeparateColumn' => $this->isSeparateColumn,
            'isRequired'       => $this->isRequired,
            'options'          => array_map(static fn (CustomFieldOption $option) => $option->toJson(), $this->options),
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

    public function fromJson(array $json): void
    {
        $this->fieldType = $json['fieldType'];
        $this->name = $json['name'];
        $this->description = $json['description'];
        $this->isSeparateColumn = $json['isSeparateColumn'] ?? true;
        $this->isRequired = $json['isRequired'] ?? false;
        $this->options = array_map(static function ($optionData) {
            $customFieldOption = new CustomFieldOption();
            $customFieldOption->fromJson($optionData);

            return $customFieldOption;
        }, $json['options']);
    }

    protected function validateFieldSpecific(array $options): void
    {
        if (count($options) < 2) {
            throw new InvalidArgumentException('Select fields must have at least 2 options');
        }
    }
}
