<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<Segment>
 */
class SegmentRepository extends CoreRepository
{
    private const ORDER_IN_PROCEDURE_IS_NOT_NULL = 'segment.orderInProcedure IS NOT NULL';
    private const PARENT_STATEMENT_CONDITION = 'segment.parentStatementOfSegment = :statementId';

    /**
     * @return array<Segment>
     */
    public function findByProcedure(ProcedureInterface $procedure): array
    {
        return $this->findBy(['procedure' => $procedure]);
    }

    /**
     * @return array<Segment>
     */
    public function findAll(): array
    {
        return parent::findAll();
    }

    /**
     * Add Entityobject to database.
     *
     * @throws Exception
     */
    public function addObject(Segment $segment): Segment
    {
        try {
            $manager = $this->getEntityManager();
            $segment->setText($this->sanitize($segment->getText(), [$this->obscureTag]));
            $manager->persist($segment);
            $manager->flush();

            return $segment;
        } catch (Exception $e) {
            $this->getLogger()->warning('Add StatementObject failed Message: ', [$e]);
            throw $e;
        }
    }

    public function obscureText(Segment $segment): void
    {
        $segment->setText($this->sanitize($segment->getText(), [$this->obscureTag]));
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, Segment>
     */
    public function findByIds(array $ids): array
    {
        return $this->findBy(['id' => $ids]);
    }

    /**
     * Returns the subset of given IDs that belong to `$procedureId` AND
     * whose current workflow place has `locked = true`. One round-trip
     * with an explicit JOIN — the lock state is read at query time instead
     * of triggering N lazy place fetches when the caller iterates the
     * result.
     *
     * The procedure filter prevents an out-of-procedure segment ID from
     * triggering a "your batch contains locked segments" response that
     * would also leak the existence and lock state of segments belonging
     * to a different procedure.
     *
     * Note: the JOIN does not `addSelect('p')`, so the `place` association
     * on each returned `Segment` remains a lazy proxy. Fine for the
     * current caller (which only counts the result); future callers that
     * need to walk `$segment->getPlace()->...` should add the select to
     * avoid reintroducing N+1.
     *
     * Used by {{ @see SegmentBulkEditorService::findLockedSegments }} to
     * pre-validate batches against the segment-lock feature.
     *
     * @param list<string> $ids
     *
     * @return list<Segment>
     */
    public function findLockedByIds(array $ids, string $procedureId): array
    {
        if ([] === $ids) {
            return [];
        }

        return $this->createQueryBuilder('s')
            ->innerJoin('s.place', 'p')
            ->where('s.id IN (:ids)')
            ->andWhere('s.procedure = :procedureId')
            ->andWhere('p.locked = true')
            ->setParameter('ids', $ids)
            ->setParameter('procedureId', $procedureId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Returns the highest number in field orderInProcedure filtered by procedure id.
     */
    public function getLastSortedSegmentNumber(string $procedureId): int
    {
        $manager = $this->getEntityManager();
        $query = $manager->createQueryBuilder()
            ->select('segment.orderInProcedure')
            ->from(Segment::class, 'segment')
            ->where('segment.orderInProcedure IS NOT NULL')
            ->andWhere('segment.procedure = :procedureId')->setParameter('procedureId', $procedureId)
            ->addOrderBy('segment.orderInProcedure', 'DESC')
            ->setMaxResults(1)
            ->getQuery();
        $segments = $query->getResult();

        return 0 === (is_countable($segments) ? count($segments) : 0) ? 0 : $segments[0]['orderInProcedure'];
    }

    /**
     * @throws Exception
     */
    public function get(string $entityId): ?Segment
    {
        return $this->findOneBy(['id' => $entityId]);
    }

    /**
     * Find all segments that have a custom field with the given ID.
     *
     * @return array<Segment>
     */
    public function findSegmentsWithCustomField(string $customFieldId): array
    {
        $qb = $this->getEntityManager()->createQueryBuilder();

        // Escape JSON-breaking characters to prevent injection
        $escapedCustomFieldId = str_replace(['\\', '"'], ['\\\\', '\\"'], $customFieldId);
        $searchPattern = '%"id":"'.$escapedCustomFieldId.'"%';

        return $qb
            ->select('segment')
            ->from(Segment::class, 'segment')
            ->where('segment.customFields IS NOT NULL')
            ->andWhere('segment.customFields LIKE :customFieldSearch')
            ->setParameter('customFieldSearch', $searchPattern)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the position of a segment within its parent statement.
     *
     * @return array{segmentId: string, position: int, total: int}|null Returns null if segment not found
     */
    public function getSegmentPosition(string $segmentId, string $statementId): ?array
    {
        $em = $this->getEntityManager();

        // First, get the target segment's orderInProcedure
        $targetQuery = $em->createQueryBuilder()
            ->select('segment.id', 'segment.orderInProcedure')
            ->from(Segment::class, 'segment')
            ->where('segment.id = :segmentId')
            ->andWhere(self::PARENT_STATEMENT_CONDITION)
            ->setParameter('segmentId', $segmentId)
            ->setParameter('statementId', $statementId)
            ->getQuery();

        $targetResult = $targetQuery->getOneOrNullResult();

        if (null === $targetResult) {
            return null;
        }

        $targetOrder = $targetResult['orderInProcedure'];

        // Count how many segments in this statement have orderInProcedure <= target
        $positionQuery = $em->createQueryBuilder()
            ->select('COUNT(segment.id)')
            ->from(Segment::class, 'segment')
            ->where(self::PARENT_STATEMENT_CONDITION)
            ->andWhere(self::ORDER_IN_PROCEDURE_IS_NOT_NULL)
            ->andWhere('segment.orderInProcedure <= :targetOrder')
            ->setParameter('statementId', $statementId)
            ->setParameter('targetOrder', $targetOrder)
            ->getQuery();

        $position = (int) $positionQuery->getSingleScalarResult();

        // Get total count of segments in this statement
        $totalQuery = $em->createQueryBuilder()
            ->select('COUNT(segment.id)')
            ->from(Segment::class, 'segment')
            ->where(self::PARENT_STATEMENT_CONDITION)
            ->andWhere(self::ORDER_IN_PROCEDURE_IS_NOT_NULL)
            ->setParameter('statementId', $statementId)
            ->getQuery();

        $total = (int) $totalQuery->getSingleScalarResult();

        return [
            'segmentId' => $segmentId,
            'position'  => $position,
            'total'     => $total,
        ];
    }

    /**
     * Change the recommendation in all segments with the given ID *if* they are in the given procedure.
     *
     * @param array<int, string> $segmentIds
     * @param bool               $attach     use true to attach the given text to the existing recommendation, otherwise it will be replaced
     */
    public function editSegmentRecommendations(array $segmentIds, string $procedureId, string $recommendationText, bool $attach): void
    {
        if ([] === $segmentIds) {
            return;
        }

        if ($attach && '' === $recommendationText) {
            return;
        }

        $qb = $this->getEntityManager()->createQueryBuilder();
        $value = $attach
            ? $qb->expr()->concat('segment.recommendation', ':recommendationText')
            : ':recommendationText';

        $query = $qb
            ->update(Segment::class, 'segment')
            ->set('segment.recommendation', $value)
            ->where($qb->expr()->in('segment.id', $segmentIds))
            ->andWhere('segment.procedure = :procedureId')
            ->setParameter('procedureId', $procedureId)
            ->setParameter('recommendationText', $recommendationText)
            ->getQuery();

        $query->execute();

        // The query above does not update the UnitOfWork. If that is used (for example to refresh elasticsearch),
        // the new changes wont apply. So we have to refresh the UnitofWorks for changed entities.
        foreach ($segmentIds as $segmentId) {
            try {
                $segment = $this->getEntityManager()->getReference(Segment::class, $segmentId);
            } catch (ORMException) {
                $segment = $this->find($segmentId);
            }
            $this->getEntityManager()->refresh($segment);
        }
    }

    /**
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function deleteSegmentObject(Segment $segment): void
    {
        $em = $this->getEntityManager();
        $em->remove($segment);
        $em->flush();
    }
}
