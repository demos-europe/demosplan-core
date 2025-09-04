<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\Exception\OdtProcessingException;
use demosplan\DemosPlanCoreBundle\Tools\OdtImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class OdtImporterTest extends TestCase
{
    private const BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT = 'backend/core/Import/res/SimpleDoc.odt';

    // XML templates to avoid duplication
    private const STYLES_XML_TEMPLATE = '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum5">
<text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">
</text:list-level-style-number>
</text:list-style>
</office:styles>
</office:document-styles>';

    private function createOdtImporter(?ZipArchive $zipArchive = null): OdtImporter
    {
        $styleParser = new \demosplan\DemosPlanCoreBundle\Tools\ODT\ODTStyleParser();
        $htmlProcessor = new \demosplan\DemosPlanCoreBundle\Tools\ODT\ODTHtmlProcessor();
        $fileExtractor = new \demosplan\DemosPlanCoreBundle\Tools\ODT\OdtFileExtractor($zipArchive);
        $elementProcessor = new \demosplan\DemosPlanCoreBundle\Tools\ODT\OdtElementProcessor();

        return new OdtImporter($styleParser, $htmlProcessor, $fileExtractor, $elementProcessor);
    }

    /**
     * Create a mocked ZipArchive that returns the given content for content.xml and styles.xml.
     */
    private function createMockedZipArchive(string $contentXml, ?string $stylesXml = null): ZipArchive
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('close')->willReturn(true);

        if (null !== $stylesXml) {
            $zip->method('getFromName')->willReturnCallback(function ($filename) use ($contentXml, $stylesXml) {
                return match ($filename) {
                    'content.xml' => $contentXml,
                    'styles.xml'  => $stylesXml,
                    default       => false,
                };
            });
        } else {
            $zip->method('getFromName')->willReturn($contentXml);
        }

        return $zip;
    }

    public function testConvertsOdtFileToHtml(): void
    {
        $odtImporter = $this->createOdtImporter();
        $html = $odtImporter->convert(DemosPlanPath::getTestPath(
            self::BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT
        ));

        // Test basic HTML structure
        $this->assertStringContainsString('<html><body>', $html);
        $this->assertStringContainsString('</body></html>', $html);

        // Test content processing (headings, formatting, tables)
        $this->assertStringContainsString('<h1>Testüberschrift</h1>', $html);
        $this->assertStringContainsString('<p>Mein <strong>fetter</strong> Absatz', $html);
        $this->assertStringContainsString('<table>', $html);
    }

    public function testThrowsExceptionWhenOdtFileCannotBeOpened(): void
    {
        $this->expectException(OdtProcessingException::class);
        $this->expectExceptionMessage('Unable to open ODT file: path/to/nonexistent/file.odt');

        $odtImporter = $this->createOdtImporter();
        $odtImporter->convert('path/to/nonexistent/file.odt');
    }

    public function testReturnsEmptyWhenContentXmlIsMissing(): void
    {
        // Create a minimal ODT file without content.xml
        $tempFile = tempnam(sys_get_temp_dir(), 'test_odt_');
        $zip = new ZipArchive();
        $zip->open($tempFile, ZipArchive::CREATE);
        $zip->addFromString('mimetype', 'application/vnd.oasis.opendocument.text');
        $zip->close();

        $odtImporter = $this->createOdtImporter();
        $html = $odtImporter->convert($tempFile);

        // Should return basic HTML structure even with empty content
        $this->assertStringContainsString('<html><body>', $html);
        $this->assertStringContainsString('</body></html>', $html);

        unlink($tempFile);
    }

    public function testConvertsOdtFileWithTableToHtml(): void
    {
        $odtImporter = $this->createOdtImporter();
        $html = $odtImporter->convert(DemosPlanPath::getTestPath(
            self::BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT
        ));

        // Test that tables are processed correctly
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('<tr>', $html);
        $this->assertStringContainsString('<td', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    public function testConvertsHeadingLevels(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:h text:outline-level="1">Heading 1</text:h><text:h text:outline-level="2">Heading 2</text:h><text:h text:outline-level="3">Heading 3</text:h></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<h1>Heading 1</h1>', $html);
        $this->assertStringContainsString('<h2>Heading 2</h2>', $html);
        $this->assertStringContainsString('<h3>Heading 3</h3>', $html);
    }

    public function testConvertsTableWithSpanning(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><table:table><table:table-row><table:table-cell table:number-columns-spanned="2"><text:p>Colspan 2</text:p></table:table-cell><table:covered-table-cell/></table:table-row><table:table-row><table:table-cell table:number-rows-spanned="2"><text:p>Rowspan 2</text:p></table:table-cell><table:table-cell><text:p>Cell 2</text:p></table:table-cell></table:table-row></table:table></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<td colspan="2" >Colspan 2</td>', $html);
        $this->assertStringContainsString('<td rowspan="2" >Rowspan 2</td>', $html);
        $this->assertStringNotContainsString('covered-table-cell', $html);
    }

    public function testConvertsUnorderedList(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:list><text:list-item><text:p>Item 1</text:p></text:list-item><text:list-item><text:p>Item 2</text:p></text:list-item></text:list></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<ul><li>Item 1</li><li>Item 2</li></ul>', $html);
    }

    public function testConvertsOrderedList(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive(
            '<office:document-content><text:list text:style-name="WWNum4"><text:list-item><text:p>1. First item</text:p></text:list-item><text:list-item><text:p>2. Second item</text:p></text:list-item></text:list></office:document-content>',
            '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum4">
<text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">
</text:list-level-style-number>
</text:list-style>
</office:styles>
</office:document-styles>'
        );
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<ol><li>1. First item</li><li>2. Second item</li></ol>', $html);
    }

    public function testConvertsTextFormatting(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
<office:automatic-styles>
<style:style style:name="T1" style:family="text">
<style:text-properties fo:font-weight="bold"/>
</style:style>
<style:style style:name="T2" style:family="text">
<style:text-properties fo:font-style="italic" style:text-underline-style="solid"/>
</style:style>
</office:automatic-styles>
<office:body>
<office:text>
<text:p>Normal <text:span text:style-name="T1">bold</text:span> and <text:span text:style-name="T2">italic underline</text:span> text</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<p>Normal <strong>bold</strong> and <em><u>italic underline</u></em> text</p>', $html);
    }

    public function testConvertsFootnotes(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>Text with footnote<text:note text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>Footnote content</text:p></text:note-body></text:note></text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<p>Text with footnote<sup title="Footnote content"', $html);
        $this->assertStringContainsString('title="Footnote content"', $html);
        $this->assertStringContainsString('>1</sup></p>', $html);
    }

    public function testConvertsEndnotes(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>Text with endnote<text:note text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>Endnote content</text:p></text:note-body></text:note></text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<p>Text with endnote<sup title="Endnote content"', $html);
        $this->assertStringContainsString('title="Endnote content"', $html);
        $this->assertStringContainsString('>i</sup></p>', $html);
    }

    public function testConvertsSoftPageBreaks(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>Before break<text:soft-page-break/>After break</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<p>Before break<hr class="page-break">After break</p>', $html);
    }

    public function testConvertsNestedLists(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:list><text:list-item><text:p>Item 1</text:p><text:list><text:list-item><text:p>Nested item 1.1</text:p></text:list-item></text:list></text:list-item></text:list></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<ul><li>Item 1<ul><li>Nested item 1.1</li></ul></li></ul>', $html);
    }

    public function testHandlesUnknownStylesGracefully(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>Normal <text:span text:style-name="UnknownStyle">text</text:span> here</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Unknown styles should not cause errors and should return content as-is
        $this->assertStringContainsString('<p>Normal text here</p>', $html);
    }

    /**
     * Test recognition of Zwischenüberschriften patterns like "2.1 Küstenmeer".
     */
    public function testRecognizesZwischenuberschriftenPatterns(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>1 Vernetzung und Kooperation</text:p><text:p>2.1 Küstenmeer</text:p><text:p>3.1.1 Oberzentren</text:p><text:p>3.1.1.1 Sub-level heading</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<h1>1 Vernetzung und Kooperation</h1>', $html);
        $this->assertStringContainsString('<h2>2.1 Küstenmeer</h2>', $html);
        $this->assertStringContainsString('<h3>3.1.1 Oberzentren</h3>', $html);
        $this->assertStringContainsString('<h4>3.1.1.1 Sub-level heading</h4>', $html);
    }

    /**
     * Test that space elements (text:s) are preserved in heading recognition.
     */
    public function testPreservesSpacesInHeadingRecognition(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>7<text:s/>Mobilität der Zukunft</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Should recognize as heading and preserve the space
        $this->assertStringContainsString('<h1>7 Mobilität der Zukunft</h1>', $html);
    }

    /**
     * Test that tab elements (text:tab) are converted to spaces in heading recognition.
     */
    public function testConvertTabsToSpacesInHeadingRecognition(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>7<text:tab/>Mobilität der Zukunft</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Should recognize as heading and convert tab to space
        $this->assertStringContainsString('<h1>7 Mobilität der Zukunft</h1>', $html);
    }

    /**
     * Test that policy items (G/Z patterns) remain as paragraphs, not headings.
     */
    public function testPolicyItemsRemainAsParagraphs(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>1 G</text:p><text:p>2 Z</text:p><text:p>15 G</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // These should remain as paragraphs, NOT become headings
        $this->assertStringContainsString('<p>1 G</p>', $html);
        $this->assertStringContainsString('<p>2 Z</p>', $html);
        $this->assertStringContainsString('<p>15 G</p>', $html);

        // Verify they are NOT headings
        $this->assertStringNotContainsString('<h1>1 G</h1>', $html);
        $this->assertStringNotContainsString('<h1>2 Z</h1>', $html);
    }

    /**
     * Test recognition of parenthetical patterns like "(1) Title".
     */
    public function testRecognizesParentheticalPatterns(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content><text:p>(1) Schleswig-Holstein weiterdenken</text:p><text:p>(2) Landesplanung weiterdenken</text:p></office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<h2>(1) Schleswig-Holstein weiterdenken</h2>', $html);
        $this->assertStringContainsString('<h2>(2) Landesplanung weiterdenken</h2>', $html);
    }

    /**
     * Test that style-based heading detection works.
     */
    public function testStyleBasedHeadingDetection(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
<office:automatic-styles>
<style:style style:name="P1" style:family="paragraph" style:parent-style-name="Heading_1">
</style:style>
<style:style style:name="P2" style:family="paragraph">
<style:text-properties fo:font-weight="bold" fo:font-size="16pt"/>
</style:style>
</office:automatic-styles>
<office:body>
<office:text>
<text:p text:style-name="P1">Style-based Heading 1</text:p>
<text:p text:style-name="P2">Bold Large Text Heading</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        $this->assertStringContainsString('<h1>Style-based Heading 1</h1>', $html);
        $this->assertStringContainsString('<h2>Bold Large Text Heading</h2>', $html);
    }

    /**
     * Test dynamic heading detection based on font properties.
     */
    public function testDynamicHeadingDetection(): void
    {
        // Arrange - Test case similar to "Zwischenüberschrift" style
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
<office:automatic-styles>
<style:style style:name="Zwischenüberschrift" style:family="paragraph" style:parent-style-name="Normsatz_3a__20_Fließtext">
<style:paragraph-properties fo:margin-top="0.423cm" fo:margin-bottom="0.212cm" style:contextual-spacing="false"/>
<style:text-properties fo:font-size="18pt" fo:font-weight="bold" style:font-size-asian="18pt" style:language-asian="zh" style:country-asian="CN" style:font-weight-asian="bold"/>
</style:style>
</office:automatic-styles>
<office:body>
<office:text>
<text:p text:style-name="Zwischenüberschrift">Mit Herausforderungen flexibel umgehen</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert - 18pt bold text with distinctive margins should be detected as level 2 heading
        $this->assertStringContainsString('<h2>Mit Herausforderungen flexibel umgehen</h2>', $html);
    }

    /**
     * Test basic list continuation functionality (LEP document scenario).
     */
    public function testListContinuationBasic(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive(
            '<office:document-content>
                <text:list xml:id="list1" text:style-name="WWNum5">
                    <text:list-item><text:p>First main item</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1" text:style-name="WWNum5">
                    <text:list-item><text:p>Second main item</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1" text:style-name="WWNum5">
                    <text:list-item><text:p>Third main item</text:p></text:list-item>
                </text:list>
            </office:document-content>',
            self::STYLES_XML_TEMPLATE
        );
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // First list should start at 1 (no start attribute)
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>First main item</li>', $html);
        // Second list should continue from 2
        $this->assertStringContainsString('<ol start="2">', $html);
        $this->assertStringContainsString('<li>Second main item</li>', $html);
        // Third list should continue from 3
        $this->assertStringContainsString('<ol start="3">', $html);
        $this->assertStringContainsString('<li>Third main item</li>', $html);
    }

    /**
     * Test list continuation with multiple items in each list.
     */
    public function testListContinuationMultipleItems(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive(
            '<office:document-content>
                <text:list xml:id="list1" text:style-name="WWNum5">
                    <text:list-item><text:p>Item 1.1</text:p></text:list-item>
                    <text:list-item><text:p>Item 1.2</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1" text:style-name="WWNum5">
                    <text:list-item><text:p>Item 2.1</text:p></text:list-item>
                    <text:list-item><text:p>Item 2.2</text:p></text:list-item>
                </text:list>
            </office:document-content>',
            self::STYLES_XML_TEMPLATE
        );
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // First list should start at 1 and contain 2 items
        $this->assertStringContainsString('<ol>', $html);
        $this->assertStringContainsString('<li>Item 1.1</li>', $html);
        $this->assertStringContainsString('<li>Item 1.2</li>', $html);
        // Second list should continue from 3 (after the 2 items in the first list)
        $this->assertStringContainsString('<ol start="3">', $html);
        $this->assertStringContainsString('<li>Item 2.1</li>', $html);
        $this->assertStringContainsString('<li>Item 2.2</li>', $html);
    }

    /**
     * Test that unordered lists are not affected by continuation logic.
     */
    public function testUnorderedListsIgnoreContinuation(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive(
            '<office:document-content>
                <text:list xml:id="list1" text:style-name="WWNum1">
                    <text:list-item><text:p>Bullet item 1</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1" text:style-name="WWNum1">
                    <text:list-item><text:p>Bullet item 2</text:p></text:list-item>
                </text:list>
            </office:document-content>',
            '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum1">
<text:list-level-style-bullet text:level="1" text:bullet-char="-">
</text:list-level-style-bullet>
</text:list-style>
</office:styles>
</office:document-styles>'
        );
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Both should be unordered lists without start attributes
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Bullet item 1</li>', $html);
        $this->assertStringContainsString('<li>Bullet item 2</li>', $html);
        // Should not contain start attributes or ordered list tags
        $this->assertStringNotContainsString('start=', $html);
        $this->assertStringNotContainsString('<ol>', $html);
    }

    /**
     * Test LEP document structure specifically (matches the real document).
     */
    public function testLEPDocumentListStructure(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive(
            '<office:document-content>
                <text:p>Dafür sollen folgende Ansätze verfolgt werden:</text:p>
                <text:list xml:id="list1115186218" text:style-name="WWNum5">
                    <text:list-item><text:p>Stärkung der Wettbewerbsfähigkeit und Attraktivität des Wirtschafts- und Lebensraums Metropolregion Hamburg</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1115186218" text:style-name="WWNum5">
                    <text:list-item><text:p>Erhöhung der nationalen und internationalen Sichtbarkeit der Metropolregion Hamburg</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1115186218" text:style-name="WWNum5">
                    <text:list-item><text:p>Ausbau und Weiterentwicklung der Zusammenarbeit in der Organisation Metropolregion Hamburg</text:p></text:list-item>
                </text:list>
                <text:list text:continue-list="list1115186218" text:style-name="WWNum5">
                    <text:list-item><text:p>Verbesserung der Standortbedingungen im zur Metropolregion Hamburg gehörenden Teil des Landes</text:p></text:list-item>
                </text:list>
            </office:document-content>',
            self::STYLES_XML_TEMPLATE
        );
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Should produce correctly numbered sequence: 1., 2., 3., 4.
        $this->assertStringContainsString('<li>Stärkung der Wettbewerbsfähigkeit', $html);
        $this->assertStringContainsString('<ol start="2">', $html);
        $this->assertStringContainsString('<li>Erhöhung der nationalen', $html);
        $this->assertStringContainsString('<ol start="3">', $html);
        $this->assertStringContainsString('<li>Ausbau und Weiterentwicklung', $html);
        $this->assertStringContainsString('<ol start="4">', $html);
        $this->assertStringContainsString('<li>Verbesserung der Standortbedingungen', $html);
    }

    /**
     * Test LEP document "Zwischenüberschrift" heading detection with real ODT data.
     */
    public function testLEPDocumentZwischenuberschriftDetection(): void
    {
        // Arrange - Real ODT structure from LEP document with Zwischenüberschrift style in styles.xml (like the real file)
        $zip = $this->createMockedZipArchive(
            '<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
<office:automatic-styles>
<style:style style:name="Normsatz_3a__20_Fließtext" style:display-name="Normsatz: Fließtext" style:family="paragraph" style:parent-style-name="Standard"/>
</office:automatic-styles>
<office:body>
<office:text>
<text:h text:outline-level="1" text:style-name="Kapitelüberschrift">
<text:bookmark-start text:name="_Toc84925075"/>
(1) Schleswig-Holstein – Zukunft flexibel, gemeinsam und nachhaltig gestalten
<text:bookmark-end text:name="_Toc84925075"/>
</text:h>
<text:p text:style-name="Zwischenüberschrift">
<text:bookmark-start text:name="_Toc49262434"/>
Mit Herausforderungen flexibel umgehen
<text:bookmark-end text:name="_Toc49262434"/>
</text:p>
<text:p text:style-name="Normsatz_3a__20_Fließtext">
Seit der Veröffentlichung des Landesentwicklungsplans 2010 haben sich viele gesellschaftliche und wirtschaftliche Rahmenbedingungen verändert...
</text:p>
<text:p text:style-name="Zwischenüberschrift">
Gestaltungschancen nutzen – Innovationen fördern (Experimentierklausel)
</text:p>
<text:p text:style-name="Zwischenüberschrift">
Zukunft anpacken – Hand in Hand
</text:p>
</office:text>
</office:body>
</office:document-content>',
            '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:fo="urn:oasis:names:tc:opendocument:xmlns:xsl-fo-compatible:1.0">
<office:styles>
<style:style style:name="Zwischenüberschrift" style:family="paragraph" style:parent-style-name="Normsatz_3a__20_Fließtext">
<style:paragraph-properties fo:margin-top="0.423cm" fo:margin-bottom="0.212cm" style:contextual-spacing="false"/>
<style:text-properties fo:font-size="18pt" fo:font-weight="bold" style:font-size-asian="18pt" style:language-asian="zh" style:country-asian="CN" style:font-weight-asian="bold"/>
</style:style>
</office:styles>
</office:document-styles>'
        );
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert - The main heading that was previously missed should now be detected (with whitespace tolerance)
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*\(1\) Schleswig-Holstein[^<]*<\/h1>/s', $html,
            'Proper ODT heading should be detected');

        // The Zwischenüberschrift paragraphs should be detected as headings (18pt bold = level 2)
        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Mit Herausforderungen flexibel umgehen\s*<\/h2>/s', $html,
            'Zwischenüberschrift "Mit Herausforderungen flexibel umgehen" should be detected as h2');

        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Gestaltungschancen nutzen[^<]*<\/h2>/s', $html,
            'Zwischenüberschrift about Gestaltungschancen should be detected as h2');

        $this->assertMatchesRegularExpression('/<h2[^>]*>\s*Zukunft anpacken[^<]*<\/h2>/s', $html,
            'Zwischenüberschrift "Zukunft anpacken" should be detected as h2');

        // Verify these are NOT treated as regular paragraphs
        $this->assertStringNotContainsString('<p>Mit Herausforderungen flexibel umgehen</p>', $html,
            'Should not be treated as regular paragraph');
        $this->assertStringNotContainsString('<p>Gestaltungschancen nutzen – Innovationen fördern (Experimentierklausel)</p>', $html,
            'Should not be treated as regular paragraph');

        // Regular text should remain as paragraph
        $this->assertMatchesRegularExpression('/<p[^>]*>\s*Seit der Veröffentlichung des Landesentwicklungsplans 2010/s', $html,
            'Regular text should remain as paragraph');
    }

    /**
     * Test table header rows processing - reproduces LEP document issue.
     */
    public function testProcessesTableHeaderRows(): void
    {
        // Arrange
        $zip = $this->createMockedZipArchive('<office:document-content>
            <table:table table:name="Tabelle1" table:style-name="Tabelle1">
                <table:table-column table:style-name="Tabelle1.A"/>
                <table:table-column table:style-name="Tabelle1.B"/>
                <table:table-header-rows>
                    <table:table-row table:style-name="Tabelle1.1">
                        <table:table-cell table:style-name="Tabelle1.A1" office:value-type="string">
                            <text:p text:style-name="P173">
                                <text:span text:style-name="T50">Arbeitsplatzzentralität Kennziffer Sozial. Besch. a. A. je Einwohnerinnen und Einwohner</text:span>
                            </text:p>
                        </table:table-cell>
                        <table:table-cell table:style-name="Tabelle1.B1" office:value-type="string">
                            <text:p text:style-name="P173">
                                <text:span text:style-name="T50">Header 2</text:span>
                            </text:p>
                        </table:table-cell>
                    </table:table-row>
                </table:table-header-rows>
                <table:table-row>
                    <table:table-cell><text:p>Data 1</text:p></table:table-cell>
                    <table:table-cell><text:p>Data 2</text:p></table:table-cell>
                </table:table-row>
            </table:table>
        </office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Table headers should now be processed and included in output
        $this->assertStringContainsString('<table>', $html);
        $this->assertStringContainsString('Arbeitsplatzzentralität Kennziffer Sozial. Besch.', $html);
        $this->assertStringContainsString('Header 2', $html);
        $this->assertStringContainsString('Data 1', $html);
        $this->assertStringContainsString('Data 2', $html);
        $this->assertStringContainsString('</table>', $html);
    }

    /**
     * Test that table-of-content elements are removed during structural filtering.
     */
    public function testRemovesTableOfContents(): void
    {
        // Arrange - Document with TOC and real content
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:xlink="http://www.w3.org/1999/xlink">
<office:body>
<office:text>
<text:table-of-content text:protected="true" text:name="Inhaltsverzeichnis1">
    <text:table-of-content-source text:outline-level="3">
        <text:index-title-template>Inhaltsverzeichnis</text:index-title-template>
        <text:table-of-content-entry-template text:outline-level="1">
            <text:index-entry-link-start/>
            <text:index-entry-chapter/>
            <text:index-entry-text/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
        </text:table-of-content-entry-template>
    </text:table-of-content-source>
    <text:index-body>
        <text:index-title>
            <text:h text:outline-level="1">Inhaltsverzeichnis</text:h>
        </text:index-title>
        <text:p>
            <text:a xlink:href="#_Toc123">
                <text:span>Rechtlicher Rahmen und Aufbau</text:span>
            </text:a>
        </text:p>
    </text:index-body>
</text:table-of-content>
<text:h text:outline-level="1">
    <text:bookmark-start text:name="_Toc123"/>Rechtlicher Rahmen und Aufbau<text:bookmark-end text:name="_Toc123"/>
</text:h>
<text:p>This is the actual document content that should be preserved.</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // TOC content should be completely removed
        $this->assertStringNotContainsString('Inhaltsverzeichnis', $html);
        $this->assertStringNotContainsString('table-of-content', $html);
        $this->assertStringNotContainsString('index-entry', $html);

        // Actual content should be preserved (allowing for whitespace in HTML output)
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Rechtlicher Rahmen und Aufbau\s*<\/h1>/s', $html);
        $this->assertStringContainsString('<p>This is the actual document content', $html);
    }

    /**
     * Test that illustration-index elements are removed during structural filtering.
     */
    public function testRemovesIllustrationIndexes(): void
    {
        // Arrange - Document with illustration index and real content
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:xlink="http://www.w3.org/1999/xlink">
<office:body>
<office:text>
<text:illustration-index text:protected="true" text:name="Abbildungsverzeichnis1">
    <text:illustration-index-source text:caption-sequence-name="Illustration">
        <text:index-title-template>Figure Index</text:index-title-template>
        <text:illustration-index-entry-template>
            <text:index-entry-link-start/>
            <text:index-entry-text/>
            <text:index-entry-page-number/>
            <text:index-entry-link-end/>
        </text:illustration-index-entry-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>
            <text:a xlink:href="#_Figure1">
                <text:span>Abbildung 1 Modell Mehrebenengovernance</text:span>
            </text:a>
        </text:p>
    </text:index-body>
</text:illustration-index>
<text:h text:outline-level="1">Real Document Content</text:h>
<text:p>This is the actual content after the illustration index.</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Illustration index should be completely removed
        $this->assertStringNotContainsString('illustration-index', $html);
        $this->assertStringNotContainsString('Figure Index', $html);
        $this->assertStringNotContainsString('Abbildung 1 Modell Mehrebenengovernance', $html);

        // Actual content should be preserved
        $this->assertStringContainsString('<h1>Real Document Content</h1>', $html);
        $this->assertStringContainsString('<p>This is the actual content after', $html);
    }

    /**
     * Test that index headings preceding structural elements are removed.
     */
    public function testRemovesIndexHeadings(): void
    {
        // Arrange - Document with index heading followed by structural element
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:body>
<office:text>
<text:h text:outline-level="1">Abbildungsverzeichnis</text:h>
<text:illustration-index text:protected="true" text:name="Abbildungsverzeichnis1">
    <text:illustration-index-source>
        <text:index-title-template>Figure Index</text:index-title-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>Figure 1: Sample Figure</text:p>
    </text:index-body>
</text:illustration-index>
<text:h text:outline-level="1">Verzeichnis der Themenkarten</text:h>
<text:illustration-index text:protected="true" text:name="Abbildungsverzeichnis2">
    <text:illustration-index-source>
        <text:index-title-template>Map Index</text:index-title-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>Themenkarte 1: Sample Map</text:p>
    </text:index-body>
</text:illustration-index>
<text:h text:outline-level="1">Rechtlicher Rahmen und Aufbau</text:h>
<text:p>This is the actual document content.</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Index headings should be removed along with their structural elements
        $this->assertStringNotContainsString('<h1>Abbildungsverzeichnis</h1>', $html);
        $this->assertStringNotContainsString('<h1>Verzeichnis der Themenkarten</h1>', $html);
        $this->assertStringNotContainsString('Figure 1: Sample Figure', $html);
        $this->assertStringNotContainsString('Themenkarte 1: Sample Map', $html);

        // Actual content heading should be preserved
        $this->assertStringContainsString('<h1>Rechtlicher Rahmen und Aufbau</h1>', $html);
        $this->assertStringContainsString('<p>This is the actual document content.</p>', $html);
    }

    /**
     * Test backward compatibility - documents without structural elements should work normally.
     */
    public function testBackwardCompatibilityWithoutStructuralElements(): void
    {
        // Arrange - Simple document without any structural elements
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:body>
<office:text>
<text:h text:outline-level="1">Document Title</text:h>
<text:p>First paragraph of content.</text:p>
<text:h text:outline-level="2">Section Heading</text:h>
<text:p>Second paragraph with more content.</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // All content should be preserved exactly as before
        $this->assertStringContainsString('<h1>Document Title</h1>', $html);
        $this->assertStringContainsString('<p>First paragraph of content.</p>', $html);
        $this->assertStringContainsString('<h2>Section Heading</h2>', $html);
        $this->assertStringContainsString('<p>Second paragraph with more content.</p>', $html);

        // Should not affect processing of normal documents
        $this->assertStringContainsString('<html><body>', $html);
        $this->assertStringContainsString('</body></html>', $html);
    }

    /**
     * Test real-world LEP document structural filtering scenario.
     */
    public function testFiltersLEPDocumentStructure(): void
    {
        // Arrange - Simplified version of actual LEP document structure
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" xmlns:xlink="http://www.w3.org/1999/xlink">
<office:body>
<office:text>
<text:h text:outline-level="1">Landesentwicklungsplan Schleswig-Holstein Fortschreibung 2021</text:h>
<text:p>Ministerium für Inneres, ländliche Räume, Integration und Gleichstellung</text:p>

<text:table-of-content text:protected="true" text:name="Inhaltsverzeichnis1">
    <text:table-of-content-source text:outline-level="3">
        <text:index-title-template>Inhaltsverzeichnis</text:index-title-template>
    </text:table-of-content-source>
    <text:index-body>
        <text:index-title>
            <text:h text:outline-level="1">Inhaltsverzeichnis</text:h>
        </text:index-title>
        <text:p>
            <text:a xlink:href="#_Toc84925070">
                <text:span>Abbildungsverzeichnis</text:span>
            </text:a>
        </text:p>
        <text:p>
            <text:a xlink:href="#_Toc84925073">
                <text:span>Rechtlicher Rahmen und Aufbau</text:span>
            </text:a>
        </text:p>
    </text:index-body>
</text:table-of-content>

<text:h text:outline-level="1">Abbildungsverzeichnis</text:h>
<text:illustration-index text:protected="true" text:name="Abbildungsverzeichnis1">
    <text:illustration-index-source text:caption-sequence-name="Illustration">
        <text:index-title-template>Figure Index</text:index-title-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>
            <text:a xlink:href="#_Figure1">
                <text:span>Abbildung 1 Modell Mehrebenengovernance</text:span>
            </text:a>
        </text:p>
    </text:index-body>
</text:illustration-index>

<text:h text:outline-level="1">Verzeichnis der Themenkarten</text:h>
<text:illustration-index text:protected="true" text:name="Abbildungsverzeichnis2">
    <text:illustration-index-source text:caption-sequence-name="Themenkarte">
        <text:index-title-template>Map Index</text:index-title-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>
            <text:a xlink:href="#_Map1">
                <text:span>Themenkarte 1: Raumstruktur</text:span>
            </text:a>
        </text:p>
    </text:index-body>
</text:illustration-index>

<text:h text:outline-level="1">Abkürzungsverzeichnis</text:h>
<text:p>AIS → Automatic Identification System</text:p>
<text:p>ALKIS → Amtliches Liegenschaftskataster-Informationssystem</text:p>

<text:h text:outline-level="1">
    <text:bookmark-start text:name="_Toc84925073"/>Rechtlicher Rahmen und Aufbau<text:bookmark-end text:name="_Toc84925073"/>
</text:h>
<text:p>Der Landesentwicklungsplan (LEP) ist das zentrale Instrument der Raumordnung in Schleswig-Holstein.</text:p>

<text:h text:outline-level="1">Teil A Herausforderungen, Chancen und strategische Handlungsfelder</text:h>
<text:p>Schleswig-Holstein steht vor verschiedenen Herausforderungen und Chancen.</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // All structural elements should be removed
        $this->assertStringNotContainsString('table-of-content', $html);
        $this->assertStringNotContainsString('<h1>Inhaltsverzeichnis</h1>', $html);
        $this->assertStringNotContainsString('<h1>Abbildungsverzeichnis</h1>', $html);
        $this->assertStringNotContainsString('<h1>Verzeichnis der Themenkarten</h1>', $html);
        $this->assertStringNotContainsString('Abbildung 1 Modell Mehrebenengovernance', $html);
        $this->assertStringNotContainsString('Themenkarte 1: Raumstruktur', $html);

        // Document title and legitimate content should be preserved
        $this->assertStringContainsString('<h1>Landesentwicklungsplan Schleswig-Holstein Fortschreibung 2021</h1>', $html);
        $this->assertStringContainsString('<h1>Abkürzungsverzeichnis</h1>', $html); // This is legitimate content, not an index heading
        $this->assertStringContainsString('AIS → Automatic Identification System', $html);

        // Main content sections should be preserved and easily accessible (allowing for whitespace)
        $this->assertMatchesRegularExpression('/<h1[^>]*>\s*Rechtlicher Rahmen und Aufbau\s*<\/h1>/s', $html);
        $this->assertStringContainsString('Der Landesentwicklungsplan (LEP) ist das zentrale Instrument', $html);
        $this->assertStringContainsString('<h1>Teil A Herausforderungen, Chancen und strategische Handlungsfelder</h1>', $html);
        $this->assertStringContainsString('Schleswig-Holstein steht vor verschiedenen Herausforderungen', $html);
    }

    /**
     * Test that multiple structural elements of the same type are all removed.
     */
    public function testRemovesMultipleStructuralElements(): void
    {
        // Arrange - Document with multiple illustration indexes
        $zip = $this->createMockedZipArchive('<?xml version="1.0" encoding="UTF-8"?>
<office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:body>
<office:text>
<text:illustration-index text:protected="true" text:name="Index1">
    <text:illustration-index-source text:caption-sequence-name="Illustration">
        <text:index-title-template>Figures</text:index-title-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>Figure 1: Chart</text:p>
    </text:index-body>
</text:illustration-index>

<text:illustration-index text:protected="true" text:name="Index2">
    <text:illustration-index-source text:caption-sequence-name="Table">
        <text:index-title-template>Tables</text:index-title-template>
    </text:illustration-index-source>
    <text:index-body>
        <text:p>Table 1: Data</text:p>
    </text:index-body>
</text:illustration-index>

<text:h text:outline-level="1">Actual Content</text:h>
<text:p>This should be preserved.</text:p>
</office:text>
</office:body>
</office:document-content>');
        $odtImporter = $this->createOdtImporter($zip);

        // Act
        $html = $odtImporter->convert('test.odt');

        // Assert
        // Both indexes should be removed
        $this->assertStringNotContainsString('Figure 1: Chart', $html);
        $this->assertStringNotContainsString('Table 1: Data', $html);
        $this->assertStringNotContainsString('illustration-index', $html);

        // Actual content should remain
        $this->assertStringContainsString('<h1>Actual Content</h1>', $html);
        $this->assertStringContainsString('<p>This should be preserved.</p>', $html);
    }
}
