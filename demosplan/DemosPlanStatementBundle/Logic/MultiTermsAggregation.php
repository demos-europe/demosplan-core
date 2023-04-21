<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanStatementBundle\Logic;

use Elastica\Aggregation\GlobalAggregation;
use Elastica\Aggregation\Traits\MissingTrait;
use Elastica\Aggregation\Traits\ShardSizeTrait;

/**
 * Implements an Elasticsearch `Multi Terms Aggregation`, which is currently
 * {@link https://github.com/ruflin/Elastica/issues/2071 not supported by `Elastica`}.
 * Please note that **not** all parameters are supported yet.
 *
 * @see https://www.elastic.co/guide/en/elasticsearch/reference/master/search-aggregations-bucket-multi-terms-aggregation.html
 */
class MultiTermsAggregation extends GlobalAggregation
{
    use MissingTrait;
    use ShardSizeTrait;

    /**
     * @param list<string> $fields
     */
    public function setTerms(array $fields): void
    {
        $this->setParam('terms', []);
        $this->addTerms($fields);
    }

    /**
     * @param list<string> $fields
     */
    public function addTerms(array $fields): void
    {
        array_map([$this, 'addTerm'], $fields);
    }

    public function addTerm(string $field): void
    {
        $this->addParam('terms', ['field' => $field]);
    }
}
