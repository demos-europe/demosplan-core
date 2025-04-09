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
    /**
     * @var string
     */
    protected $id = '';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $type = 'radio_button';

    /**
     * Radio button options.
     *
     * @var array
     */
    protected $options = [];

    /**
     * @var string
     */
    protected $description = '';

    /** @var string */
    protected $procedureId = '';

    public function getFormat(): string
    {
        return 'radio_button';
    }

    public function fromJson(array $json): void
    {
        $this->id = $json['id'];
        $this->type = $json['type'];
        $this->name = $json['name'];
        $this->description = $json['description'];
        $this->options = $json['options'];
    }

    public function toJson(): array
    {
        return [
            'id'            => $this->id,
            'type'          => $this->type,
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

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function getCustomFieldsList(): ?array
    {
        return [];
    }

    public function getType(): string
    {
        return 'radio_button';
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }
}
