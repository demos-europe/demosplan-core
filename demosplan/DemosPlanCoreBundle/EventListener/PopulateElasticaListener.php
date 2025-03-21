<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventListener;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use FOS\ElasticaBundle\Event\PostIndexPopulateEvent;
use FOS\ElasticaBundle\Event\PreIndexPopulateEvent;
use FOS\ElasticaBundle\Index\IndexManager;
use Monolog\Logger;
use Psr\Log\LoggerInterface;

class PopulateElasticaListener
{
    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @var GlobalConfigInterface
     */
    protected $globalConfig;

    public function __construct(
        GlobalConfigInterface $globalConfig,
        private readonly IndexManager $indexManager,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->globalConfig = $globalConfig;
    }

    public function preIndexPopulate(PreIndexPopulateEvent $event): void
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();

        $settings->set([
            'refresh_interval' => '-1',
            'number_of_replicas' => 0
        ]);

        $this->logger->info('preIndexPopulate ES Index. Set refresh interval to -1');
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event): void
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();

        $replicas = $this->globalConfig->getElasticsearchNumReplicas();

        $settings->set([
            'refresh_interval' => '500ms',
            'number_of_replicas' => $replicas,
            'max_result_window' => 1_000_000
        ]);

        try {
            // Use the index's native request method
            $indexName = $index->getName();

            // Use the underlying Elasticsearch client API directly
            $esClient = $index->getClient();

            // ES8 native client API to force merge
            $esClient->indices()->forcemerge([
                'index' => $indexName,
                'max_num_segments' => 5
            ]);

            $this->logger->info('postIndexPopulate ES Index. Set refresh interval to 500ms. Force merge executed.');
        } catch (\Exception $e) {
            $this->logger->error('Failed to force merge index: ' . $e->getMessage());
        }
    }
}
