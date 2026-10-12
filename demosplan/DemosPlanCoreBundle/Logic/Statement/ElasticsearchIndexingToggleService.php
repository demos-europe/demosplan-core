<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Temporarily disables the Elasticsearch auto-indexing listeners registered on the
 * EntityManager's EventManager, so bulk imports can index the affected entities in one
 * bulk operation afterward instead of one document at a time.
 */
class ElasticsearchIndexingToggleService
{
    private const EVENTS_TO_CHECK = [
        'prePersist', 'postPersist',
        'preUpdate', 'postUpdate',
        'preRemove', 'postRemove',
        'postLoad', 'preFlush', 'onFlush', 'postFlush', 'onClear',
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, list<object>> the removed listeners, keyed by event name
     */
    public function disableAutoIndexing(): array
    {
        $eventManager = $this->entityManager->getEventManager();
        $disabledListeners = [];

        foreach (self::EVENTS_TO_CHECK as $eventName) {
            try {
                if (!array_key_exists($eventName, $eventManager->getAllListeners())) {
                    continue;
                }
                $listeners = $eventManager->getListeners($eventName);
            } catch (Throwable) {
                continue;
            }

            foreach ($listeners as $listener) {
                if (str_contains($listener::class, 'Elastica')) {
                    $eventManager->removeEventListener($eventName, $listener);
                    $disabledListeners[$eventName][] = $listener;
                }
            }
        }

        $this->logger->info('Elasticsearch auto-indexing disabled during import', [
            'listeners_disabled' => count($disabledListeners),
        ]);

        return $disabledListeners;
    }

    /**
     * @param array<string, list<object>> $disabledListeners
     */
    public function reEnableAutoIndexing(array $disabledListeners): void
    {
        $eventManager = $this->entityManager->getEventManager();

        foreach ($disabledListeners as $eventName => $listeners) {
            foreach ($listeners as $listener) {
                $eventManager->addEventListener($eventName, $listener);
            }
        }

        $this->logger->info('Elasticsearch auto-indexing re-enabled after import', [
            'listeners_enabled' => count($disabledListeners),
        ]);
    }
}
