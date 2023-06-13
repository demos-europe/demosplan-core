<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

class FilterMissing implements FilterInterface
{
    /** @var string */
    protected $field;
    /** @var mixed */
    protected $value;

    /**
     * @param string $field
     */
    public function __construct($field)
    {
        $this->field = $field;
        $this->value = [''];
    }

    /**
     * @return string
     */
    public function getField()
    {
        return $this->field;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
