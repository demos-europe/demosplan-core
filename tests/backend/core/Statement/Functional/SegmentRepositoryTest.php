<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Tests\Base\FunctionalTestCase;

class SegmentRepositoryTest extends FunctionalTestCase
{
    protected ?SegmentRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(SegmentRepository::class);
    }

    public function testFindByIdsForProcedureReturnsOnlySegmentsOfGivenProcedure(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();
        $segmentInProcedure = SegmentFactory::createOne(['procedure' => $procedure])->_real();
        $segmentOfOtherProcedure = SegmentFactory::createOne()->_real();

        // act
        $result = $this->sut->findByIdsForProcedure(
            [$segmentInProcedure->getId(), $segmentOfOtherProcedure->getId()],
            $procedure->getId()
        );

        // assert
        static::assertSame([$segmentInProcedure->getId()], $this->extractIds($result));
    }

    public function testFindByIdsForProcedureReturnsEmptyArrayForEmptyIds(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();

        // act
        $result = $this->sut->findByIdsForProcedure([], $procedure->getId());

        // assert
        static::assertSame([], $result);
    }

    public function testFindUnlockedByIdsForProcedureExcludesLockedSegments(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();
        $unlockedSegment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place'     => PlaceFactory::new(['procedure' => $procedure, 'locked' => false]),
        ])->_real();
        $lockedSegment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place'     => PlaceFactory::new(['procedure' => $procedure, 'locked' => true]),
        ])->_real();

        // act
        $result = $this->sut->findUnlockedByIdsForProcedure(
            [$unlockedSegment->getId(), $lockedSegment->getId()],
            $procedure->getId()
        );

        // assert
        static::assertSame([$unlockedSegment->getId()], $this->extractIds($result));
    }

    public function testFindUnlockedByIdsForProcedureRespectsProcedureScope(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();
        $segmentInProcedure = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place'     => PlaceFactory::new(['procedure' => $procedure, 'locked' => false]),
        ])->_real();
        $segmentOfOtherProcedure = SegmentFactory::createOne()->_real();

        // act
        $result = $this->sut->findUnlockedByIdsForProcedure(
            [$segmentInProcedure->getId(), $segmentOfOtherProcedure->getId()],
            $procedure->getId()
        );

        // assert
        static::assertSame([$segmentInProcedure->getId()], $this->extractIds($result));
    }

    /**
     * @param array<int, Segment> $segments
     *
     * @return array<int, string>
     */
    private function extractIds(array $segments): array
    {
        return array_map(static fn (Segment $segment): string => $segment->getId(), $segments);
    }
}
