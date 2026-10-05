<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\StatementSegment;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use Tests\Base\AbstractApiTest;

/**
 * Sorting (`order[...]`) and pagination of GET /api/3.0/StatementSegment.
 */
class StatementSegmentSortingPaginationApiTest extends AbstractApiTest
{
    use StatementSegmentApiTestTrait;

    public function testGetCollectionIsSortedByOrderInProcedureAscending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $third = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 3]);
        $first = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 1]);
        $second = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 2]);

        $ids = $this->requestCollectionIds('order[orderInProcedure]=asc', $procedure);

        self::assertSame([$first->getId(), $second->getId(), $third->getId()], $ids);
    }

    public function testGetCollectionIsSortedByOrderInProcedureDescending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $first = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 1]);
        $third = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 3]);
        $second = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 2]);

        $ids = $this->requestCollectionIds('order[orderInProcedure]=desc', $procedure);

        self::assertSame([$third->getId(), $second->getId(), $first->getId()], $ids);
    }

    public function testGetCollectionIsSortedByDeadlineAscending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $march = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-03-01')]);
        $january = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-01-01')]);
        $february = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-02-01')]);

        $ids = $this->requestCollectionIds('order[deadline]=asc', $procedure);

        self::assertSame([$january->getId(), $february->getId(), $march->getId()], $ids);
    }

    public function testGetCollectionIsSortedByDeadlineDescending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $january = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-01-01')]);
        $march = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-03-01')]);
        $february = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-02-01')]);

        $ids = $this->requestCollectionIds('order[deadline]=desc', $procedure);

        self::assertSame([$march->getId(), $february->getId(), $january->getId()], $ids);
    }

    /**
     * Where NULLs end up is up to the database, so only "nothing is dropped" and the relative
     * order of the dated segments are asserted.
     */
    public function testGetCollectionSortedByDeadlineKeepsSegmentsWithoutDeadline(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $later = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-02-01')]);
        $withoutDeadline = $this->createSegmentInProcedure($procedure, ['deadline' => null]);
        $earlier = $this->createSegmentInProcedure($procedure, ['deadline' => new DateTime('2030-01-01')]);

        $ids = $this->requestCollectionIds('order[deadline]=asc', $procedure);

        self::assertCount(3, $ids);
        self::assertContains($withoutDeadline->getId(), $ids);
        self::assertLessThan(
            array_search($later->getId(), $ids, true),
            array_search($earlier->getId(), $ids, true)
        );
    }

    /**
     * SegmentDeterministicOrderExtension appends `id ASC` as tiebreaker: with identical sort keys
     * the pages must still neither repeat nor drop a segment.
     */
    public function testGetCollectionBreaksSortTiesById(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $expectedIds = [];
        for ($i = 0; $i < 4; ++$i) {
            $expectedIds[] = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 1])->getId();
        }
        sort($expectedIds);
        $query = 'order[orderInProcedure]=asc&pagination=true&itemsPerPage=2&page=';

        $pageOne = $this->requestCollectionIds($query.'1', $procedure);
        $pageTwo = $this->requestCollectionIds($query.'2', $procedure);

        self::assertSame($expectedIds, array_merge($pageOne, $pageTwo));
    }

    public function testGetCollectionIsPaginated(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $first = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 1]);
        $second = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 2]);
        $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 3]);

        $document = $this->requestCollection(
            'order[orderInProcedure]=asc&pagination=true&page=1&itemsPerPage=2',
            $procedure
        );

        self::assertSame(3, $document['meta']['totalItems']);
        self::assertSame([$first->getId(), $second->getId()], array_column($document['data'], 'id'));
    }

    public function testGetCollectionSecondPageContinuesWherePageOneEnded(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 1]);
        $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 2]);
        $third = $this->createSegmentInProcedure($procedure, ['orderInProcedure' => 3]);

        $ids = $this->requestCollectionIds(
            'order[orderInProcedure]=asc&pagination=true&page=2&itemsPerPage=2',
            $procedure
        );

        self::assertSame([$third->getId()], $ids);
    }

    public function testGetCollectionReturnsEmptyPagePastTheLastPage(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $this->createSegmentInProcedure($procedure);

        $ids = $this->requestCollectionIds('pagination=true&page=5&itemsPerPage=2', $procedure);

        self::assertSame([], $ids);
    }

    /**
     * Resource::paginationMaximumItemsPerPage is 100, so asking for more must not return more.
     * All segments share one parent statement and carry minimal texts to keep the setup cheap.
     */
    public function testGetCollectionCapsItemsPerPageAtOneHundred(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $parent = StatementFactory::createOne(['procedure' => $procedure])->_real();
        for ($i = 1; $i <= 101; ++$i) {
            $this->createSegmentInProcedure($procedure, [
                'parentStatementOfSegment' => $parent,
                'orderInProcedure'         => $i,
                'text'                     => 's',
                'recommendation'           => 'r',
                'memo'                     => 'm',
            ]);
        }

        $document = $this->requestCollection(
            'order[orderInProcedure]=asc&pagination=true&page=1&itemsPerPage=500',
            $procedure
        );

        self::assertSame(101, $document['meta']['totalItems']);
        self::assertCount(100, $document['data']);
    }

    /**
     * "Schritt" sorts by the workflow position of the place, not alphabetically by its name.
     */
    public function testGetCollectionSortsByPlaceSortIndex(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $third = $this->createSegmentInPlace($procedure, 'Alpha', 3);
        $first = $this->createSegmentInPlace($procedure, 'Zeta', 1);
        $second = $this->createSegmentInPlace($procedure, 'Beta', 2);

        $ascending = $this->requestCollectionIds('order[place.sortIndex]=asc', $procedure);
        $descending = $this->requestCollectionIds('order[place.sortIndex]=desc', $procedure);

        self::assertSame([$first->getId(), $second->getId(), $third->getId()], $ascending);
        self::assertSame([$third->getId(), $second->getId(), $first->getId()], $descending);
    }

    /**
     * `order[externId]` uses natural order on the parent statement ID and then on the segment ID,
     * so "M2-1" comes before "M10-1" and "M1-2" before "M1-10" despite plain string order.
     */
    public function testGetCollectionSortsByExternIdInNaturalOrder(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $m10 = StatementFactory::createOne(['procedure' => $procedure, 'externId' => 'M10'])->_real();
        $m2 = StatementFactory::createOne(['procedure' => $procedure, 'externId' => 'M2'])->_real();
        $m1 = StatementFactory::createOne(['procedure' => $procedure, 'externId' => 'M1'])->_real();
        $m10s1 = $this->createSegmentInProcedure($procedure, ['parentStatementOfSegment' => $m10, 'externId' => 'M10-1']);
        $m2s1 = $this->createSegmentInProcedure($procedure, ['parentStatementOfSegment' => $m2, 'externId' => 'M2-1']);
        $m1s10 = $this->createSegmentInProcedure($procedure, ['parentStatementOfSegment' => $m1, 'externId' => 'M1-10']);
        $m1s2 = $this->createSegmentInProcedure($procedure, ['parentStatementOfSegment' => $m1, 'externId' => 'M1-2']);

        $ascending = $this->requestCollectionIds('order[externId]=asc', $procedure);
        $descending = $this->requestCollectionIds('order[externId]=desc', $procedure);

        self::assertSame([$m1s2->getId(), $m1s10->getId(), $m2s1->getId(), $m10s1->getId()], $ascending);
        self::assertSame([$m10s1->getId(), $m2s1->getId(), $m1s10->getId(), $m1s2->getId()], $descending);
    }

    /**
     * "Einreicher*in" sorts by the submit name of the parent statement.
     */
    public function testGetCollectionSortsBySubmitName(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $berta = $this->createSegmentWithSubmitName($procedure, 'Berta');
        $anton = $this->createSegmentWithSubmitName($procedure, 'Anton');
        $clara = $this->createSegmentWithSubmitName($procedure, 'Clara');

        $ascending = $this->requestCollectionIds('order[parentStatementOfSegment.meta.submitName]=asc', $procedure);
        $descending = $this->requestCollectionIds('order[parentStatementOfSegment.meta.submitName]=desc', $procedure);

        self::assertSame([$anton->getId(), $berta->getId(), $clara->getId()], $ascending);
        self::assertSame([$clara->getId(), $berta->getId(), $anton->getId()], $descending);
    }

    private function createSegmentWithSubmitName(Procedure $procedure, string $submitName): Segment
    {
        $parentStatement = StatementFactory::createOne(['procedure' => $procedure])->_real();
        StatementMetaFactory::createOne(['statement' => $parentStatement, 'submitName' => $submitName]);

        return $this->createSegmentInProcedure($procedure, ['parentStatementOfSegment' => $parentStatement]);
    }

    private function createSegmentInPlace(Procedure $procedure, string $placeName, int $sortIndex): Segment
    {
        $place = PlaceFactory::createOne([
            'procedure' => $procedure,
            'name'      => $placeName,
            'sortIndex' => $sortIndex,
        ])->_real();

        return $this->createSegmentInProcedure($procedure, ['place' => $place]);
    }
}
