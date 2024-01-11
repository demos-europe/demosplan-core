<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Repository\IRepository\ImmutableObjectInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\Query\Expr\Join;
use Exception;

/**
 * @template-extends CoreRepository<EntityContentChange>
 */
class EntityContentChangeRepository extends CoreRepository implements ImmutableObjectInterface
{
    /**
     * Get Entity by Id.
     *
     * @param string $entityId
     *
     * @return EntityContentChange|object
     */
    public function get($entityId)
    {
        return $this->find($entityId);
    }

    /**
     * @param EntityContentChange $entity
     *
     * @return EntityContentChange
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
            $this->logger->warning('Add EntityContentChange failed Message: ', [$e]);
            throw $e;
        }
    }

    /**
     * Get a descending list of EntityContentChange Objects.
     *
     * @throws Exception
     */
    public function getDescListOfObjects(EntityContentChange $oldestRelevantVersionObject)
    {
        try {
            return $this->getEntityManager()->createQueryBuilder()
                ->select('ecc.contentChange')
                ->from(EntityContentChange::class, 'ecc')
                ->where('ecc.entityId = :entityId')
                ->andWhere('ecc.entityField = :entityField')
                ->andWhere('ecc.created >= :created')
                ->setParameter('entityId', $oldestRelevantVersionObject->getEntityId())
                ->setParameter('entityField', $oldestRelevantVersionObject->getEntityField())
                ->setParameter('created', $oldestRelevantVersionObject->getCreated())
                ->orderBy('ecc.created', 'DESC')
                ->getQuery()
                ->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Failed to get list of relevant EntityContentChange Objects ', [$e]);
            throw $e;
        }
    }

    /**
     * Returns ordered array of EntityContentChanges for one specific Entity of one specific datetime.
     *
     * @return array<int, EntityContentChange>
     *
     * @throws Exception
     */
    public function findAllObjectsOfChangeInstance(EntityContentChange $oldestRelevantVersionObject): array
    {
        try {
            return $this->getEntityManager()->createQueryBuilder()
                ->select('ecc')
                ->from(EntityContentChange::class, 'ecc')
                ->where('ecc.entityId = :entityId')
                ->andWhere('ecc.created = :created')
                ->setParameter('entityId', $oldestRelevantVersionObject->getEntityId())
                ->setParameter('created', $oldestRelevantVersionObject->getCreated())
                ->getQuery()
                ->getResult();
        } catch (Exception $e) {
            $this->logger->warning('Failed to get list of relevant EntityContentChange Objects ', [$e]);
            throw $e;
        }
    }

    /**
     * Delete all EntityContentChange entries related to a specific entity Id.
     *
     * @param array<int,string> $relatedEntityIds
     * @param string            $field            field name or 'all' to delete all fields
     *
     * @throws Exception
     */
    public function deleteByEntityIds(array $relatedEntityIds, string $field): void
    {
        try {
            $queryBuilder = $this->getEntityManager()->createQueryBuilder()
                ->delete(EntityContentChange::class, 'ecc')
                ->where('ecc.entityId IN (:entityIds)')
                ->setParameter('entityIds', $relatedEntityIds);

            if ('all' !== $field) {
                $queryBuilder
                    ->andWhere('ecc.entityField = :field')
                    ->setParameter('field', $field)
                    ->getQuery()->execute();
            }
        } catch (Exception $e) {
            $this->getLogger()->error('Error on delete EntityContentChanges by EntityId ', [$e, $relatedEntityIds]);
            throw $e;
        }
    }

