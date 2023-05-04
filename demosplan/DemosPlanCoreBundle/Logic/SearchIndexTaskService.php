<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Application\DemosPlanKernel;
use demosplan\DemosPlanCoreBundle\Entity\SearchIndexTask;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Repository\SearchIndexTaskRepository;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementFragmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Token\DemosToken;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use Doctrine\ORM\NonUniqueResultException;
use Exception;
use FOS\ElasticaBundle\Index\IndexManager;
use FOS\ElasticaBundle\Persister\ObjectPersister;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Tightenco\Collect\Support\Collection;

/**
 * Index Entities by using a Queue to improve application performance.
 *
 * Class SearchIndexTaskService
 */
class SearchIndexTaskService extends CoreService
{
    public const ES_INDEX_STATEMENT = 'statements';
    public const ES_INDEX_STATEMENT_FRAGMENT = 'statementFragments';
    public const ES_INDEX_STATEMENT_SEGMENT = 'statementSegments';

    /**
     * @var ObjectPersister
     */
    protected $statementPersister;
    /**
     * @var ObjectPersister
     */
    protected $statementFragmentPersister;

    /** @var Collection SearchIndexTask */
    protected $failedIndexTasks;

    /** @var TokenStorageInterface */
    protected $tokenStorage;

    /** @var IndexManager */
    protected $indexManager;

    /**
     * @var string
     */
    private $environment;

    /**
     * @var SearchIndexTaskRepository
     */
    private $searchIndexTaskRepository;
    /**
     * @var ObjectPersister
     */
    private $statementSegmentPersister;
    /**
     * @var StatementFragmentRepository
     */
    private $statementFragmentRepository;
    /**
     * @var StatementRepository
     */
    private $statementRepository;
    /**
     * @var SegmentRepository
     */
    private $segmentRepository;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    public function __construct(
        GlobalConfigInterface $globalConfig,
        IndexManager $indexManager,
        ObjectPersister $statementFragmentPersister,
        ObjectPersister $statementPersister,
        ObjectPersister $statementSegmentPersister,
        SearchIndexTaskRepository $searchIndexTaskRepository,
        SegmentRepository $segmentRepository,
        StatementFragmentRepository $statementFragmentRepository,
        StatementRepository $statementRepository,
        TokenStorageInterface $tokenStorage,
        string $environment
    ) {
        $this->environment = $environment;
        $this->failedIndexTasks = collect();
        $this->globalConfig = $globalConfig;
        $this->indexManager = $indexManager;
        $this->searchIndexTaskRepository = $searchIndexTaskRepository;
        $this->segmentRepository = $segmentRepository;
        $this->statementFragmentPersister = $statementFragmentPersister;
        $this->statementFragmentRepository = $statementFragmentRepository;
        $this->statementPersister = $statementPersister;
        $this->statementRepository = $statementRepository;
        $this->statementSegmentPersister = $statementSegmentPersister;
        $this->tokenStorage = $tokenStorage;
    }

    /**
     * Add Entities to Queue to be Indexed asynchronously.
     *
     * @param array|string $entityIds
     *
     * @deprecated should not be used anymore as default indexing via fos elastica is used
     */
    public function addIndexTask(string $entityClass, $entityIds): void
    {
        // do not add new SearchIndexTasks as it is disabled for testing
        return;

        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        if (0 === count($entityIds)) {
            return;
        }
        try {
            $userId = null;
            $token = $this->tokenStorage->getToken();
            if ($token instanceof DemosToken && $token->getUser() instanceof User) {
                $userId = $token->getUser()->getId();
            }

            $this->searchIndexTaskRepository->addEntries($entityClass, $entityIds, $userId);
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not create SearchIndexTask', [$e, $e->getTraceAsString()]);
        }
    }

