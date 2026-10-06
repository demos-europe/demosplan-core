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
}
