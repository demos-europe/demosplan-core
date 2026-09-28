<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use demosplan\DemosPlanCoreBundle\Logic\Statement\ElasticSearchService;
use Elastica\Aggregation\Terms;
use Elastica\Query;
use Elastica\Response;
use Elastica\ResultSet;
use Elastica\SearchableInterface;
use Tests\Base\UnitTestCase;

/**
 * Verifies that ElasticSearchService::fetchAllHitsViaSearchAfter accumulates every hit, advances
 * the search_after cursor, captures aggregations once, and reproduces the legacy response shape.
 */
class ElasticSearchServiceSearchAfterTest extends UnitTestCase
{
    /** @var ElasticSearchService */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(ElasticSearchService::class);
    }

    public function testFetchAllHitsViaSearchAfterAccumulatesAllBatches(): void
    {
        $batchSize = 2;
        $aggregations = ['publicStatement' => ['buckets' => [['key' => 'x', 'doc_count' => 5]]]];

        // 3 batches: [2, 2, 1] hits → 5 total; the last (partial) batch terminates the loop.
        $resultSets = [
            $this->buildResultSet([['_id' => 'a', 'sort' => [1, 'a']], ['_id' => 'b', 'sort' => [2, 'b']]], 5, $aggregations),
            $this->buildResultSet([['_id' => 'c', 'sort' => [3, 'c']], ['_id' => 'd', 'sort' => [4, 'd']]], 5, []),
            $this->buildResultSet([['_id' => 'e', 'sort' => [5, 'e']]], 5, []),
        ];

        // The query is mutated in place between batches, so snapshot the params at each call.
        $paramSnapshots = [];
        $search = $this->createMock(SearchableInterface::class);
        $search->method('search')->willReturnCallback(
            static function (Query $query) use (&$paramSnapshots, &$resultSets): ResultSet {
                $paramSnapshots[] = $query->getParams();

                return array_shift($resultSets);
            }
        );

        // Query carries an aggregation so we can assert it is dropped on follow-up batches.
        $query = new Query();
        $query->addAggregation((new Terms('publicStatement'))->setField('publicStatement'));

        $output = $this->sut->fetchAllHitsViaSearchAfter($search, $query, $batchSize);

        // All hits accumulated, in order.
        $hits = $output['result']['hits']['hits'];
        static::assertCount(5, $hits);
        static::assertSame(['a', 'b', 'c', 'd', 'e'], array_column($hits, '_id'));

        // Total comes from the first batch.
        static::assertSame(5, $output['result']['hits']['total']['value']);

        // Aggregations captured once, from the first batch.
        static::assertSame($aggregations, $output['aggregations']);

        // Exactly three requests were issued.
        static::assertCount(3, $paramSnapshots);

        // Common query setup applied on every batch.
        foreach ($paramSnapshots as $params) {
            static::assertSame($batchSize, $params['size']);
            static::assertSame(0, $params['from']);
            static::assertTrue($params['track_total_hits']);
            static::assertContains(['id' => 'asc'], $params['sort'], 'id tiebreaker must be present');
        }

        // First batch: aggregations requested, no cursor yet.
        static::assertArrayHasKey('aggs', $paramSnapshots[0]);
        static::assertArrayNotHasKey('search_after', $paramSnapshots[0]);

        // Follow-up batches: cursor = previous batch last hit sort, aggregations dropped.
        static::assertSame([2, 'b'], $paramSnapshots[1]['search_after']);
        static::assertArrayNotHasKey('aggs', $paramSnapshots[1]);
        static::assertSame([4, 'd'], $paramSnapshots[2]['search_after']);
        static::assertArrayNotHasKey('aggs', $paramSnapshots[2]);
    }

    public function testFetchAllHitsViaSearchAfterHandlesEmptyResult(): void
    {
        $search = $this->createMock(SearchableInterface::class);
        $search->expects(static::once())->method('search')->willReturn(
            $this->buildResultSet([], 0, [])
        );

        $output = $this->sut->fetchAllHitsViaSearchAfter($search, new Query(), 100);

        static::assertSame([], $output['result']['hits']['hits']);
        static::assertSame(0, $output['result']['hits']['total']['value']);
        static::assertSame([], $output['aggregations']);
    }

    /**
     * @param array<int, array<string, mixed>> $hits
     */
    private function buildResultSet(array $hits, int $total, array $aggregations): ResultSet
    {
        $data = [
            'hits'         => ['total' => ['value' => $total], 'hits' => $hits],
            'aggregations' => $aggregations,
        ];

        return new ResultSet(new Response($data), new Query(), []);
    }
}
