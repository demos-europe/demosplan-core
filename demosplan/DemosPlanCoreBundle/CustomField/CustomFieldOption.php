<?php

declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\CustomField;
use Ramsey\Uuid\Uuid;

class CustomFieldOption
{
    protected string $id;
    protected string $label;
    public function getId(): string { return $this->id; }
    public function getLabel(): string { return $this->label; }

    public function toJson(): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
        ];
    }
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

