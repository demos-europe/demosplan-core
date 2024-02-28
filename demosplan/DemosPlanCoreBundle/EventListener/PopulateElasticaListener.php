<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
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
     * @var IndexManager
     */
    private $indexManager;

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
        IndexManager $indexManager,
        LoggerInterface $logger
    ) {
        $this->indexManager = $indexManager;
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
        $index->getClient()->request('_forcemerge?max_num_segments=5', 'POST');

        // set short refresh interval to avoid problems with outdated lists
        // might lead to performance hits
        $settings->setRefreshInterval('500ms');
        // set a high result window as long as we do not use scroll api
        $settings->set(['max_result_window' => 1000000]);

        $this->logger->info('postIndexPopulate ES Index. Set refresh interval to 500');

        // increase max clause count to avoid problems with large queries
        $settings->set(['max_clause_count' => 4096]);
        $this->logger->info('postIndexPopulate ES Index. Set max_clause_count to 4096');
    }
}
