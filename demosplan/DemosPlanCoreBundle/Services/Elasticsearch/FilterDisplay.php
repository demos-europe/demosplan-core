<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

class FilterDisplay
{
    /** @var string */
    protected $name;
    /** @var string */
    protected $field;
    /** @var string */
    protected $titleKey;
    /** @var FilterValue[] */
    protected $values;
    /** @var string|null */
    protected $aggregationNullValue;
    /** @var bool */
    protected $hasNoAssignmentSelectOption = true;
    /**
     * @var string|null
     */
    protected $contextHelpKey;

    /**
     * Display Filter in interface.
     *
     * @var bool
     */
    protected $displayInInterface = true;
    /**
     * Elasticsearch index field.
     *
     * @var string
     */
    protected $aggregationField;
    /** @var string */
    protected $permission = 'feature_procedure_filter_any';

    /**
     * @param string $name
     */
    public function __construct($name)
    {
        $this->setName($name);
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     *
     * @return FilterDisplay
     */
    public function setName($name)
    {
        $this->name = $name;

        return $this;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field)
    {
        $this->field = $field;
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
     *
     * @return FilterDisplay
     */
    public function setTitleKey($titleKey)
    {
        $this->titleKey = $titleKey;

        return $this;
    }

    /**
     * @return FilterValue[]
     */
    public function getValues()
    {
        return $this->values;
    }

    /**
     * Sets the FilterValue objects in this instance. While this function is able to handle the legacy array format
     * if somehow possible the input parameter given should be an array of actual FilterValue instance.
     *
     * @param FilterValue[] $values
     *
     * @return FilterDisplay this instance
     */
    public function setValues($values)
    {
        $this->values = $values;

        return $this;
    }

    /**
     * Append Value. While this function is able to handle the legacy array format
     * if somehow possible the input parameter given should be an actual FilterValue instance.
     *
     * @param FilterValue $value
     *
     * @return FilterDisplay
     */
    public function addValue($value)
    {
        $this->values[] = $value;

        return $this;
    }

    /**
     * @return string|null
     */
    public function getAggregationNullValue()
    {
        return $this->aggregationNullValue;
    }

    /**
     * @param string|null $aggregationNullValue
     *
     * @return FilterDisplay
     */
    public function setAggregationNullValue($aggregationNullValue)
    {
        $this->aggregationNullValue = $aggregationNullValue;

        return $this;
    }

    /**
     * If field is not explicitly set use name.
     *
     * @return string
     */
    public function getAggregationField()
    {
        return is_null($this->aggregationField) ? $this->name : $this->aggregationField;
    }

    /**
     * @param string $aggregationField
     *
     * @return FilterDisplay
     */
    public function setAggregationField($aggregationField)
    {
        $this->aggregationField = $aggregationField;

        return $this;
    }

    public function getContextHelpKey(): ?string
    {
        return $this->contextHelpKey;
    }

    public function setContextHelpKey(?string $contextHelpKey): FilterDisplay
    {
        $this->contextHelpKey = $contextHelpKey;

        return $this;
    }

    /**
     * @return bool
     */
    public function isDisplayInInterface()
    {
        return $this->displayInInterface;
    }

    /**
     * @param bool $displayInInterface
     *
     * @return FilterDisplay
     */
    public function setDisplayInInterface($displayInInterface)
    {
        $this->displayInInterface = $displayInInterface;

        return $this;
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

    public function hasNoAssignmentSelectOption(): bool
    {
        return $this->hasNoAssignmentSelectOption;
    }

    public function setHasNoAssignmentSelectOption(bool $hasNoAssignmentSelectOption)
    {
        $this->hasNoAssignmentSelectOption = $hasNoAssignmentSelectOption;
    }
}
