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
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PopulateElasticaEventSubscriber implements EventSubscriberInterface
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
        LoggerInterface $logger,
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
        // Use the Elastica Client's API method instead of sendRequest with string params.
        // Expunge deleted docs left over from the previous populate cycle
        // (mutually exclusive with max_num_segments; expunge is the useful one here).
        $index->getClient()->indices()->forcemerge(['only_expunge_deletes' => true]);

        // Reset to the ES default refresh interval; the previous 500ms override caused
        // near-continuous segment merge/compaction work that slowed the next populate.
        $settings->setRefreshInterval('1s');

        $this->logger->info('postIndexPopulate ES Index. Set refresh interval to 1s');
    }

    /**
     * @return array<string, mixed>
     */
    public static function getSubscribedEvents(): array
    {
        return [PreIndexPopulateEvent::class => 'preIndexPopulate', PostIndexPopulateEvent::class => 'postIndexPopulate'];
    }
}
