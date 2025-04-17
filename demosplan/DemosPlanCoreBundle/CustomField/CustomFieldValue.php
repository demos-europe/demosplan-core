<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\CustomField;

class CustomFieldValue
{

    protected string $id = '';

    protected string $value = '';

    public function fromJson(array $json): void
    {
        $this->id = $json['id'];
        $this->value = $json['value'];
    }

    public function toJson(): array
    {
        return [
            'id'            => $this->id,
            'value'     => $this->value,
        ];
    }


    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getValue(): string
    {
        return $this->value;
    }

}
