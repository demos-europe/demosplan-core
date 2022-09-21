<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use demosplan\DemosPlanCoreBundle\Entity\SearchIndexTask;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ObjectInterface;
use demosplan\DemosPlanCoreBundle\ValueObject\QueueStatus;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

class SearchIndexTaskRepository extends FluentRepository implements ObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return SearchIndexTask
     */
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    /**
     * @param string|null $entityClass
     * @param bool        $groupByUser Tasks may be grouped by users
     *
     * @return SearchIndexTask[]
     *
     * @throws Exception
     */
    public function getItemsToIndex($entityClass = null, $groupByUser = false)
    {
        // get Date in mysql formatting
        $now = date('Y-m-d H:i:s');

        $em = $this->getEntityManager();

        // use a transaction to avoid race conditions between multiple search index tasks
        $em->beginTransaction();
        try {
            $query = $em->createQueryBuilder()
                ->select('si')
                ->distinct()
                ->from(SearchIndexTask::class, 'si')
                ->andWhere('si.created <= :until')
                ->setParameter('until', $now)
                ->andWhere('si.processing = :processing')
                ->setParameter('processing', false);

            if (null !== $entityClass) {
                $query->andWhere('si.entity = :entityClass')
                    ->setParameter('entityClass', $entityClass);
            }

            /** @var SearchIndexTask[] $groupedSearchIndexTasks */
            $groupedSearchIndexTasks = $query->getQuery()
                ->execute();

            $groupedSearchIndexTasks = collect($groupedSearchIndexTasks)->unique(
                static function ($entity) use ($groupByUser) {
                    /* @var SearchIndexTask $entity */
                    if ($groupByUser) {
                        return $entity->getEntityId().$entity->getUserId();
                    }

                    return $entity->getEntityId();
                })->values()->toArray();

            // delete Search index tasks to remove duplicates
            $this->deleteUntil($now, $entityClass);

            // Group Entities by Entity type so we do a single DB access for Entity Type (not for every single entity)
            $searchIndexTasksGroupedByEntity = [];
            foreach ($groupedSearchIndexTasks as $searchIndexTask) {
                $searchIndexTasksGroupedByEntity[$searchIndexTask->getEntity()][] = $searchIndexTask->getEntityId();
            }

            // re-add unique search tasks as currently processing
            $searchTasks = [];
            foreach ($searchIndexTasksGroupedByEntity as $entityName => $searchIndexTasks) {
                $searchTasks[] = $this->addEntries(
                    $entityName,
                    $searchIndexTasks,
                    null,
                    true
                );
            }
            // use this pattern for performance reasons
            // @link https://github.com/kalessil/phpinspectionsea/blob/master/docs/performance.md#slow-array-function-used-in-loop
            $searchTasks = array_merge([], ...$searchTasks);

            // perform query
            $em->commit();

            return $searchTasks;
        } catch (Exception $e) {
            $this->logger->warning('Get Items to Index failed', [$e]);
            $em->rollback();
            throw $e;
        }
    }

    /**
     * @param string|null $userId
     * @param bool        $isProcessing
     *
     * @return SearchIndexTask[]
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function addEntries(string $entityClass, array $entityIds, $userId = null, $isProcessing = false)
    {
        $em = $this->getEntityManager();
        $entities = [];
        foreach ($entityIds as $entityId) {
            $entity = new SearchIndexTask($entityClass, $entityId, $userId, $isProcessing);
            $em->persist($entity);
            // Entity needs to be flushed immediately to be able to flush specific entity
            // Otherwise doctrine needs to calculate any managed entity which could lead to massive
            // performance issues during any kind of larger bulk editing
            $em->flush($entity);
            $entities[] = $entity;
        }

        return $entities;
    }

    /**
     * Add Entityobject to database.
     *
     * @param SearchIndexTask $entity
     *
     * @return SearchIndexTask
     *
     * @throws Exception
     */
    public function addObject($entity)
    {
        try {
            $this->getEntityManager()->persist($entity);
            $this->getEntityManager()->flush();

            return $entity;
        } catch (Exception $e) {
            $this->logger->warning('Add SearchIndexTask failed', [$e]);
            throw $e;
        }
    }

    /**
     * Update Object.
     *
     * @param SearchIndexTask $entity
     *
     * @return SearchIndexTask
     */
    public function updateObject($entity)
    {
        // Items do not need to be updated

        return $entity;
    }

    /**
     * @param iterable $entities
     *
     * @return bool false if an exception was thrown, true otherwise
     */
    public function deleteItems($entities): bool
    {
        try {
            $em = $this->getEntityManager();
            foreach ($entities as $entity) {
                $em->remove($entity);
            }
            $em->flush();

            return true;
        } catch (Exception $e) {
            $this->logger->warning('Set Items as processing failed', [$e]);

            return false;
        }
    }

    /**
     * Delete Entity.
     *
     * @param string $entityId
     *
     * @return bool
     */
    public function delete($entityId)
    {
        // no need to delete Items manually as they are fetched with
        // group by to speed up indexing and avoid useless multiple updates.

        return false;
    }

    /**
     * @param SearchIndexTask $entity
     *
     * @return bool
     */
    public function deleteObject($entity)
    {
        // no need to delete Items manually as they are fetched with
        // group by to speed up indexing and avoid useless multiple updates.
        return false;
    }

    /**
     * Delete Tasks up to a defined time.
     *
     * @param string      $until       mysql Datestring date('Y-m-d H:i:s')
     * @param string|null $entityClass
     *
     * @throws Exception
     */
    public function deleteUntil($until, $entityClass = null): bool
    {
        try {
            $em = $this->getEntityManager();

            // This could basically also be done as
            // DELETE FROM search_index_task WHERE id IN (SELECT * FROM (SELECT s0_.id FROM search_index_task s0_ WHERE s0_.created <= :until AND s0_.processing = false) as t);
            // but this seems to be not supported by DQL

            // fetch ids to delete to avoid deadlocks
            $query = $em->createQueryBuilder()
                ->select('si.id')
                ->from(SearchIndexTask::class, 'si')
                ->andWhere('si.created <= :until')
                ->setParameter('until', $until)
                ->andWhere('si.processing = :processing')
                ->setParameter('processing', false);

            if (null !== $entityClass) {
                $query->andWhere('si.entity = :entityClass')
                    ->setParameter('entityClass', $entityClass);
            }

            /** @var SearchIndexTask[] $searchIndexTasks */
            $searchIndexTasks = $query->getQuery()->execute();

            // get RowIds to delete
            $ids = collect($searchIndexTasks)->transform(static function ($searchIndexTask) {
                return is_array($searchIndexTask) && array_key_exists('id', $searchIndexTask) ?
                    $searchIndexTask['id'] : '';
            })->all();

            if (0 === count($ids)) {
                return true;
            }

            $query = $em->createQueryBuilder()
                ->delete()
                ->from(SearchIndexTask::class, 'si')
                ->where('si.id IN (:ids)')
                ->setParameter('ids', $ids);

            return $query->getQuery()->execute();
        } catch (Exception $e) {
            $this->logger->warning('Delete Items to Index failed', [$e]);
            throw $e;
        }
    }

    /**
     * @throws NonUniqueResultException
     */
    public function getQueueStatus(): QueueStatus
    {
        $em = $this->getEntityManager();
        $total = $em->createQueryBuilder()
            ->select('count(sit.id)')
            ->from(SearchIndexTask::class, 'sit')
            ->getQuery()
            ->getSingleScalarResult();

        $processing = $em->createQueryBuilder()
            ->select('count(sit.id)')
            ->from(SearchIndexTask::class, 'sit')
            ->where('sit.processing = 1')
            ->getQuery()
            ->getSingleScalarResult();

        return new QueueStatus($total, $processing);
    }

}
