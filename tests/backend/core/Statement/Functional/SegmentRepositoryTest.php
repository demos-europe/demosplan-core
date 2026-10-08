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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

class SegmentRepositoryTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    protected ?SegmentRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(SegmentRepository::class);
    }

    public function testFindSegmentsWithCustomFieldValuesReturnsIdAndValuesOfTheProcedureOnly(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $values = $this->buildCustomFieldValues([$field->getId() => $high]);
        $segmentInProcedure = SegmentFactory::createOne(['procedure' => $procedure, 'customFields' => $values])->_real();
        SegmentFactory::createOne(['customFields' => $values]); // another procedure
        SegmentFactory::createOne(['procedure' => $procedure]); // no custom field values at all

        // act
        $rows = iterator_to_array($this->sut->findSegmentsWithCustomFieldValues($procedure->getId(), [$field->getId()]), false);

        // assert
        static::assertCount(1, $rows);
        static::assertSame($segmentInProcedure->getId(), $rows[0]['id']);
        static::assertSame([$high], $rows[0]['customFields']->getOptionIds($field->getId()));
    }

    public function testFindSegmentsWithCustomFieldValuesOnlyReturnsSegmentsThatHoldEveryRequiredField(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise']);
        [$high] = $this->getOptionIds($priority);
        [$noise] = $this->getOptionIds($topics);
        SegmentFactory::createOne(['procedure' => $procedure, 'customFields' => $this->buildCustomFieldValues([$priority->getId() => $high])]);
        $holdsBoth = SegmentFactory::createOne([
            'procedure'    => $procedure,
            'customFields' => $this->buildCustomFieldValues([$priority->getId() => $high, $topics->getId() => $noise]),
        ])->_real();

        // act
        $rows = iterator_to_array(
            $this->sut->findSegmentsWithCustomFieldValues($procedure->getId(), [$priority->getId(), $topics->getId()]),
            false
        );

        // assert
        static::assertSame([$holdsBoth->getId()], array_column($rows, 'id'));
    }

    public function testFindSegmentsWithCustomFieldValuesReturnsNothingWhenNoSegmentHoldsTheField(): void
    {
        // arrange
        $procedure = ProcedureFactory::createOne()->_real();
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        SegmentFactory::createOne(['procedure' => $procedure]);

        // act
        $rows = iterator_to_array($this->sut->findSegmentsWithCustomFieldValues($procedure->getId(), [$field->getId()]), false);

        // assert
        static::assertSame([], $rows);
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
     * @param array<int, Segment> $segments
     *
     * @return array<int, string>
     */
    private function extractIds(array $segments): array
    {
        return array_map(static fn (Segment $segment): string => $segment->getId(), $segments);
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
