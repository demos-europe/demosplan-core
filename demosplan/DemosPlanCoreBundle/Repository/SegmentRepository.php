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
use Doctrine\ORM\ORMException;
use Exception;

/**
 * @template-extends CoreRepository<Segment>
 */
class SegmentRepository extends CoreRepository
{
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

        // wrap the given text in quotes so the DB handles it as string instead of an expression
        $recommendationText = "'$recommendationText'";

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
}
