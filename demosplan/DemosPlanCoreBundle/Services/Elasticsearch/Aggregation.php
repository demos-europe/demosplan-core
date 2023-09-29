<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

class Aggregation
{
    /** @var string */
    protected $name;
    /** @var int */
    protected $amount;
    /** @var string|null */
    protected $nullValue;

    /**
     * @param string      $name
     * @param string|null $nullValue
     */
    public function __construct($name, $nullValue = null)
    {
        $this->setName($name);
        $this->setNullValue($nullValue);
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
     * @return int
     */
    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * @param int $amount
     */
    public function setAmount($amount)
    {
        $this->amount = $amount;
    }

    /**
     * @return string|null
     */
    public function getNullValue()
    {
        return $this->nullValue;
    }

    /**
     * @param string|null $nullValue
     */
    public function setNullValue($nullValue)
    {
        $this->nullValue = $nullValue;
    }
}
