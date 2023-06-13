<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

class SearchField
{
    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $titleKey;

    /**
     * @var int|float
     */
    protected $boost;

    /**
     * @var string
     */
    private $field;

    /**
     * @param int|float $boost
     */
    public function __construct(string $name, string $field, string $titleKey, $boost)
    {
        $this->name = $name;
        $this->titleKey = $titleKey;
        $this->boost = $boost;
        $this->field = $field;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function setField(string $field): SearchField
    {
        $this->field = $field;

        return $this;
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
     * @return int|float
     */
    public function getBoost()
    {
        return $this->boost;
    }
}
