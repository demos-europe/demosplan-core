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

use DateInterval;
use DateTime;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
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

    public function testReturnsAssignedSegmentDueInOneWeek(): void
    {
        $assignee = UserFactory::createOne();
        $segment = $this->createSegmentWithDeadline($assignee, $this->todayPlus('P7D'));

        $result = $this->sut->findSegmentsForAssigneesByDeadlineInterval(new DateInterval('P7D'));

        self::assertArrayHasKey($assignee->getId(), $result);
        self::assertCount(1, $result[$assignee->getId()]);
        self::assertSame($segment->getId(), $result[$assignee->getId()][0]->getId());
    }

    public function testReturnsAssignedSegmentDueOnDeadlineDay(): void
    {
        $assignee = UserFactory::createOne();
        $segment = $this->createSegmentWithDeadline($assignee, $this->todayPlus('P0D'));

        $result = $this->sut->findSegmentsForAssigneesByDeadlineInterval(new DateInterval('P0D'));

        self::assertArrayHasKey($assignee->getId(), $result);
        self::assertSame($segment->getId(), $result[$assignee->getId()][0]->getId());
    }

    public function testExcludesSegmentWithDifferentDeadline(): void
    {
        $assignee = UserFactory::createOne();
        $segment = $this->createSegmentWithDeadline($assignee, $this->todayPlus('P1D'));

        $result = $this->sut->findSegmentsForAssigneesByDeadlineInterval(new DateInterval('P7D'));

        self::assertArrayNotHasKey($assignee->getId(), $result);
        $this->assertResultHasNoSegment($result, $segment->getId());
    }

    public function testExcludesDeletedSegment(): void
    {
        $assignee = UserFactory::createOne();
        $this->createSegmentWithDeadline($assignee, $this->todayPlus('P7D'), true);

        $result = $this->sut->findSegmentsForAssigneesByDeadlineInterval(new DateInterval('P7D'));

        self::assertArrayNotHasKey($assignee->getId(), $result);
    }

    public function testExcludesSegmentWithoutAssignee(): void
    {
        $segment = $this->createSegmentWithDeadline(null, $this->todayPlus('P7D'));

        $result = $this->sut->findSegmentsForAssigneesByDeadlineInterval(new DateInterval('P7D'));

        $this->assertResultHasNoSegment($result, $segment->getId());
    }

    public function testGroupsSegmentsByAssignee(): void
    {
        $deadline = $this->todayPlus('P7D');
        $firstAssignee = UserFactory::createOne();
        $secondAssignee = UserFactory::createOne();
        $this->createSegmentWithDeadline($firstAssignee, $deadline);
        $this->createSegmentWithDeadline($firstAssignee, $deadline);
        $this->createSegmentWithDeadline($secondAssignee, $deadline);

        $result = $this->sut->findSegmentsForAssigneesByDeadlineInterval(new DateInterval('P7D'));

        self::assertCount(2, $result[$firstAssignee->getId()]);
        self::assertCount(1, $result[$secondAssignee->getId()]);
    }

    /**
     * @param array<string, list<Segment>> $result
     */
    private function assertResultHasNoSegment(array $result, string $segmentId): void
    {
        $returnedSegmentIds = [];
        foreach ($result as $segments) {
            foreach ($segments as $segment) {
                $returnedSegmentIds[] = $segment->getId();
            }
        }

        self::assertNotContains($segmentId, $returnedSegmentIds);
    }

    private function todayPlus(string $intervalSpec): DateTime
    {
        return (new DateTime('today'))->add(new DateInterval($intervalSpec));
    }

    private function createSegmentWithDeadline(?object $assignee, DateTime $deadline, bool $deleted = false): Segment
    {
        $attributes = [
            'deadline' => $deadline,
            'deleted'  => $deleted,
        ];
        if (null !== $assignee) {
            $attributes['assignee'] = $assignee;
        }

        return SegmentFactory::createOne($attributes)->_real();
    }
}
