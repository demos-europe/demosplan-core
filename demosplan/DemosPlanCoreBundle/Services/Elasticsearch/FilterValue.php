<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

class FilterValue
{
    /** @var Aggregation */
    protected $aggregation;
    /** @var string */
    protected $key;
    /** @var string */
    protected $label;
    /** @var bool */
    protected $selected = false;

    /**
     * @param string $key
     */
    public function __construct($key)
    {
        $this->setKey($key);
    }

    /**
     * @return Aggregation
     */
    public function getAggregation()
    {
        return $this->aggregation;
    }

    /**
     * @param Aggregation $aggregation
     *
     * @return FilterValue
     */
    public function setAggregation($aggregation)
    {
        $this->aggregation = $aggregation;

        return $this;
    }

    /**
     * @return string
     */
    public function getKey()
    {
        return $this->key;
    }

    /**
     * @param string $key
     *
     * @return FilterValue
     */
    public function setKey($key)
    {
        $this->key = $key;

        return $this;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @param string $label
     *
     * @return FilterValue
     */
    public function setLabel($label)
    {
        $this->label = $label;

        return $this;
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
     *
     * @return FilterValue
     */
    public function setSelected($selected)
    {
        $this->selected = $selected;

        return $this;
    }

    /**
     * Shortcut for getAggregation->getAmount();.
     *
     * @return int the count of the bucket aggregation, displayed in twig
     */
    public function getCount()
    {
        return $this->aggregation->getAmount();
    }

    /**
     * Shortcut for getAggregation->getNullValue();.
     *
     * @return string|null used in twig as value for this filter send in a request
     */
    public function getValue()
    {
        return $this->aggregation->getName();
    }
}
