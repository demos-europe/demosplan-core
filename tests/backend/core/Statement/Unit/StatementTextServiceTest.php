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
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementTextService;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use Psr\Cache\CacheItemPoolInterface;
use Tests\Base\FunctionalTestCase;

class StatementTextServiceTest extends FunctionalTestCase
{
    protected $sut;
    protected ?CacheItemPoolInterface $cache = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = $this->getContainer()->get('cache.app');
        $this->sut = new StatementTextService($this->cache);
    }

    protected function tearDown(): void
    {
        // Clear cache after each test
        $this->cache->clear();
        parent::tearDown();
    }

    public function testComputeTextReturnsTextForUnsegmentedStatement(): void
    {
        // Arrange
        $expectedText = '<p>Original text</p>';
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::UNSEGMENTED,
            'text'               => $expectedText,
        ]);

        // Act
        $result = $this->sut->computeText($statement->object());

        // Assert
        self::assertSame($expectedText, $result);
    }

    public function testComputeTextReturnsEmptyStringForEmptyText(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::UNSEGMENTED,
            'text'               => '',
        ]);

        // Act
        $result = $this->sut->computeText($statement->object());

        // Assert
        self::assertSame('', $result);
    }

    public function testComputeTextConcatenatesBlocksForSegmentedStatement(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
            'text'                     => '<p>First segment</p>',
        ]);

        TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 2,
            'text'             => '<p>Text section</p>',
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 3,
            'text'                     => '<p>Second segment</p>',
        ]);

        // Act
        $result = $this->sut->computeText($statement->object());

        // Assert
        self::assertSame(
            '<p>First segment</p><p>Text section</p><p>Second segment</p>',
            $result
        );
    }

    public function testComputeTextUsesCacheWhenAvailable(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
            'text'                     => '<p>Original content</p>',
        ]);

        // First call - cache miss
        $firstResult = $this->sut->computeText($statement->object());

        // Modify segment (but cache should still be used)
        $segment = $statement->getSegmentsOfStatement()->first();
        $segment->setText('<p>Modified content</p>');
        $this->getEntityManager()->flush();

        // Act - Second call should use cache
        $secondResult = $this->sut->computeText($statement->object());

        // Assert - Cache returns original value
        self::assertSame($firstResult, $secondResult);
        self::assertSame('<p>Original content</p>', $secondResult);
    }

    public function testGetCachedTextReturnsNullWhenNotCached(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        // Act
        $result = $this->sut->getCachedText($statement->object());

        // Assert
        self::assertNull($result);
    }

    public function testGetCachedTextReturnsCachedValue(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
            'text'                     => '<p>Cached text</p>',
        ]);

        // Populate cache
        $this->sut->computeText($statement->object());

        // Act
        $result = $this->sut->getCachedText($statement->object());

        // Assert
        self::assertSame('<p>Cached text</p>', $result);
    }

    public function testInvalidateTextCacheRemovesCachedEntry(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
            'text'                     => '<p>Original</p>',
        ]);

        // Populate cache
        $this->sut->computeText($statement->object());
        self::assertNotNull($this->sut->getCachedText($statement->object()));

        // Act
        $this->sut->invalidateTextCache($statement->object());

        // Assert
        self::assertNull($this->sut->getCachedText($statement->object()));
    }

    public function testInvalidateTextCacheForMultipleStatementsRemovesAllCaches(): void
    {
        // Arrange
        $statement1 = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        $statement2 = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        $statement3 = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement1,
            'orderInStatement'         => 1,
            'text'                     => '<p>Text 1</p>',
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement2,
            'orderInStatement'         => 1,
            'text'                     => '<p>Text 2</p>',
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement3,
            'orderInStatement'         => 1,
            'text'                     => '<p>Text 3</p>',
        ]);

        // Populate all caches
        $this->sut->computeText($statement1->object());
        $this->sut->computeText($statement2->object());
        $this->sut->computeText($statement3->object());

        // Act
        $this->sut->invalidateTextCacheForMultiple([
            $statement1->object(),
            $statement2->object(),
            $statement3->object(),
        ]);

        // Assert
        self::assertNull($this->sut->getCachedText($statement1->object()));
        self::assertNull($this->sut->getCachedText($statement2->object()));
        self::assertNull($this->sut->getCachedText($statement3->object()));
    }

    public function testNeedsRecomputationReturnsFalseForUnsegmentedStatement(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::UNSEGMENTED,
        ]);

        // Act
        $result = $this->sut->needsRecomputation($statement->object());

        // Assert
        self::assertFalse($result);
    }

    public function testNeedsRecomputationReturnsTrueForSegmentedStatementWithoutCache(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        // Act
        $result = $this->sut->needsRecomputation($statement->object());

        // Assert
        self::assertTrue($result);
    }

    public function testNeedsRecomputationReturnsFalseForSegmentedStatementWithCache(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
            'text'                     => '<p>Content</p>',
        ]);

        // Populate cache
        $this->sut->computeText($statement->object());

        // Act
        $result = $this->sut->needsRecomputation($statement->object());

        // Assert
        self::assertFalse($result);
    }

    public function testPrecomputeTextForMultipleWarmsCacheForSegmentedStatements(): void
    {
        // Arrange
        $statement1 = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        $statement2 = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::UNSEGMENTED,
            'text'               => '<p>Unsegmented</p>',
        ]);

        $statement3 = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement1,
            'orderInStatement'         => 1,
            'text'                     => '<p>Segmented 1</p>',
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement3,
            'orderInStatement'         => 1,
            'text'                     => '<p>Segmented 3</p>',
        ]);

        // Act
        $this->sut->precomputeTextForMultiple([
            $statement1->object(),
            $statement2->object(),
            $statement3->object(),
        ]);

        // Assert - Only segmented statements should be cached
        self::assertNotNull($this->sut->getCachedText($statement1->object()));
        self::assertNull($this->sut->getCachedText($statement2->object())); // Unsegmented, not cached
        self::assertNotNull($this->sut->getCachedText($statement3->object()));
    }

    public function testComputeTextAfterCacheInvalidationRecomputesText(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
            'text'                     => '<p>Original</p>',
        ]);

        // First computation
        $originalText = $this->sut->computeText($statement->object());
        self::assertSame('<p>Original</p>', $originalText);

        // Modify segment and invalidate cache
        $segment->object()->setText('<p>Modified</p>');
        $this->getEntityManager()->flush();
        $this->sut->invalidateTextCache($statement->object());

        // Act - Recompute after invalidation
        $newText = $this->sut->computeText($statement->object());

        // Assert
        self::assertSame('<p>Modified</p>', $newText);
    }

    public function testComputeTextHandlesEmptyBlockList(): void
    {
        // Arrange
        $statement = StatementFactory::createOne([
            'segmentationStatus' => SegmentationStatus::SEGMENTED,
        ]);

        // No segments or text sections

        // Act
        $result = $this->sut->computeText($statement->object());

        // Assert
        self::assertSame('', $result);
    }
}
