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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Logic\Statement\SegmentationService;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentationStatus;
use Tests\Base\FunctionalTestCase;

class SegmentationServiceTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentationService::class);
    }

    public function testConvertLegacyStatementToSegmented(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setText('Legacy text');
        $statement->setSegmentationStatus(SegmentationStatus::UNSEGMENTED);
        $statement->_save();

        $html = '
            <div data-segment-order="2">Segment 1</div>
            <div data-segment-order="3">Segment 2</div>
        ';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        self::assertTrue($statement->isSegmented());
        self::assertCount(1, $statement->getTextSections());
        self::assertCount(2, $statement->getSegmentsOfStatement());
    }

    public function testConvertCreatesSegmentsWithCorrectOrder(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '
            <div data-segment-order="1">First</div>
            <div data-segment-order="2">Second</div>
            <div data-segment-order="3">Third</div>
        ';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        $segments = $statement->getSegmentsOfStatement()->toArray();

        self::assertCount(3, $segments);
        self::assertEquals(1, $segments[0]->getOrderInProcedure());
        self::assertEquals(2, $segments[1]->getOrderInProcedure());
        self::assertEquals(3, $segments[2]->getOrderInProcedure());
    }

    public function testConvertCreatesTextSectionsWithCorrectType(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '
        ';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        $textSections = $statement->getTextSections()->toArray();

        self::assertCount(3, $textSections);
    }

    public function testConvertClearsExistingSegmentsBeforeConversion(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $firstHtml = '<div data-segment-order="1">First conversion</div>';

        // First conversion
        $this->sut->convertToSegmented($statement->object(), $firstHtml);
        $this->getEntityManager()->refresh($statement->object());
        self::assertCount(1, $statement->getSegmentsOfStatement());

        // Act - Second conversion with different content
        $secondHtml = '
            <div data-segment-order="1">Second conversion A</div>
            <div data-segment-order="2">Second conversion B</div>
        ';
        $this->sut->convertToSegmented($statement->object(), $secondHtml);

        // Assert
        $this->getEntityManager()->clear();
        $reloaded = $this->getEntityManager()->find(
            \demosplan\DemosPlanCoreBundle\Entity\Statement\Statement::class,
            $statement->getId()
        );

        self::assertCount(2, $reloaded->getSegmentsOfStatement());
        self::assertEquals('Second conversion A', $reloaded->getSegmentsOfStatement()[0]->getText());
    }

    public function testConvertPreservesStatementMetadata(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $originalExternId = $statement->getExternId();
        $originalProcedure = $statement->getProcedure();

        $html = '<div data-segment-order="1">Content</div>';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        self::assertEquals($originalExternId, $statement->getExternId());
        self::assertEquals($originalProcedure->getId(), $statement->getProcedure()->getId());
    }

    public function testConvertHandlesEmptyHtml(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        self::assertTrue($statement->isSegmented());
        self::assertCount(0, $statement->getSegmentsOfStatement());
        self::assertCount(0, $statement->getTextSections());
    }

    public function testConvertCreatesSegmentsWithTextRaw(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '<div data-segment-order="1"><p><strong>Bold</strong> text</p></div>';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        $segments = $statement->getSegmentsOfStatement()->toArray();

        self::assertCount(1, $segments);
        self::assertStringContainsString('<strong>Bold</strong>', $segments[0]->getText());
    }

    public function testGetSegmentedHtmlComposesFromSegmentsAndSections(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '
            <div data-segment-order="2">Segment</div>
        ';
        $this->sut->convertToSegmented($statement->object(), $html);

        // Act
        $result = $this->sut->getSegmentedHtml($statement->object());

        // Assert
        self::assertStringContainsString('data-segment-order="2"', $result);
        self::assertStringContainsString('Preamble', $result);
        self::assertStringContainsString('Segment', $result);
        self::assertStringContainsString('Conclusion', $result);
    }

    public function testGetSegmentedHtmlReturnsEmptyForUnsegmented(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $statement->setSegmentationStatus(SegmentationStatus::UNSEGMENTED);
        $statement->_save();

        // Act
        $result = $this->sut->getSegmentedHtml($statement->object());

        // Assert
        self::assertEquals('', $result);
    }
}
