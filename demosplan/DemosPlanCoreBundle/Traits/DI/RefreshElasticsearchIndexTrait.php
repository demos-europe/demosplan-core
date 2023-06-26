<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Traits\DI;

use Elastica\Index;
use FOS\ElasticaBundle\Index\IndexManager;

/**
 * This Trait can be used to refresh all Elasticsearch caches.
 *
 * Needs Service fos_elastica.index_manager to be set via DI or via setter
 */
trait RefreshElasticsearchIndexTrait
{
    /**
     * @var IndexManager
     */
    protected $elasticsearchIndexManager;

    public function getElasticsearchIndexManager(): IndexManager
    {
        return $this->elasticsearchIndexManager;
    }

    public function setElasticsearchIndexManager(IndexManager $elasticsearchIndexManager)
    {
        $this->elasticsearchIndexManager = $elasticsearchIndexManager;
    }

    /**
     * Refresh all Elasticsearch indexes to be able to immediately display changes.
     */
    public function refreshElasticsearchIndexes()
    {
        /** @var Index[] $indexes */
        $indexes = $this->getElasticsearchIndexManager()->getAllIndexes();
        foreach ($indexes as $index) {
            $index->refresh();
        }
    }
}
