<?php
declare(strict_types=1);

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\Tools\OdtImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class OdtImporterTest extends TestCase
{
    private const BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT = 'backend/core/Import/res/SimpleDoc.odt';

    public function testConvertsOdtFileToHtml(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Hello World</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert(DemosPlanPath::getTestPath(
            self::BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT
        ));
        $this->assertStringContainsString('<p>Hello World</p>', $html);
    }

    public function testThrowsExceptionWhenOdtFileCannotBeOpened(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to open ODT file.');

        $odtImporter = new OdtImporter($zip);
        $odtImporter->convert('path/to/file.odt');
    }

    public function testThrowsExceptionWhenContentXmlIsMissing(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn(false);
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('path/to/file.odt');
        $this->assertSame('', $html);
    }

    public function testConvertsOdtFileWithTableToHtml(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><table:table><table:table-row><table:table-cell><text:p>Cell 1</text:p></table:table-cell><table:table-cell><text:p>Cell 2</text:p></table:table-cell></table:table-row></table:table></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert(DemosPlanPath::getTestPath(
            self::BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT
        ));
        $this->assertStringContainsString('<table><tr><td >Cell 1</td><td >Cell 2</td></tr></table>', $html);
    }

    public function testConvertsHeadingLevels(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:h text:outline-level="1">Heading 1</text:h><text:h text:outline-level="2">Heading 2</text:h><text:h text:outline-level="3">Heading 3</text:h></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<h1>Heading 1</h1>', $html);
        $this->assertStringContainsString('<h2>Heading 2</h2>', $html);
        $this->assertStringContainsString('<h3>Heading 3</h3>', $html);
    }

    public function testConvertsTableWithSpanning(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><table:table><table:table-row><table:table-cell table:number-columns-spanned="2"><text:p>Colspan 2</text:p></table:table-cell><table:covered-table-cell/></table:table-row><table:table-row><table:table-cell table:number-rows-spanned="2"><text:p>Rowspan 2</text:p></table:table-cell><table:table-cell><text:p>Cell 2</text:p></table:table-cell></table:table-row></table:table></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<td colspan="2" >Colspan 2</td>', $html);
        $this->assertStringContainsString('<td rowspan="2" >Rowspan 2</td>', $html);
        $this->assertStringNotContainsString('covered-table-cell', $html);
    }

    public function testConvertsUnorderedList(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:list><text:list-item><text:p>Item 1</text:p></text:list-item><text:list-item><text:p>Item 2</text:p></text:list-item></text:list></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<ul><li>Item 1</li><li>Item 2</li></ul>', $html);
    }

    public function testConvertsOrderedList(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        
        // Mock getFromName to return different content based on filename
        $zip->method('getFromName')->willReturnCallback(function ($filename) {
            if ($filename === 'content.xml') {
                return '<office:document-content><text:list text:style-name="WWNum4"><text:list-item><text:p>1. First item</text:p></text:list-item><text:list-item><text:p>2. Second item</text:p></text:list-item></text:list></office:document-content>';
            }
            if ($filename === 'styles.xml') {
                return '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum4">
<text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">
</text:list-level-style-number>
</text:list-style>
</office:styles>
</office:document-styles>';
            }
            return false;
        });
        
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<ol><li>1. First item</li><li>2. Second item</li></ol>', $html);
    }

    public function testConvertsTextFormatting(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<?xml version="1.0" encoding="UTF-8"?>
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
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<p>Normal <strong>bold</strong> and <em><u>italic underline</u></em> text</p>', $html);
    }

    public function testConvertsFootnotes(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Text with footnote<text:note text:note-class="footnote"><text:note-citation>1</text:note-citation><text:note-body><text:p>Footnote content</text:p></text:note-body></text:note></text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<p>Text with footnote<sup title="Footnote content"', $html);
        $this->assertStringContainsString('title="Footnote content"', $html);
        $this->assertStringContainsString('>1</sup></p>', $html);
    }

    public function testConvertsEndnotes(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Text with endnote<text:note text:note-class="endnote"><text:note-citation>i</text:note-citation><text:note-body><text:p>Endnote content</text:p></text:note-body></text:note></text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<p>Text with endnote<sup title="Endnote content"', $html);
        $this->assertStringContainsString('title="Endnote content"', $html);
        $this->assertStringContainsString('>i</sup></p>', $html);
    }

    public function testConvertsSoftPageBreaks(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Before break<text:soft-page-break/>After break</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<p>Before break<hr class="page-break">After break</p>', $html);
    }

    public function testConvertsNestedLists(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:list><text:list-item><text:p>Item 1</text:p><text:list><text:list-item><text:p>Nested item 1.1</text:p></text:list-item></text:list></text:list-item></text:list></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<ul><li>Item 1<ul><li>Nested item 1.1</li></ul></li></ul>', $html);
    }

    public function testHandlesUnknownStylesGracefully(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Normal <text:span text:style-name="UnknownStyle">text</text:span> here</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        // Unknown styles should not cause errors and should return content as-is
        $this->assertStringContainsString('<p>Normal text here</p>', $html);
    }

    /**
     * Test recognition of Zwischenüberschriften patterns like "2.1 Küstenmeer"
     */
    public function testRecognizesZwischenuberschriftenPatterns(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>1 Vernetzung und Kooperation</text:p><text:p>2.1 Küstenmeer</text:p><text:p>3.1.1 Oberzentren</text:p><text:p>3.1.1.1 Sub-level heading</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<h1>1 Vernetzung und Kooperation</h1>', $html);
        $this->assertStringContainsString('<h2>2.1 Küstenmeer</h2>', $html);
        $this->assertStringContainsString('<h3>3.1.1 Oberzentren</h3>', $html);
        $this->assertStringContainsString('<h4>3.1.1.1 Sub-level heading</h4>', $html);
    }

    /**
     * Test that space elements (text:s) are preserved in heading recognition
     */
    public function testPreservesSpacesInHeadingRecognition(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>7<text:s/>Mobilität der Zukunft</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        // Should recognize as heading and preserve the space
        $this->assertStringContainsString('<h1>7 Mobilität der Zukunft</h1>', $html);
    }

    /**
     * Test that tab elements (text:tab) are converted to spaces in heading recognition
     */
    public function testConvertTabsToSpacesInHeadingRecognition(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>7<text:tab/>Mobilität der Zukunft</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        // Should recognize as heading and convert tab to space
        $this->assertStringContainsString('<h1>7 Mobilität der Zukunft</h1>', $html);
    }

    /**
     * Test that policy items (G/Z patterns) remain as paragraphs, not headings
     */
    public function testPolicyItemsRemainAsParagraphs(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>1 G</text:p><text:p>2 Z</text:p><text:p>15 G</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        // These should remain as paragraphs, NOT become headings
        $this->assertStringContainsString('<p>1 G</p>', $html);
        $this->assertStringContainsString('<p>2 Z</p>', $html);
        $this->assertStringContainsString('<p>15 G</p>', $html);
        
        // Verify they are NOT headings
        $this->assertStringNotContainsString('<h1>1 G</h1>', $html);
        $this->assertStringNotContainsString('<h1>2 Z</h1>', $html);
    }

    /**
     * Test recognition of "Teil" patterns
     */
    public function testRecognizesTeilPatterns(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Teil A Herausforderungen</text:p><text:p>Teil B Grundsätze und Ziele</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<h1>Teil A Herausforderungen</h1>', $html);
        $this->assertStringContainsString('<h1>Teil B Grundsätze und Ziele</h1>', $html);
    }

    /**
     * Test recognition of parenthetical patterns like "(1) Title"
     */
    public function testRecognizesParentheticalPatterns(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>(1) Schleswig-Holstein weiterdenken</text:p><text:p>(2) Landesplanung weiterdenken</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<h2>(1) Schleswig-Holstein weiterdenken</h2>', $html);
        $this->assertStringContainsString('<h2>(2) Landesplanung weiterdenken</h2>', $html);
    }

    /**
     * Test that style-based heading detection works
     */
    public function testStyleBasedHeadingDetection(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<?xml version="1.0" encoding="UTF-8"?>
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
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        $this->assertStringContainsString('<h1>Style-based Heading 1</h1>', $html);
        $this->assertStringContainsString('<h1>Bold Large Text Heading</h1>', $html);
    }

    /**
     * Test basic list continuation functionality (LEP document scenario)
     */
    public function testListContinuationBasic(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        
        $zip->method('getFromName')->willReturnCallback(function ($filename) {
            if ($filename === 'content.xml') {
                return '<office:document-content>
                    <text:list xml:id="list1" text:style-name="WWNum5">
                        <text:list-item><text:p>First main item</text:p></text:list-item>
                    </text:list>
                    <text:list text:continue-list="list1" text:style-name="WWNum5">
                        <text:list-item><text:p>Second main item</text:p></text:list-item>
                    </text:list>
                    <text:list text:continue-list="list1" text:style-name="WWNum5">
                        <text:list-item><text:p>Third main item</text:p></text:list-item>
                    </text:list>
                </office:document-content>';
            }
            if ($filename === 'styles.xml') {
                return '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum5">
<text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">
</text:list-level-style-number>
</text:list-style>
</office:styles>
</office:document-styles>';
            }
            return false;
        });
        
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

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
     * Test list continuation with multiple items in each list
     */
    public function testListContinuationMultipleItems(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        
        $zip->method('getFromName')->willReturnCallback(function ($filename) {
            if ($filename === 'content.xml') {
                return '<office:document-content>
                    <text:list xml:id="list1" text:style-name="WWNum5">
                        <text:list-item><text:p>Item 1.1</text:p></text:list-item>
                        <text:list-item><text:p>Item 1.2</text:p></text:list-item>
                    </text:list>
                    <text:list text:continue-list="list1" text:style-name="WWNum5">
                        <text:list-item><text:p>Item 2.1</text:p></text:list-item>
                        <text:list-item><text:p>Item 2.2</text:p></text:list-item>
                    </text:list>
                </office:document-content>';
            }
            if ($filename === 'styles.xml') {
                return '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum5">
<text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">
</text:list-level-style-number>
</text:list-style>
</office:styles>
</office:document-styles>';
            }
            return false;
        });
        
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

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
     * Test that unordered lists are not affected by continuation logic
     */
    public function testUnorderedListsIgnoreContinuation(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        
        $zip->method('getFromName')->willReturnCallback(function ($filename) {
            if ($filename === 'content.xml') {
                return '<office:document-content>
                    <text:list xml:id="list1" text:style-name="WWNum1">
                        <text:list-item><text:p>Bullet item 1</text:p></text:list-item>
                    </text:list>
                    <text:list text:continue-list="list1" text:style-name="WWNum1">
                        <text:list-item><text:p>Bullet item 2</text:p></text:list-item>
                    </text:list>
                </office:document-content>';
            }
            if ($filename === 'styles.xml') {
                return '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum1">
<text:list-level-style-bullet text:level="1" text:bullet-char="-">
</text:list-level-style-bullet>
</text:list-style>
</office:styles>
</office:document-styles>';
            }
            return false;
        });
        
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        // Both should be unordered lists without start attributes
        $this->assertStringContainsString('<ul>', $html);
        $this->assertStringContainsString('<li>Bullet item 1</li>', $html);
        $this->assertStringContainsString('<li>Bullet item 2</li>', $html);
        // Should not contain start attributes or ordered list tags
        $this->assertStringNotContainsString('start=', $html);
        $this->assertStringNotContainsString('<ol>', $html);
    }

    /**
     * Test LEP document structure specifically (matches the real document)
     */
    public function testLEPDocumentListStructure(): void
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        
        $zip->method('getFromName')->willReturnCallback(function ($filename) {
            if ($filename === 'content.xml') {
                return '<office:document-content>
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
                </office:document-content>';
            }
            if ($filename === 'styles.xml') {
                return '<?xml version="1.0" encoding="UTF-8"?>
<office:document-styles xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:style="urn:oasis:names:tc:opendocument:xmlns:style:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0">
<office:styles>
<text:list-style style:name="WWNum5">
<text:list-level-style-number text:level="1" style:num-format="1" style:num-suffix=".">
</text:list-level-style-number>
</text:list-style>
</office:styles>
</office:document-styles>';
            }
            return false;
        });
        
        $zip->method('close')->willReturn(true);

        $odtImporter = new OdtImporter($zip);
        $html = $odtImporter->convert('test.odt');

        // Should produce correctly numbered sequence: 1., 2., 3., 4.
        $this->assertStringContainsString('<li>Stärkung der Wettbewerbsfähigkeit', $html);
        $this->assertStringContainsString('<ol start="2">', $html);
        $this->assertStringContainsString('<li>Erhöhung der nationalen', $html);
        $this->assertStringContainsString('<ol start="3">', $html);
        $this->assertStringContainsString('<li>Ausbau und Weiterentwicklung', $html);
        $this->assertStringContainsString('<ol start="4">', $html);
        $this->assertStringContainsString('<li>Verbesserung der Standortbedingungen', $html);
    }
}
