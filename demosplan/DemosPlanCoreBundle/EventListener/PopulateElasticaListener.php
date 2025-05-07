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

    public function preIndexPopulate(PreIndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();
        $settings->setRefreshInterval(-1);
        // do not use replicas during indexing to speed things up
        $settings->setNumberOfReplicas(0);
        $this->logger->info('preIndexPopulate ES Index. Set refresh interval to -1');
    }

    public function postIndexPopulate(PostIndexPopulateEvent $event)
    {
        $index = $this->indexManager->getIndex($event->getIndex());
        $settings = $index->getSettings();

        $settings->setNumberOfReplicas($this->globalConfig->getElasticsearchNumReplicas());
        $index->getClient()->sendRequest('_forcemerge?max_num_segments=5', 'POST');

        // set short refresh interval to avoid problems with outdated lists
        // might lead to performance hits
        $settings->setRefreshInterval('500ms');
        // set a high result window as long as we do not use scroll api
        $settings->set(['max_result_window' => 1_000_000]);

        $this->logger->info('postIndexPopulate ES Index. Set refresh interval to 500');
    }
}
