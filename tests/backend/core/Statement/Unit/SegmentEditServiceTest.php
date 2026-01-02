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
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TextSection;
use demosplan\DemosPlanCoreBundle\Exception\ConcurrentModificationException;
use demosplan\DemosPlanCoreBundle\Exception\EditLockedException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\OrderManagementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\SegmentEditService;
use Tests\Base\FunctionalTestCase;

class SegmentEditServiceTest extends FunctionalTestCase
{
    protected $sut;
    protected ?OrderManagementService $orderManagementService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->orderManagementService = $this->getContainer()->get(OrderManagementService::class);
        $this->sut = new SegmentEditService(
            $this->getEntityManager(),
            $this->getContainer()->get('event_dispatcher'),
            $this->orderManagementService
        );
    }

    public function testUpdateSegmentTextUpdatesContent(): void
    {
        // Arrange
        $segment = SegmentFactory::createOne([
            'text' => '<p>Original text</p>',
        ]);

        $newText = '<p>Updated text</p>';

        // Act
        $result = $this->sut->updateSegmentText(
            $segment->object(),
            $newText
        );

        // Assert
        self::assertSame($newText, $result->getText());
    }

    public function testMergeSegmentsCombinesContentAndMetadata(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement' => 1,
            'text' => '<p>First</p>',
            'editLocked' => false,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement' => 2,
            'text' => '<p>Second</p>',
            'editLocked' => false,
        ]);

        // Act
        $merged = $this->sut->mergeSegments(
            $seg1->object(),
            $seg2->object()
        );

        // Assert
        self::assertSame('<p>First</p><p>Second</p>', $merged->getText());
        self::assertSame(1, $merged->getOrderInStatement());

        // Original segments should be removed
        $statement->refresh();
        self::assertCount(1, $statement->getSegmentsOfStatement());
    }

    public function testMergeSegmentsThrowsExceptionWhenFirstSegmentIsLocked(): void
    {
        // Arrange
        $seg1 = SegmentFactory::createOne(['editLocked' => true]);
        $seg2 = SegmentFactory::createOne(['editLocked' => false]);

        // Assert
        $this->expectException(EditLockedException::class);
        $this->expectExceptionMessage('Cannot merge segments in assessment');

        // Act
        $this->sut->mergeSegments($seg1->object(), $seg2->object());
    }

    public function testMergeSegmentsThrowsExceptionWhenSecondSegmentIsLocked(): void
    {
        // Arrange
        $seg1 = SegmentFactory::createOne(['editLocked' => false]);
        $seg2 = SegmentFactory::createOne(['editLocked' => true]);

        // Assert
        $this->expectException(EditLockedException::class);
        $this->expectExceptionMessage('Cannot merge segments in assessment');

        // Act
        $this->sut->mergeSegments($seg1->object(), $seg2->object());
    }

    public function testSplitSegmentCreatesTwoParts(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement' => 1,
            'text' => '<p>Original long text</p>',
            'editLocked' => false,
        ]);

        // Act
        [$first, $second] = $this->sut->splitSegment(
            $segment->object(),
            '<p>First part</p>',
            '<p>Second part</p>'
        );

        // Assert
        self::assertSame('<p>First part</p>', $first->getText());
        self::assertSame(1, $first->getOrderInStatement());

        self::assertSame('<p>Second part</p>', $second->getText());
        self::assertSame(2, $second->getOrderInStatement());

        // Original segment should be removed
        $statement->refresh();
        self::assertCount(2, $statement->getSegmentsOfStatement());
    }

    public function testSplitSegmentThrowsExceptionWhenLocked(): void
    {
        // Arrange
        $segment = SegmentFactory::createOne(['editLocked' => true]);

        // Assert
        $this->expectException(EditLockedException::class);
        $this->expectExceptionMessage('Cannot split segments in assessment');

        // Act
        $this->sut->splitSegment(
            $segment->object(),
            '<p>Part 1</p>',
            '<p>Part 2</p>'
        );
    }

    public function testDeleteSegmentRemovesItFromStatement(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement' => 1,
            'editLocked' => false,
        ]);

        self::assertCount(1, $statement->getSegmentsOfStatement());

        // Act
        $this->sut->deleteSegment($segment->object());

        // Assert
        $statement->refresh();
        self::assertCount(0, $statement->getSegmentsOfStatement());
    }

    public function testDeleteSegmentThrowsExceptionWhenLocked(): void
    {
        // Arrange
        $segment = SegmentFactory::createOne(['editLocked' => true]);

        // Assert
        $this->expectException(EditLockedException::class);
        $this->expectExceptionMessage('Cannot delete segments in assessment');

        // Act
        $this->sut->deleteSegment($segment->object());
    }

    public function testConvertTextSectionToSegmentCreatesSegmentWithSameContent(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $textSection = TextSectionFactory::createOne([
            'statement' => $statement,
            'orderInStatement' => 2,
            'text' => '<p>Unstructured text</p>',
        ]);

        // Act
        $segment = $this->sut->convertTextSectionToSegment($textSection->object());

        // Assert
        self::assertInstanceOf(Segment::class, $segment);
        self::assertSame('<p>Unstructured text</p>', $segment->getText());
        self::assertSame(2, $segment->getOrderInStatement());

        // Text section should be removed
        $statement->refresh();
        self::assertCount(0, $statement->getTextSections());
        self::assertCount(1, $statement->getSegmentsOfStatement());
    }

    public function testConvertSegmentToTextSectionCreatesTextSectionWithSameContent(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement' => 1,
            'text' => '<p>Segment text</p>',
            'editLocked' => false,
        ]);

        // Act
        $textSection = $this->sut->convertSegmentToTextSection($segment->object());

        // Assert
        self::assertInstanceOf(TextSection::class, $textSection);
        self::assertSame('<p>Segment text</p>', $textSection->getText());
        self::assertSame(1, $textSection->getOrderInStatement());
        self::assertSame(TextSectionType::INTERLUDE->value, $textSection->getSectionType());

        // Segment should be removed
        $statement->refresh();
        self::assertCount(0, $statement->getSegmentsOfStatement());
        self::assertCount(1, $statement->getTextSections());
    }

    public function testConvertSegmentToTextSectionThrowsExceptionWhenLocked(): void
    {
        // Arrange
        $segment = SegmentFactory::createOne(['editLocked' => true]);

        // Assert
        $this->expectException(EditLockedException::class);
        $this->expectExceptionMessage('Cannot convert locked segments');

        // Act
        $this->sut->convertSegmentToTextSection($segment->object());
    }

    public function testMoveSegmentUpdatesOrder(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $segment = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement' => 1,
            'editLocked' => false,
        ]);

        // Act
        $this->sut->moveSegment($segment->object(), 3);

        // Assert
        $segment->refresh();
        self::assertSame(3, $segment->getOrderInStatement());
    }

    public function testMoveSegmentThrowsExceptionWhenLocked(): void
    {
        // Arrange
        $segment = SegmentFactory::createOne([
            'editLocked' => true,
            'orderInStatement' => 1,
        ]);

        // Assert
        $this->expectException(EditLockedException::class);
        $this->expectExceptionMessage('Cannot reorder locked segments');

        // Act
        $this->sut->moveSegment($segment->object(), 2);
    }
}
