<?php
declare(strict_types=1);

namespace Tests\Core\SegmentExport;

use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use PHPUnit\Framework\TestCase;

class StyleInitializerTest extends TestCase
{
    public function testInitialize(): void
    {
        $styleInitializer = new StyleInitializer();
        $styles = $styleInitializer->initialize();

        static::assertIsArray($styles);
        static::assertArrayHasKey('globalSection', $styles);
        static::assertArrayHasKey('globalFont', $styles);
        static::assertArrayHasKey('documentTitleFont', $styles);
        static::assertArrayHasKey('documentTitleParagraph', $styles);
        static::assertArrayHasKey('currentDateFont', $styles);
        static::assertArrayHasKey('currentDateParagraph', $styles);
        static::assertArrayHasKey('statementInfoTable', $styles);
        static::assertArrayHasKey('statementInfoTextCell', $styles);
        static::assertArrayHasKey('statementInfoEmptyCell', $styles);
        static::assertArrayHasKey('noInfoMessageFont', $styles);
        static::assertArrayHasKey('segmentsTable', $styles);
        static::assertArrayHasKey('segmentsTableHeaderRow', $styles);
        static::assertArrayHasKey('segmentsTableHeaderRowHeight', $styles);
        static::assertArrayHasKey('segmentsTableHeaderCell', $styles);
        static::assertArrayHasKey('segmentsTableBodyCell', $styles);
        static::assertArrayHasKey('segmentsTableHeaderCellID', $styles);
        static::assertArrayHasKey('segmentsTableBodyCellID', $styles);
        static::assertArrayHasKey('footerStatementInfoFont', $styles);
        static::assertArrayHasKey('footerStatementInfoParagraph', $styles);
        static::assertArrayHasKey('footerPaginationFont', $styles);
        static::assertArrayHasKey('footerPaginationParagraph', $styles);
        static::assertArrayHasKey('footerCellWidth', $styles);
        static::assertArrayHasKey('footerCell', $styles);
    }
}