    /**
     * Return the (filtered) list of EntityContentChanges of a specific Entity.
     *
     * @param string     $entityId          id of Entity which entityContentChanges will be loaded
     * @param array|null $whitelistedFields List of Fields which properties/fields of the entity will be loaded.
     *                                      If $whitelistedFields is not given, entityContentChanges for all
     *                                      fields/properties will be loaded.
     *
     * @return array<int, EntityContentChange> (whitelisted) changes of Entity of given ID
     */
    public function getChangesByEntityId(string $entityId, ?array $whitelistedFields): array
    {
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('change')
            ->from(EntityContentChange::class, 'change')
            ->andWhere('change.entityId = :entityId')
            ->setParameter('entityId', $entityId)
            ->orderBy('change.created', 'DESC');

        if (null !== $whitelistedFields) {
            $queryBuilder
                ->andWhere('change.entityField IN (:whitelistedFields)')
                ->setParameter('whitelistedFields', $whitelistedFields, Connection::PARAM_STR_ARRAY);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Find all EntityContentChanges of a specific procedure.
     *
     * @param string $procedureId identifies the Procedure
     *
     * @return EntityContentChange[] entityContentChanges
     *
     * @throws Exception
     */
    public function findByProcedure(string $procedureId): array
    {
        /** @var EntityContentChange[] $entities */
        $entities = $this
            ->createQueryBuilder('ecc')
            ->leftJoin(
                Statement::class,
                's',
                Join::WITH,
                'ecc.entityId = s.id'
            )
            ->where('s.procedure = :procedure')
            ->setParameter('procedure', $procedureId)
            ->getQuery()
            ->getResult();

        return $entities;
    }

    /**
     * Deletes all EntityContentChanges of a specific procedure.
     *
     * @param string $procedureId identifies the Procedure
     *
     * @return int number of deleted EntityContentChanges
     *
     * @throws ORMException
     * @throws OptimisticLockException
     * @throws Exception
     */
    public function deleteByProcedure(string $procedureId): int
    {
        $deletedObjects = 0;
        $em = $this->getEntityManager();
        /** @var EntityContentChange[] $objectsToDelete */
        $objectsToDelete = $this->findByProcedure($procedureId);
        foreach ($objectsToDelete as $object) {
            $em->remove($object);
            ++$deletedObjects;
        }
        $em->flush();

        return $deletedObjects;
    }

    /**
     * @return array<int, Segment> the found segments, sorted by their assignees ID
     */
    public function getEntityAssigneeChangesByEntityAndStartTime(string $entityClassName, string $timeString): array
    {
        // get the segments that have a content change implying their assignee was changed since the given time
        $editedSegments = $this->getEntityManager()->createQueryBuilder()
            ->select('seg')
            ->from($entityClassName, 'seg')
            ->innerJoin(EntityContentChange::class, 'ecc', 'WITH', 'seg.id = ecc.entityId')
            ->andWhere('seg.assignee IS NOT NULL')
            ->andWhere('ecc.entityType = :entityClassName')
            ->andWhere('ecc.entityField = :entityField')
            ->andWhere('ecc.created > :time')
            ->setParameter('entityClassName', $entityClassName)
            ->setParameter('entityField', 'assignee')
            ->setParameter('time', $timeString)
            ->orderBy('seg.assignee')
            ->getQuery()
            ->getResult();

        // we also need the segments without content change, being the ones whose assignee was
        // never changed and thus no content change exists
        $existsSubQuery = $this->getEntityManager()->createQueryBuilder()
            ->select('ecc.id')
            ->from(EntityContentChange::class, 'ecc')
            ->where('ecc.entityType = :entityClassName')
            ->andWhere('ecc.entityField = :entityField')
            ->andWhere('ecc.entityId = seg.id')
            ->getQuery();

        $qb = $this->getEntityManager()->createQueryBuilder();
        $uneditedSegments = $qb
            ->select('seg')
            ->from(Segment::class, 'seg')
            ->where($qb->expr()->not($qb->expr()->exists($existsSubQuery->getDQL())))
            ->andWhere('seg.created > :time')
            ->setParameter('time', $timeString)
            ->andWhere('seg.assignee IS NOT NULL')
            ->orderBy('seg.assignee')
            ->setParameter('entityClassName', $entityClassName)
            ->setParameter('entityField', 'assignee')
            ->getQuery()
            ->getResult();

        $segments = array_merge($editedSegments, $uneditedSegments);

        usort($segments, static fn (Segment $a, Segment $b): int => strcmp($a->getAssigneeId(), $b->getAssigneeId()));

        return $segments;
    }
}
