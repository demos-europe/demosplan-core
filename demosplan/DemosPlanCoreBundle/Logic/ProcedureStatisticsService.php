<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use demosplan\DemosPlanCoreBundle\ValueObject\PercentageDistribution;
use demosplan\DemosPlanStatementBundle\Repository\StatementRepository;
use demosplan\addons\workflow\SegmentsManager\Entity\Segment;
use demosplan\addons\workflow\SegmentsManager\Repository\Segment\SegmentRepository;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcedureStatisticsService
{
    /**
     * @var StatementRepository
     */
    private $statementRepository;

    /**
     * @var SegmentRepository
     */
    private $segmentRepository;

    public function __construct(
        SegmentRepository $segmentRepository,
        StatementRepository $statementRepository
    ) {
        $this->statementRepository = $statementRepository;
        $this->segmentRepository = $segmentRepository;
    }

    public function getSegmentedStatementsDistribution(string $procedureId): PercentageDistribution
    {
        $statementsWithSegments = $this->getSegmentStatistic(
            'statementsWithSegments',
            $procedureId
        );

        $statementsWithoutSegments = $this->getSegmentStatistic(
            'statementsWithoutSegments',
            $procedureId
        );

        $statementsWithSegmentsAndRecommendations = $this->getSegmentStatistic(
            'statementsWithSegmentsAndRecommendations',
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
                'statementsWithSegments',
                'statementsWithoutSegments',
                'statementsWithSegmentsAndRecommendations',
            ]
        )->resolve(['statistic' => $statistic])['statistic'];

        $segmentQuery = $this->segmentRepository->createQueryBuilder('seg')
            ->select('IDENTITY(seg.parentStatementOfSegment)')
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

        if ('statementsWithSegments' === $statistic) {
            $query->andWhere(
                $qb->expr()
                    ->in('s.id', $segmentQuery->getDQL())
            );
        }

        if ('statementsWithoutSegments' === $statistic) {
            $query->andWhere(
                $qb->expr()
                    ->notIn('s.id', $segmentQuery->getDQL())
            );
        }

        if ('statementsWithSegmentsAndRecommendations' === $statistic) {
            $query->andWhere(
                $qb->expr()
                    ->in(
                        's.id',
                        $segmentQuery
                            ->andWhere("seg.recommendation != ''")
                            ->getDQL()
                    )
            );
        }

        return (int) $query->getQuery()->getSingleScalarResult();
    }
}
