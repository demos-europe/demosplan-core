<?php
declare(strict_types=1);

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\Tools\OdtImporter;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use PHPUnit\Framework\TestCase;
use ZipArchive;

class OdtImporterTest extends TestCase
{
    const BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT = 'backend/core/Import/res/SimpleDoc.odt';
    private $odtImporter;

    protected function setUp(): void
    {
        $this->odtImporter = new OdtImporter();
    }

    public function testConvertsOdtFileToHtml()
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><text:p>Hello World</text:p></office:document-content>');
        $zip->method('close')->willReturn(true);

        $html = $this->odtImporter->convert(DemosPlanPath::getTestPath(
            self::BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT
        ));
        $this->assertStringContainsString('<p>Hello World</p>', $html);
    }

    public function testThrowsExceptionWhenOdtFileCannotBeOpened()
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(false);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to open ODT file.');

        $this->odtImporter->convert('path/to/file.odt');
    }

    public function testThrowsExceptionWhenContentXmlIsMissing()
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn(false);
        $zip->method('close')->willReturn(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unable to open ODT file.');

        $this->odtImporter->convert('path/to/file.odt');
    }

    public function testConvertsOdtFileWithTableToHtml()
    {
        $zip = $this->createMock(ZipArchive::class);
        $zip->method('open')->willReturn(true);
        $zip->method('getFromName')->willReturn('<office:document-content><table:table><table:table-row><table:table-cell><text:p>Cell 1</text:p></table:table-cell><table:table-cell><text:p>Cell 2</text:p></table:table-cell></table:table-row></table:table></office:document-content>');
        $zip->method('close')->willReturn(true);

        $html = $this->odtImporter->convert(DemosPlanPath::getTestPath(
            self::BACKEND_CORE_IMPORT_RES_SIMPLE_DOC_ODT
        ));
        $this->assertStringContainsString('<table><tr><td>Cell 1</td><td>Cell 2</td></tr></table>', $html);
    }
}
