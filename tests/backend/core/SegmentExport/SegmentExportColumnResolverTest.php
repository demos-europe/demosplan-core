<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\SegmentExport;

use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentExportColumnResolver;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SegmentExportColumnResolverTest extends TestCase
{
    protected ?SegmentExportColumnResolver $sut = null;

    private LoggerInterface&MockObject $logger;

    private array $columnsDefinition;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->sut = new SegmentExportColumnResolver($this->logger);

        // Fixed backend order, deliberately not matching any selection order used below.
        $this->columnsDefinition = [
            ['key' => 'externId', 'title' => 'ID', 'width' => 20],
            ['key' => 'statementStatus', 'title' => 'Status', 'width' => 20],
            ['key' => 'internId', 'title' => 'Interne ID', 'width' => 20],
            ['key' => 'tagNames', 'title' => 'Schlagworte', 'width' => 20],
            ['key' => 'oName', 'title' => 'Gruppe', 'width' => 20],
        ];
    }

    public function testReordersColumnsToMatchSelectionOrder(): void
    {
        $selectedColumnKeys = ['externId', 'oName', 'tagNames'];
        $expectedColumnKeys = $selectedColumnKeys;
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);

        static::assertSame(
            $expectedColumnKeys,
            array_column($result, 'key')
        );
    }

    public function testExternIdIsForcedToFirstPositionEvenIfSelectedElsewhere(): void
    {
        $selectedColumnKeys = ['oName', 'externId', 'tagNames'];
        $expectedColumnKeys = ['externId', 'oName', 'tagNames'];
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);

        static::assertSame(
            $expectedColumnKeys,
            array_column($result, 'key')
        );
    }

    public function testExternIdIsAddedWhenNotSelectedAtAll(): void
    {
        $selectedColumnKeys = ['tagNames'];
        $expectedColumnKeys = ['externId', 'tagNames'];
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);

        static::assertSame($expectedColumnKeys, array_column($result, 'key'));
    }

    public function testDropsColumnsNotPresentInSelection(): void
    {
        $selectedColumnKeys = ['externId', 'tagNames'];
        $expectedColumnKeys = ['externId', 'tagNames'];
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);

        static::assertCount(2, $result);
        static::assertSame($expectedColumnKeys, array_column($result, 'key'));
    }

    public function testResultContainsFullColumnDefinitionNotJustTheKey(): void
    {
        $selectedColumnKeys = ['tagNames'];
        $expectedColumnDefinition = ['key' => 'tagNames', 'title' => 'Schlagworte', 'width' => 20];
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);
        $resultByKey = array_column($result, null, 'key');

        static::assertSame(
            $expectedColumnDefinition,
            $resultByKey['tagNames']
        );
    }

    public function testResultIsSequentiallyIndexedRegardlessOfGapsWhileFiltering(): void
    {
        $selectedColumnKeys = ['tagNames', 'unknownKey', 'externId'];
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);

        static::assertSame([0, 1], array_keys($result));
    }

    public function testLogsWarningWhenASelectedColumnKeyHasNoMatch(): void
    {
        $this->logger->expects(static::once())
            ->method('warning')
            ->with(
                static::anything(),
                static::callback(
                    static fn (array $context): bool => in_array('unknownKey', $context['missingColumnKeys'] ?? [], true)
                )
            );

        $selectedColumnKeys = ['externId', 'unknownKey'];
        $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);
    }

    public function testDoesNotLogWhenEverySelectedKeyMatches(): void
    {
        $this->logger->expects(static::never())->method('warning');

        $selectedColumnKeys = ['externId', 'tagNames'];
        $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);
    }

    public function testEmptySelectionStillReturnsExternId(): void
    {
        $emptySelection = [];
        $expectedColumnKeys = ['externId'];
        $result = $this->sut->resolve($this->columnsDefinition, $emptySelection);

        static::assertSame($expectedColumnKeys, array_column($result, 'key'));
    }

    public function testDuplicateSelectedKeyIsNotReturnedTwice(): void
    {
        $selectedColumnKeys = ['externId', 'externId', 'tagNames', 'tagNames'];
        $expectedColumnKeys = ['externId', 'tagNames'];
        $result = $this->sut->resolve($this->columnsDefinition, $selectedColumnKeys);

        static::assertSame($expectedColumnKeys, array_column($result, 'key'));
    }

    public function testEmptyColumnsDefinitionReturnsEmptyResultWithoutCrashing(): void
    {
        $emptyColumnsDefinition = [];
        $selectedColumnKeys = ['tagNames'];
        $expectedColums = [];

        $this->logger->expects(static::once())
            ->method('warning')
            ->with(
                static::anything(),
                static::callback(
                    static fn (array $context): bool => in_array('externId', $context['missingColumnKeys'] ?? [], true)
                        && in_array('tagNames', $context['missingColumnKeys'] ?? [], true)
                )
            );

        $result = $this->sut->resolve($emptyColumnsDefinition, $selectedColumnKeys);

        static::assertSame($expectedColums, $result);
    }
}
