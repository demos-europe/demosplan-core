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

class CustomFieldOption
{
    protected string $id = '';
    protected string $label = '';

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    /**
     * @return array{id : string, label : string}
     */
    public function toJson(): array
    {
        return [
            'id'    => $this->id,
            'label' => $this->label,
        ];
    }

    /**
     * @param array{id : string, label : string} $json
     */
    public function fromJson(array $json): void
    {
        $this->id = $json['id'];
        $this->label = $json['label'];
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
}
