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
    protected int $sortOrder = 0;

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getSortOrder(): int
    {
        return $this->sortOrder;
    }

    public function setSortOrder(int $sortOrder): void
    {
        $this->sortOrder = $sortOrder;
    }

    /**
     * @return array{id: string, label: string, sortOrder: int}
     */
    public function toJson(): array
    {
        return [
            'id'        => $this->id,
            'label'     => $this->label,
            'sortOrder' => $this->sortOrder,
        ];
    }

    /**
     * @param array{id: string, label: string, sortOrder?: int} $json
     */
    public function fromJson(array $json): void
    {
        $this->id        = $json['id'];
        $this->label     = $json['label'];
        $this->sortOrder = $json['sortOrder'] ?? 0;
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
