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
    protected $name = '';



    /**
     * @var string
     */
    protected $type = '';

    /**
     * Radio button options
     *
     * @var array
     */
    //protected $options = [];

    /**
     * @var string
     */
    protected $caption = '';

    /** @var string */
    protected $procedureId = '';

    public function getFormat(): string
    {
        return 'radio_button';
    }

    public function fromJson(array $json): void
    {
        $this->type = $json['type'];
        $this->name = $json['name'];
        $this->caption = $json['caption'];
       // $this->options = $json['options'];
    }

    public function toJson(): array
    {
        return [
            'type'      => $this->type,
            'name'      => $this->name,
            'caption'   => $this->caption,
           // 'options' => $this->options,
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

    public function getCaption(): string
    {
        return $this->caption;
    }

    public function setCaption(string $caption): void
    {
        $this->caption = $caption;
    }

    public function getProcedureId(): string
    {
        return $this->procedureId;
    }

    public function getCustomFieldsList(): ?array
    {
        return [];
    }
}
