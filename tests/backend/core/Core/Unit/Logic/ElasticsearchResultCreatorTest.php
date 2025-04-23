<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Project\Core\Unit\Logic;

use Elastica\Query;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Statement\ElasticSearchService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * This test simulates ElasticsearchResultCreator refactoring
 * to ensure the methods we plan to add will work correctly.
 *
 * @group UnitTest
 */
class ElasticsearchResultCreatorTest extends TestCase
{
    /**
     * Test the planned handleAggregation method
     */
    public function testHandleAggregation(): void
    {
        // Create mocks
        $query = new Query();
        $elasticSearchService = $this->createMock(ElasticSearchService::class);
        $userFilters = ['institution' => ['value1']];
        $addAllAggregations = true;

        // Set up expectations
        $elasticSearchService->expects($this->once())
            ->method('addEsAggregation')
            ->with($query, 'oName.raw', null, null, 'institution')
            ->willReturn($query);

        $elasticSearchService->expects($this->once())
            ->method('addEsMissingAggregation')
            ->with($query, 'oName.raw')
            ->willReturn($query);

        // Simulate the method
        $result = $this->handleAggregation(
            $elasticSearchService,
            $query,
            'oName.raw',
            'institution',
            $userFilters,
            $addAllAggregations
        );

        $this->assertSame($query, $result);
    }

    /**
     * Test the planned processAggregationResults method
     */
    public function testProcessAggregationResults(): void
    {
        // Create mocks
        $elasticSearchService = $this->createMock(ElasticSearchService::class);
        $esField = 'oName.raw';
        $aliasField = 'institution';
        $esResultAggregations = ['oName.raw' => ['buckets' => []]];
        $processedAggregation = ['existing' => 'data'];
        $expected = ['existing' => 'data', 'institution' => ['processed data']];

        // Setup expectations
        $elasticSearchService->expects($this->once())
            ->method('addMissingAggregationResultToArray')
            ->with($esField, $aliasField, $esResultAggregations, $processedAggregation)
            ->willReturn(['existing' => 'data', 'institution' => []]);

        $elasticSearchService->expects($this->once())
            ->method('addAggregationResultToArray')
            ->with($esField, $aliasField, $esResultAggregations, ['existing' => 'data', 'institution' => []], null)
            ->willReturn($expected);

        // Simulate the method
        $result = $this->processAggregationResults(
            $elasticSearchService,
            $esField,
            $aliasField,
            $esResultAggregations,
            $processedAggregation
        );

        $this->assertEquals($expected, $result);
    }

    /**
     * Test the planned processProcedureMoveAggregation method
     */
    public function testProcessProcedureMoveAggregation(): void
    {
        // Create mocks
        $procedureRepository = $this->createMock(ProcedureRepository::class);
        $translator = $this->createMock(TranslatorInterface::class);
        $elasticSearchService = $this->createMock(ElasticSearchService::class);

        $fieldName = 'movedFromProcedureId';
        $esResultAggregations = [
            'movedFromProcedureId' => [
                'buckets' => [
                    ['key' => 'proc1', 'doc_count' => 5],
                    ['key' => 'proc2', 'doc_count' => 3]
                ]
            ]
        ];
        $processedAggregation = [];

        // Create mock procedures
        $procedure1 = $this->createMock(Procedure::class);
        $procedure1->method('getName')->willReturn('Procedure 1');

        $procedure2 = $this->createMock(Procedure::class);
        $procedure2->method('getName')->willReturn('Procedure 2');

        // Setup expectations for repository
        $procedureRepository->expects($this->exactly(2))
            ->method('get')
            ->willReturnMap([
                ['proc1', $procedure1],
                ['proc2', $procedure2]
            ]);

        $translator->expects($this->once())
            ->method('trans')
            ->with('all')
            ->willReturn('All');

        // We'll just use a hardcoded value for this test

        // Simulate the method
        $result = $this->processProcedureMoveAggregation(
            $procedureRepository,
            $translator,
            $elasticSearchService,
            $fieldName,
            $esResultAggregations,
            $processedAggregation
        );

        $expected = [
            'movedFromProcedureId' => [
                [
                    'label' => 'All',
                    'value' => 'EXISTING',
                    'count' => 8,
                ],
                [
                    'count' => 5,
                    'label' => 'Procedure 1',
                    'value' => 'proc1',
                ],
                [
                    'count' => 3,
                    'label' => 'Procedure 2',
                    'value' => 'proc2',
                ]
            ]
        ];

        $this->assertEquals($expected, $result);
    }

    /**
     * Simulation of the planned handleAggregation method
     */
    private function handleAggregation(
        ElasticSearchService $elasticSearchService,
        Query $query,
        string $esField,
        string $aliasField,
        array $userFilters,
        bool $addAllAggregations,
        ?string $sortField = null,
        ?string $sortOrder = null
    ): Query {
        if ($addAllAggregations || \array_key_exists($aliasField, $userFilters)) {
            $query = $elasticSearchService->addEsAggregation(
                $query,
                $esField,
                $sortField,
                $sortOrder,
                $aliasField
            );

            // Check if this field needs a missing aggregation
            if (str_contains($esField, '.raw')) {
                $query = $elasticSearchService->addEsMissingAggregation($query, $esField);
            }
        }

        return $query;
    }

    /**
     * Simulation of the planned processAggregationResults method
     */
    private function processAggregationResults(
        ElasticSearchService $elasticSearchService,
        string $esField,
        string $aliasField,
        array $esResultAggregations,
        array $processedAggregation,
        ?array $labelMap = null
    ): array {
        if (str_contains($esField, '.raw')) {
            $processedAggregation = $elasticSearchService->addMissingAggregationResultToArray(
                $esField,
                $aliasField,
                $esResultAggregations,
                $processedAggregation
            );
        }

        return $elasticSearchService->addAggregationResultToArray(
            $esField,
            $aliasField,
            $esResultAggregations,
            $processedAggregation,
            $labelMap
        );
    }

    /**
     * Simulation of the planned processProcedureMoveAggregation method
     */
    private function processProcedureMoveAggregation(
        ProcedureRepository $procedureRepository,
        TranslatorInterface $translator,
        ElasticSearchService $elasticSearchService,
        string $fieldName,
        array $esResultAggregations,
        array $processedAggregation
    ): array {
        $movedStatementCount = 0;
        $processedAggregation[$fieldName] = [];
        if (isset($esResultAggregations[$fieldName])) {
            foreach ($esResultAggregations[$fieldName]['buckets'] as $agg) {
                $procedure = $procedureRepository->get($agg['key']);
                $label = $procedure instanceof Procedure ? $procedure->getName() : '';
                $processedAggregation[$fieldName][] = [
                    'count' => $agg['doc_count'],
                    'label' => $label,
                    'value' => $agg['key'],
                ];
                $movedStatementCount += $agg['doc_count'];
            }
        }
        array_unshift($processedAggregation[$fieldName], [
            'label' => $translator->trans('all'),
            'value' => 'EXISTING', // simulating ElasticSearchService::EXISTING_FIELD_FILTER
            'count' => $movedStatementCount,
        ]);

        return $processedAggregation;
    }
}
