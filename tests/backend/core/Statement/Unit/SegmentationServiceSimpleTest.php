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

class SegmentationServiceSimpleTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(SegmentationService::class);
    }

    public function testConvertTextSectionsOnly(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '
            <div data-section-type="preamble" data-section-order="1">Preamble text</div>
            <div data-section-type="interlude" data-section-order="2">Interlude text</div>
            <div data-section-type="conclusion" data-section-order="3">Conclusion text</div>
        ';

        // Act
        $this->sut->convertToSegmented($statement->object(), $html);

        // Assert
        $this->getEntityManager()->refresh($statement->object());
        self::assertTrue($statement->isSegmented());
        self::assertCount(3, $statement->getTextSections());

        $textSections = $statement->getTextSections()->toArray();
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
        self::assertCount(0, $statement->getTextSections());
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

    public function testGetSegmentedHtmlComposesFromTextSections(): void
    {
        // Arrange
        $statement = StatementFactory::createOne();
        $html = '
            <div data-section-type="preamble" data-section-order="1">Preamble</div>
            <div data-section-type="conclusion" data-section-order="2">Conclusion</div>
        ';
        $this->sut->convertToSegmented($statement->object(), $html);
        $this->getEntityManager()->refresh($statement->object());

        // Act
        $result = $this->sut->getSegmentedHtml($statement->object());

        // Assert
        self::assertStringContainsString('data-section-type="preamble"', $result);
        self::assertStringContainsString('data-section-order="1"', $result);
        self::assertStringContainsString('Preamble', $result);
        self::assertStringContainsString('data-section-type="conclusion"', $result);
        self::assertStringContainsString('data-section-order="2"', $result);
        self::assertStringContainsString('Conclusion', $result);
    }
}
