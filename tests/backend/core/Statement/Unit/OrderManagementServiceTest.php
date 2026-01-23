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
use demosplan\DemosPlanCoreBundle\Logic\Statement\OrderManagementService;
use InvalidArgumentException;
use Tests\Base\FunctionalTestCase;

class OrderManagementServiceTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new OrderManagementService(
            $this->getEntityManager()
        );
    }

    public function testRenumberContentBlocksCreatesSequentialOrder(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 5,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 10,
        ]);

        $seg3 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 15,
        ]);

        // Act
        $this->sut->renumberContentBlocks($statement->object());

        // Assert
        $seg1->refresh();
        $seg2->refresh();
        $seg3->refresh();

        self::assertSame(1, $seg1->getOrderInStatement());
        self::assertSame(2, $seg2->getOrderInStatement());
        self::assertSame(3, $seg3->getOrderInStatement());
    }

    public function testRenumberContentBlocksHandlesMixedBlockTypes(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 10,
        ]);

        $textSection = TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 5,
        ]);

        // Act
        $this->sut->renumberContentBlocks($statement->object());

        // Assert
        $textSection->refresh();
        $seg->refresh();

        // TextSection should come first (lower original order)
        self::assertSame(1, $textSection->getOrderInStatement());
        self::assertSame(2, $seg->getOrderInStatement());
    }

    public function testMoveBlockMovesDownSuccessfully(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 2,
        ]);

        $seg3 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 3,
        ]);

        // Act - Move seg1 from position 1 to position 3
        $this->sut->moveBlock($statement->object(), 1, 3);

        // Assert
        $seg1->refresh();
        $seg2->refresh();
        $seg3->refresh();

        self::assertSame(3, $seg1->getOrderInStatement());
        self::assertSame(1, $seg2->getOrderInStatement()); // shifted up
        self::assertSame(2, $seg3->getOrderInStatement()); // shifted up
    }

    public function testMoveBlockMovesUpSuccessfully(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 2,
        ]);

        $seg3 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 3,
        ]);

        // Act - Move seg3 from position 3 to position 1
        $this->sut->moveBlock($statement->object(), 3, 1);

        // Assert
        $seg1->refresh();
        $seg2->refresh();
        $seg3->refresh();

        self::assertSame(2, $seg1->getOrderInStatement()); // shifted down
        self::assertSame(3, $seg2->getOrderInStatement()); // shifted down
        self::assertSame(1, $seg3->getOrderInStatement());
    }

    public function testMoveBlockDoesNothingWhenFromEqualsTo(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 2,
        ]);

        // Act - Move to same position
        $this->sut->moveBlock($statement->object(), 2, 2);

        // Assert - No change
        $seg->refresh();
        self::assertSame(2, $seg->getOrderInStatement());
    }

    public function testMoveBlockThrowsExceptionWhenBlockNotFound(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        // Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No block found at order position 99');

        // Act
        $this->sut->moveBlock($statement->object(), 99, 1);
    }

    public function testInsertSegmentAfterShiftsSubsequentBlocks(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 2,
        ]);

        $newSegment = SegmentFactory::createOne();

        // Act
        $this->sut->insertSegmentAfter($seg1->object(), $newSegment->object());

        // Assert
        $seg1->refresh();
        $seg2->refresh();
        $newSegment->refresh();

        self::assertSame(1, $seg1->getOrderInStatement());
        self::assertSame(2, $newSegment->getOrderInStatement()); // inserted after seg1
        self::assertSame(3, $seg2->getOrderInStatement()); // shifted down
    }

    public function testInsertTextSectionBetweenWithGap(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 5, // Gap between 1 and 5
        ]);

        $textSection = TextSectionFactory::createOne(['statement' => $statement]);

        // Act - Insert between 1 and 5
        $this->sut->insertTextSectionBetween(1, 5, $textSection->object());

        // Assert
        $seg1->refresh();
        $seg2->refresh();
        $textSection->refresh();

        self::assertSame(1, $seg1->getOrderInStatement());
        self::assertSame(2, $textSection->getOrderInStatement()); // inserted at order1 + 1
        self::assertSame(6, $seg2->getOrderInStatement()); // shifted down
    }

    public function testInsertTextSectionBetweenWithoutGap(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        $seg1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        $seg2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 2, // No gap
        ]);

        $textSection = TextSectionFactory::createOne(['statement' => $statement]);

        // Act - Insert between 1 and 2
        $this->sut->insertTextSectionBetween(1, 2, $textSection->object());

        // Assert
        $seg1->refresh();
        $seg2->refresh();
        $textSection->refresh();

        self::assertSame(1, $seg1->getOrderInStatement());
        self::assertSame(2, $textSection->getOrderInStatement()); // inserted at order2
        self::assertSame(3, $seg2->getOrderInStatement()); // shifted down
    }

    public function testGetMaxOrderReturnsHighestOrder(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 3,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 7,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 5,
        ]);

        // Act
        $maxOrder = $this->sut->getMaxOrder($statement->object());

        // Assert
        self::assertSame(7, $maxOrder);
    }

    public function testGetMaxOrderReturnsZeroWhenNoBlocks(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        // Act
        $maxOrder = $this->sut->getMaxOrder($statement->object());

        // Assert
        self::assertSame(0, $maxOrder);
    }

    public function testValidateOrderSequenceReturnsNoErrorsForValidSequence(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 2,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 3,
        ]);

        // Act
        $errors = $this->sut->validateOrderSequence($statement->object());

        // Assert
        self::assertEmpty($errors);
    }

    public function testValidateOrderSequenceDetectsDuplicates(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1, // Duplicate!
        ]);

        // Act
        $errors = $this->sut->validateOrderSequence($statement->object());

        // Assert
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Duplicate order number: 1', $errors[0]);
    }

    public function testValidateOrderSequenceDetectsGaps(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 1,
        ]);

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInStatement'         => 3, // Gap! Missing 2
        ]);

        // Act
        $errors = $this->sut->validateOrderSequence($statement->object());

        // Assert
        self::assertNotEmpty($errors);
        self::assertStringContainsString('Gap in order sequence', $errors[0]);
    }

    public function testValidateOrderSequenceReturnsEmptyForEmptyStatement(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();

        // Act
        $errors = $this->sut->validateOrderSequence($statement->object());

        // Assert
        self::assertEmpty($errors);
    }
}
