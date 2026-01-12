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

class MultiSelectValue extends AbstractCustomFieldValue
{
    protected ?array $value = null;

    public function getValue(): ?array
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        // Type enforcement: must be array or null
        if ($value !== null && !is_array($value)) {
            throw new InvalidArgumentException(
                'MultiSelectValue requires array or null, got '.gettype($value)
            );
        }

        // Validate array structure: all elements must be strings
        if (is_array($value)) {
            foreach ($value as $item) {
                if (!is_string($item)) {
                    throw new InvalidArgumentException(
                        'All MultiSelectValue array elements must be strings'
                    );
                }
            }

            // Deduplicate and reindex
            $value = array_values(array_unique($value));
        }

        $this->value = $value;
    }

    public function toJson(): array
    {
        return [
            'id'    => $this->id,
            'value' => $this->value,
        ];
    }

    public static function fromJson(array $json, CustomFieldInterface $fieldConfig): static
    {
        $instance = new self();
        $instance->id = $json['id'];
        $instance->setValue($json['value'] ?? null);

        return $instance;
    }
}