    /**
     * Delete Items directly from Elasticsearch index.
     *
     * @param array|string $entityIds
     */
    public function deleteFromIndexTask(string $entityClass, $entityIds)
    {
        if (DemosPlanKernel::ENVIRONMENT_TEST === $this->environment) {
            return;
        }
        if (!is_array($entityIds)) {
            $entityIds = [$entityIds];
        }
        try {
            switch ($entityClass) {
                case Statement::class:
                    $this->deleteStatementsFromIndex($entityIds);
                    break;
                case StatementFragment::class:
                    $this->deleteStatementFragmentsFromIndex($entityIds);
                    break;
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could not delete from Index SearchIndexTask', [$e]);
        }
    }

    /**
     * Get Tasks from Index Queue and send them to Elasticsearch.
     *
     * @param string|null $entityClass
     *
     * @deprecated should not be used anymore as default indexing via fos elastica is used
     */
    public function refreshIndex($entityClass = null)
    {
        // the reindexing is disabled as we want to test, whether the default
        // indexing via fos elastica listener works and does not lead to
        // performance issues. When it does, we can reenable this code
        return;
        try {
            $this->logStatus();
            $itemsToIndex = $this->searchIndexTaskRepository->getItemsToIndex($entityClass);
            if (0 < count($itemsToIndex)) {
                $this->indexItems($itemsToIndex);
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could index Search Items', [$e]);
        }

        // check for Errors during Elasticsearch indexing and save Entries to database
        // to avoid loss of Indexing
        if (0 < $this->failedIndexTasks->count()) {
            $this->failedIndexTasks->each(function ($item) {
                if ($item instanceof SearchIndexTask) {
                    // new Instance needed. Re-adding of $item does not work
                    $this->searchIndexTaskRepository->addObject(
                        new SearchIndexTask($item->getEntity(), $item->getEntityId(), $item->getUserId())
                    );
                }
            });
        }
    }

    /**
     * @param string|null $userId
     */
    public function hasUserPendingSearchTasks($userId): bool
    {
        try {
            $pendingIndexTasks = $this->searchIndexTaskRepository->findBy(['userId' => $userId]);
            if (0 < count($pendingIndexTasks)) {
                return true;
            }
        } catch (Exception $e) {
            $this->getLogger()->warning('Could get pending user Search Items', [$e]);
        }

        return false;
    }

    /**
     * Log IndexQueue Status to be able to monitor Bottlenecks.
     *
     * @throws NonUniqueResultException
     */
    public function logStatus()
    {
        if ($this->globalConfig->isElasticsearchAsyncIndexing() &&
            $this->globalConfig->isElasticsearchAsyncIndexingLogStatus()) {
            $logPath = DemosPlanPath::getProjectPath('app/logs');
            $fh = fopen($logPath.'/searchIndexQueueStatus'.date('Y-m-d').'.log', 'ab');
            $status = $this->searchIndexTaskRepository->getQueueStatus();
            fwrite($fh, date('Y-m-d H:i:s').';'.$status->getTotal().';'.$status->getProcessing()."\n");
            fclose($fh);
        }
    }

    /**
     * Perform Search Task and delete Items afterwards.
     *
     * @param SearchIndexTask[] $items
     */
    protected function indexItems($items)
    {
        $itemsCollection = collect($items);

        $groups = $itemsCollection->groupBy(static function ($item) {
            /* @var SearchIndexTask $item */
            return $item->getEntity();
        });

        // refresh items by entity type
        $groups->each(function (Collection $items, $key) {
            switch ($key) {
                case Statement::class:
                    $this->indexStatementItems($items);
                    break;
                case StatementFragment::class:
                    $this->indexStatementFragmentItems($items);
                    break;
                case Segment::class:
                    $this->indexStatementSegmentItems($items);
                    break;
                default:
                    $this->getLogger()->warning('Could not index Entity ', [$key]);
                    // clean up not existing entities
                    $this->searchIndexTaskRepository->deleteItems($items);
            }
        });
    }

    /**
     * @param Collection $items
     *
     * @throws Exception
     */
    protected function indexStatementItems($items)
    {
        try {
            $statementIds = $this->getEntityIds($items);

            // in some rare cases $statementIds might be empty
            if ($statementIds->isEmpty()) {
                return;
            }

            $itemsToIndex = $this->statementRepository->getStatements($statementIds->toArray());
            // in some rare cases items to index might be empty
            if (!is_array($itemsToIndex) || 0 === count($itemsToIndex)) {
                return;
            }
            if (DemosPlanKernel::ENVIRONMENT_TEST !== $this->environment) {
                $this->statementPersister->insertMany($itemsToIndex);
                $this->refreshElasticsearchIndex(self::ES_INDEX_STATEMENT);
            }

            // delete Search index tasks
            $this->searchIndexTaskRepository->deleteItems($items);
        } catch (Exception $e) {
            // catch exception here to not interfere with other indexing tasks
            // save tasks to be added to queue again
            $this->addFailedIndexTasks($items);
            $this->getLogger()->warning('Could not index Search Statement Items', [$e]);
        }
    }

    private function indexStatementSegmentItems(Collection $items): void
    {
        try {
            $segmentIds = $this->getEntityIds($items);

            // in some rare cases $segmentIds might be empty
            if ($segmentIds->isEmpty()) {
                return;
            }

            $itemsToIndex = $this->segmentRepository->findByIds($segmentIds->toArray());
            // in some rare cases items to index might be empty
            if (!is_array($itemsToIndex) || 0 === count($itemsToIndex)) {
                return;
            }
            if (DemosPlanKernel::ENVIRONMENT_TEST !== $this->environment) {
                $this->statementSegmentPersister->insertMany($itemsToIndex);
                $this->refreshElasticsearchIndex(self::ES_INDEX_STATEMENT_SEGMENT);
            }

            // delete Search index tasks
            $this->searchIndexTaskRepository->deleteItems($items);
        } catch (Exception $e) {
            // catch exception here to not interfere with other indexing tasks
            // save tasks to be added to queue again
            $this->addFailedIndexTasks($items);
            $this->getLogger()->warning('Could not index Search StatementSegment Items', [$e]);
        }
    }

    /**
     * @param Collection $items
     */
    protected function indexStatementFragmentItems($items)
    {
        try {
            $statementFragmentIds = $this->getEntityIds($items);

            $itemsToIndex = $this->statementFragmentRepository->getStatementFragments($statementFragmentIds->toArray());
            // in some rare cases items to index might be empty
            if (!is_array($itemsToIndex) || 0 === count($itemsToIndex)) {
                return;
            }
            if (DemosPlanKernel::ENVIRONMENT_TEST !== $this->environment) {
                $this->statementFragmentPersister->insertMany($itemsToIndex);
                $this->refreshElasticsearchIndex(self::ES_INDEX_STATEMENT_FRAGMENT);
            }

            // delete Search index tasks
            $this->searchIndexTaskRepository->deleteItems($items);
        } catch (Exception $e) {
            // catch exception here to not interfere with other indexing tasks
            // save tasks to be added to queue again
            $this->addFailedIndexTasks($items);
            $this->getLogger()->warning('Could not index Search StatementFragment Items', [$e]);
        }
    }

    /**
     * Delete Statements immediately from Elasticsearch index.
     *
     * @param array $ids
     */
    protected function deleteStatementsFromIndex($ids)
    {
        $this->statementPersister->deleteManyByIdentifiers($ids);
        $this->refreshElasticsearchIndex(self::ES_INDEX_STATEMENT);
    }

    /**
     * Delete StatementFragments immediately from Elasticsearch index.
     *
     * @param array $ids
     */
    protected function deleteStatementFragmentsFromIndex($ids)
    {
        $this->statementFragmentPersister->deleteManyByIdentifiers($ids);
        $this->refreshElasticsearchIndex(self::ES_INDEX_STATEMENT_FRAGMENT);
    }

    /**
     * Save failed index tasks to be rewritten ta queue later.
     *
     * @param Collection $searchIndexTasks
     */
    protected function addFailedIndexTasks($searchIndexTasks)
    {
        $this->failedIndexTasks = $this->failedIndexTasks->merge(collect($searchIndexTasks));
    }

    /**
     * Ensure that Elasticsearch index is refreshed before delivering new results.
     *
     * @param string $index
     */
    protected function refreshElasticsearchIndex($index)
    {
        $this->indexManager->getIndex($index)->refresh();
    }

    private function getEntityIds(Collection $items): Collection
    {
        return $items->map(static function ($item) {
            /* @var $item SearchIndexTask */
            return $item->getEntityId();
        });
    }
}
