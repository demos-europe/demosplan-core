<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use demosplan\DemosPlanCoreBundle\ValueObject\PercentageDistribution;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcedureStatisticsService
{
    public function __construct(
        private readonly SegmentRepository $segmentRepository,
        private readonly StatementRepository $statementRepository,
        private readonly EntityManagerInterface $entityManager
    ) {
    }

    public function getSegmentedStatementsDistribution(string $procedureId): PercentageDistribution
    {
        $statementsWithSegments = $this->getSegmentStatistic(
            'newStatements',
            $procedureId
        );

        $statementsWithoutSegments = $this->getSegmentStatistic(
            'inProgressSegments',
            $procedureId
        );

        $statementsWithSegmentsAndRecommendations = $this->getSegmentStatistic(
            'finishedSegments',
            $procedureId
        );

        return new PercentageDistribution(
            $statementsWithSegments + $statementsWithoutSegments,
            [
                'unsegmented'             => $statementsWithoutSegments,
                'segmented'               => $statementsWithSegments,
                'recommendationsFinished' => $statementsWithSegmentsAndRecommendations,
            ]
        );
    }

    private function getSegmentStatistic(string $statistic, string $procedureId): int
    {
        $statistic = (new OptionsResolver())
            ->setDefined(['statistic'])
            ->setAllowedValues(
                'statistic',
                [
                    'newStatements',
                    'inProgressSegments',
                    'finishedSegments',
                ]
            )->resolve(['statistic' => $statistic])['statistic'];

        $segmentQuery = $this->segmentRepository->createQueryBuilder('seg')
            ->select('IDENTITY(seg.parentStatementOfSegment)')
            ->join('seg.place', 'place')
            ->where('seg.procedure = :procedureId')
            ->andWhere('seg.parentStatementOfSegment = s.id')
            ->setParameter('procedureId', $procedureId);

        $qb = $this->statementRepository->createQueryBuilder('s');
        $query = $qb
            ->select('COUNT(s.id)')
            ->where('s.procedure = :procedureId')
            ->andWhere('s.original IS NOT NULL')
            ->andWhere('s NOT INSTANCE OF '.Segment::class)
            ->setParameter('procedureId', $procedureId);

        if ('newStatements' === $statistic) {
            $query->andWhere(
                $qb->expr()
                    ->in('s.id', $segmentQuery->getDQL())
            );
        }

        if ('inProgressSegments' === $statistic) {
            $query->andWhere(
                $qb->expr()
                    ->notIn('s.id', $segmentQuery
                        ->andWhere('place.solved = 0')
                        ->getDQL())
            );
        }

        if ('finishedSegments' === $statistic) {
            $query->andWhere(
                $qb->expr()
                    ->in(
                        's.id',
                        $segmentQuery
                            ->andWhere('place.solved = 1')
                            ->getDQL()
                    )
            );
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
