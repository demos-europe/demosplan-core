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

class SingleSelectValue extends AbstractCustomFieldValue
{
    protected ?string $value = null;

    public function getValue(): ?string
    {
        return $this->value;
    }

    public function setValue(mixed $value): void
    {
        // Type enforcement: must be string or null
        if ($value !== null && !is_string($value)) {
            throw new InvalidArgumentException(
                'SingleSelectValue requires string or null, got '.gettype($value)
            );
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
