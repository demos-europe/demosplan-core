<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Export;

use demosplan\DemosPlanCoreBundle\Logic\Export\OdtHtmlProcessor;
use PhpOffice\PhpWord\PhpWord;
use ReflectionClass;
use Tests\Base\UnitTestCase;

class OdtHtmlProcessorTest extends UnitTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new OdtHtmlProcessor();
    }

    public function testParseHtmlWithDomHandlesParagraphs(): void
    {
        // Arrange
        $html = '<p>first paragraph</p><p>second paragraph</p>';

        // Act - use reflection to access private method
        $reflection = new ReflectionClass($this->sut);
        $method = $reflection->getMethod('parseHtmlWithDom');
        $method->setAccessible(true);
        $result = $method->invoke($this->sut, $html);

        // Assert
        self::assertIsArray($result);
        self::assertGreaterThanOrEqual(3, count($result)); // text, line break, text

        // Check that paragraphs are separated by line breaks
        $textSegments = array_filter($result, fn ($seg) => "\n" !== $seg['text']);
        self::assertCount(2, $textSegments);
    }

    public function testParseHtmlWithDomExtractsFormatting(): void
    {
        // Arrange
        $html = '<p><strong>bold text</strong></p>';

        // Act
        $reflection = new ReflectionClass($this->sut);
        $method = $reflection->getMethod('parseHtmlWithDom');
        $method->setAccessible(true);
        $result = $method->invoke($this->sut, $html);

        // Assert
        self::assertIsArray($result);
        self::assertNotEmpty($result);

        // Find the bold text segment
        $boldSegment = array_filter($result, fn ($seg) => 'bold text' === $seg['text']);
        self::assertNotEmpty($boldSegment);
        $boldSegment = array_values($boldSegment)[0];
        self::assertTrue($boldSegment['bold']);
        self::assertFalse($boldSegment['italic']);
        self::assertFalse($boldSegment['underline']);
    }

    public function testGetOdtStyleNameReturnsCorrectStyleForCombinations(): void
    {
        // Test all formatting combinations
        $testCases = [
            [false, false, false, 'odtPlain'],
            [true, false, false, 'odtBold'],
            [false, true, false, 'odtItalic'],
            [false, false, true, 'odtUnderline'],
            [true, true, false, 'odtBoldItalic'],
            [true, false, true, 'odtBoldUnderline'],
            [false, true, true, 'odtItalicUnderline'],
            [true, true, true, 'odtBoldItalicUnderline'],
        ];

        $reflection = new ReflectionClass($this->sut);
        $method = $reflection->getMethod('getOdtStyleName');
        $method->setAccessible(true);

        foreach ($testCases as [$bold, $italic, $underline, $expectedStyle]) {
            // Act
            $result = $method->invoke($this->sut, $bold, $italic, $underline);

            // Assert
            self::assertSame($expectedStyle, $result, "Failed for bold=$bold, italic=$italic, underline=$underline");
        }
    }

    public function testRegisterStylesRegistersAllStyles(): void
    {
        // Arrange
        $phpWord = new PhpWord();

        // Act - should not throw exceptions when registering styles
        $this->expectNotToPerformAssertions();
        $this->sut->registerStyles($phpWord);
    }

    public function testProcessHtmlForCellProcessesFormattedTextCorrectly(): void
    {
        // Arrange
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();

        $this->sut->registerStyles($phpWord);

        $html = '<p><strong>bold</strong> and plain text</p>';

        // Act
        $this->sut->processHtmlForCell($cell, $html);

        // Assert - verify that multiple text elements were added with different styles
        $elements = $cell->getElements();
        self::assertGreaterThan(1, count($elements), 'Cell should contain multiple text elements with different formatting');
    }

    public function testProcessHtmlForCellHandlesComplexHtml(): void
    {
        // Arrange
        $phpWord = new PhpWord();
        $section = $phpWord->addSection();
        $table = $section->addTable();
        $row = $table->addRow();
        $cell = $row->addCell();

        $this->sut->registerStyles($phpWord);

        $html = '<p>interne Hinweise</p><p>text</p><p><strong>dick</strong></p><p><strong><em>kursiv</em></strong></p><p><u>untertrichen</u></p>';

        // Act
        $this->sut->processHtmlForCell($cell, $html);

        // Assert - verify that text was processed and added
        $elements = $cell->getElements();
        self::assertNotEmpty($elements, 'Cell should contain processed text elements');
    }
}
