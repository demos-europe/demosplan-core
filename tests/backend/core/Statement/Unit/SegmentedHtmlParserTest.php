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

use demosplan\DemosPlanCoreBundle\Logic\Statement\SegmentedHtmlParser;
use PHPUnit\Framework\TestCase;

class SegmentedHtmlParserTest extends TestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new SegmentedHtmlParser();
    }

    public function testParseSimpleStructuredHtml(): void
    {
        // Arrange
        $html = '<div data-segment-order="1">First segment</div>';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertIsArray($result);
        self::assertCount(1, $result);
        self::assertEquals(1, $result[0]['order']);
        self::assertEquals('First segment', $result[0]['text']);
        self::assertEquals('segment', $result[0]['type']);
    }

    public function testParseMultipleSegments(): void
    {
        // Arrange
        $html = '
            <div data-segment-order="1">First segment</div>
            <div data-segment-order="2">Second segment</div>
            <div data-segment-order="3">Third segment</div>
        ';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertCount(3, $result);
        self::assertEquals(1, $result[0]['order']);
        self::assertEquals('First segment', $result[0]['text']);
        self::assertEquals(2, $result[1]['order']);
        self::assertEquals('Second segment', $result[1]['text']);
        self::assertEquals(3, $result[2]['order']);
        self::assertEquals('Third segment', $result[2]['text']);
    }

    public function testParsePreambleInterlideAndConclusion(): void
    {
        // Arrange
        $html = '
            <div data-segment-order="2">Segment 1</div>
            <div data-segment-order="4">Segment 2</div>
        ';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertCount(5, $result);

        // Preamble
        self::assertEquals(1, $result[0]['order']);
        self::assertEquals('textSection', $result[0]['type']);
        self::assertEquals('This is the preamble.', $result[0]['text']);

        // Segment 1
        self::assertEquals(2, $result[1]['order']);
        self::assertEquals('segment', $result[1]['type']);

        // Interlude
        self::assertEquals(3, $result[2]['order']);
        self::assertEquals('textSection', $result[2]['type']);

        // Segment 2
        self::assertEquals(4, $result[3]['order']);
        self::assertEquals('segment', $result[3]['type']);

        // Conclusion
        self::assertEquals(5, $result[4]['order']);
        self::assertEquals('textSection', $result[4]['type']);
    }

    public function testParseExtractsTextRaw(): void
    {
        // Arrange
        $html = '<div data-segment-order="1"><p><strong>Bold text</strong> and regular text</p></div>';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertArrayHasKey('textRaw', $result[0]);
        self::assertStringContainsString('<strong>Bold text</strong>', $result[0]['textRaw']);
    }

    public function testParseStripsTags(): void
    {
        // Arrange
        $html = '<div data-segment-order="1"><p><strong>Bold</strong> and <em>italic</em></p></div>';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertEquals('Bold and italic', $result[0]['text']);
    }

    public function testParseHandlesEmptyHtml(): void
    {
        // Arrange
        $html = '';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertIsArray($result);
        self::assertEmpty($result);
    }

    public function testParseIgnoresNonStructuredElements(): void
    {
        // Arrange
        $html = '
            <p>This text has no order attribute and should be ignored</p>
            <div data-segment-order="1">This should be parsed</div>
        ';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertCount(1, $result);
        self::assertEquals('This should be parsed', $result[0]['text']);
    }

    public function testParseSortsResultsByOrder(): void
    {
        // Arrange - intentionally out of order
        $html = '
            <div data-segment-order="3">Third</div>
            <div data-segment-order="1">First</div>
            <div data-segment-order="2">Second</div>
        ';

        // Act
        $result = $this->sut->parse($html);

        // Assert
        self::assertEquals('First', $result[0]['text']);
        self::assertEquals('Second', $result[1]['text']);
        self::assertEquals('Third', $result[2]['text']);
    }
}
