<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

class Sort
{
    /** @var string */
    protected $name;
    /** @var string */
    protected $titleKey;
    /** @var string */
    protected $direction;
    /** @var bool */
    protected $selected;
    /** @var SortField[] */
    protected $fields;
    /** @var string */
    protected $permission = 'feature_procedure_sort_any';

    /**
     * @param string $name
     * @param string $direction
     */
    public function __construct($name, $direction = 'asc')
    {
        $this->setName($name);
        $this->setDirection($direction);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getTitleKey()
    {
        return $this->titleKey;
    }

    /**
     * @param string $titleKey
     */
    public function setTitleKey($titleKey)
    {
        $this->titleKey = $titleKey;
    }

    /**
     * @return string
     */
    public function getDirection()
    {
        return $this->direction;
    }

    /**
     * @param string $direction
     */
    public function setDirection($direction)
    {
        $this->direction = $direction;
    }

    /**
     * @return bool
     */
    public function isSelected()
    {
        return $this->selected;
    }

    /**
     * @param bool $selected
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;
    }

    /**
     * @return SortField[]
     */
    public function getFields()
    {
        return $this->fields;
    }

    /**
     * @param SortField[] $fields
     */
    public function setFields($fields)
    {
        $this->fields = $fields;
    }

    /**
     * Append SortField.
     */
    public function addField(SortField $field)
    {
        $this->fields[] = $field;
    }

    /**
     * @return string
     */
    public function getPermission()
    {
        return $this->permission;
    }

    /**
     * @param string $permission
     */
    public function setPermission($permission)
    {
        $this->permission = $permission;
    }
}
