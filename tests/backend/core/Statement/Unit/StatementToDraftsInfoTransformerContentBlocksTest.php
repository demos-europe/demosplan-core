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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TextSectionFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Transformers\Segment\StatementToDraftsInfoTransformer;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use ReflectionMethod;
use Tests\Base\FunctionalTestCase;

/**
 * Covers the read side of the order-based `contentBlocks` format: an already-segmented
 * Statement must be transformed into a `contentBlocks` payload built from its Segments and
 * TextSections, sorted by orderInStatement, without going through DraftsInfoValidator.
 *
 * `transformSegmentedStatement()` is invoked via reflection rather than through the public
 * `transform($statementId)` entry point: that entry point first calls
 * `SegmentableStatementValidator::validate()`, which rejects any statement whose
 * `segmentsOfStatement` is non-empty (the legacy `isAlreadySegmented()` check) - but a statement
 * that can actually reach the `isSegmented()`/contentBlocks branch necessarily already has
 * Segments attached, so that guard always throws first. This is a pre-existing ordering bug
 * unrelated to this migration (not fixed here); reflection lets these tests verify the
 * block-building logic itself without being blocked by it.
 */
class StatementToDraftsInfoTransformerContentBlocksTest extends FunctionalTestCase
{
    protected ?StatementToDraftsInfoTransformer $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(StatementToDraftsInfoTransformer::class);
    }

    private function transformSegmentedStatement(Statement $statement): string
    {
        $method = new ReflectionMethod($this->sut, 'transformSegmentedStatement');
        $method->setAccessible(true);

        return $method->invoke($this->sut, $statement);
    }

    public function testTransformBuildsContentBlocksSortedByOrder(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();

        TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 1,
            'text'             => 'Plain leftover text.',
            'textRaw'          => '<p>Plain leftover text.</p>',
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInProcedure'         => 2,
            'text'                     => '<p>Segment text.</p>',
        ]);

        $statement->_refresh();

        // Act
        $result = json_decode($this->transformSegmentedStatement($statement), true, 512, JSON_THROW_ON_ERROR);

        // Assert
        $attributes = $result['data']['attributes'];
        self::assertSame('SEGMENTED', $attributes['segmentationStatus']);
        self::assertArrayHasKey('contentBlocks', $attributes);

        $blocks = $attributes['contentBlocks'];
        self::assertCount(2, $blocks);

        // Sorted by order: the textSection (order 1) comes before the segment (order 2).
        self::assertSame('textSection', $blocks[0]['type']);
        self::assertSame(1, $blocks[0]['order']);
        self::assertSame('Plain leftover text.', $blocks[0]['text']);
        self::assertSame('<p>Plain leftover text.</p>', $blocks[0]['textRaw']);

        self::assertSame('segment', $blocks[1]['type']);
        self::assertSame(2, $blocks[1]['order']);
        self::assertSame('<p>Segment text.</p>', $blocks[1]['text']);
        // Segment has no separate raw-HTML storage - both fields mirror the same rich-text value.
        self::assertSame('<p>Segment text.</p>', $blocks[1]['textRaw']);
        // Documents a known, pre-existing gap (out of scope for this migration): segment blocks
        // never carry their real tags on this read path.
        self::assertSame([], $blocks[1]['tags']);
    }

    public function testTransformHandlesOnlyASegmentWithoutAnyTextSection(): void
    {
        // Arrange - a segmented statement with only a Segment and no TextSection at all, a shape
        // the legacy schema (still unconditionally requiring textualReference/segments) would
        // reject if this path were ever validated against it - it isn't, this method returns
        // before DraftsInfoValidator::validate() is reached.
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInProcedure'         => 1,
            'text'                     => '<p>Only segment.</p>',
        ]);

        $statement->_refresh();

        // Act
        $result = json_decode($this->transformSegmentedStatement($statement), true, 512, JSON_THROW_ON_ERROR);

        // Assert
        $blocks = $result['data']['attributes']['contentBlocks'];
        self::assertCount(1, $blocks);
        self::assertSame('segment', $blocks[0]['type']);
    }
}
