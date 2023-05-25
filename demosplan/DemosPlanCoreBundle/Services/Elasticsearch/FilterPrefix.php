<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Services\Elasticsearch;

/**
 * Implements a prefix Query Filter
 * https://www.elastic.co/guide/en/elasticsearch/reference/current/query-dsl-prefix-query.html.
 */
class FilterPrefix implements FilterInterface
{
    /** @var string */
    protected $field;
    /** @var string */
    protected $value;

    /**
     * @param string $field
     * @param string $value
     */
    public function __construct($field, $value)
    {
        $this->field = $field;
        $this->value = $value;
    }

    public function getField(): string
    {
        return $this->field;
    }

    public function getValue(): string
    {
        return $this->value;
    }
}
