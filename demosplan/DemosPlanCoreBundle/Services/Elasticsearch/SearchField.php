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
     * @param int|float $boost
     */
    public function __construct(string $name, private string $field, string $titleKey, protected $boost)
    {
        $this->name = $name;
        $this->titleKey = $titleKey;
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
