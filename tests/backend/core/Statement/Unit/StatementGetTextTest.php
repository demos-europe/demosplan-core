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
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use Tests\Base\FunctionalTestCase;

class StatementGetTextTest extends FunctionalTestCase
{
    public function testGetTextReturnsLegacyTextForUnsegmentedStatement(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setText('Legacy statement text');
        $statement->setSegmentationStatus(SegmentationStatus::UNSEGMENTED);
        $statement->_save();

        // Act
        $result = $statement->getText();

        // Assert
        self::assertEquals('Legacy statement text', $result);
    }

    public function testGetTextComposesTextFromSegmentsAndSections(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();

        // Create preamble
        TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 1,
            'text'             => 'This is the preamble.',
        ]);

        // Create first segment
        $segment1 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInProcedure'         => 2,
            'text'                     => 'First segment text.',
        ]);

        // Create interlude
        TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 3,
            'text'             => 'This is an interlude between segments.',
        ]);

        // Create second segment
        $segment2 = SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInProcedure'         => 4,
            'text'                     => 'Second segment text.',
        ]);

        // Create conclusion
        TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 5,
            'text'             => 'This is the conclusion.',
        ]);

        // Act
        $result = $statement->getText();

        // Assert
        $expected = 'This is the preamble. First segment text. This is an interlude between segments. Second segment text. This is the conclusion.';
        self::assertEquals($expected, $result);
    }

    public function testGetTextReturnsEmptyStringWhenNoSegmentsOrSections(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->setText(''); // Clear legacy text
        $statement->_save();

        // Act
        $result = $statement->getText();

        // Assert
        self::assertEquals('', $result);
    }

    public function testGetTextHandlesOnlySegmentsWithoutTextSections(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();

        SegmentFactory::createOne([
            'parentStatementOfSegment' => $statement,
            'orderInProcedure'         => 1,
            'text'                     => 'Only segment text.',
        ]);

        // Act
        $result = $statement->getText();

        // Assert
        self::assertEquals('Only segment text.', $result);
    }

    public function testGetTextHandlesOnlyTextSectionsWithoutSegments(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::SEGMENTED);
        $statement->_save();

        TextSectionFactory::createOne([
            'statement'        => $statement,
            'orderInStatement' => 1,
            'text'             => 'Only preamble text.',
        ]);

        // Act
        $result = $statement->getText();

        // Assert
        self::assertEquals('Only preamble text.', $result);
    }
}
