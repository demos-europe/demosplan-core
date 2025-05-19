<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Export;

use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\SimpleType\Jc;

class StyleInitializer
{
    /**
     * @var array<string, mixed>
     */
    private array $styles;

    /**
     * @return array<string, mixed>
     */
    public function initialize(): array
    {
        $this->initializeGlobalStyles();
        $this->initializeHeaderStyles();
        $this->initializeSegmentStyles();
        $this->initializeFooterStyles();

        return $this->styles;
    }

    private function initializeGlobalStyles(): void
    {
        $this->styles['globalSection'] = [
            'orientation'  => 'landscape',
            'marginLeft'   => Converter::cmToTwip(1.27),
            'marginRight'  => Converter::cmToTwip(1.27),
        ];
        $this->styles['globalFont'] = ['name' => 'Arial'];
    }

    private function initializeHeaderStyles(): void
    {
        $this->styles['documentTitleFont'] = ['size' => 12, 'bold' => true];
        $this->styles['documentTitleParagraph'] = ['alignment' => Jc::CENTER, 'spaceAfter' => Converter::cmToTwip(0.5)];

        $this->styles['currentDateFont'] = [];
        $this->styles['currentDateParagraph'] = ['alignment' => Jc::END, 'spaceAfter' => Converter::cmToTwip(0.5)];

        $this->styles['statementInfoTable'] = [
            'borderColor' => 'ffffff',
            'borderSize'  => 0,
            'cellSpacing' => Converter::cmToTwip(0),
        ];
        $this->styles['statementInfoTextCell'] = new CellExportStyle(4500);
        $this->styles['statementInfoEmptyCell'] = new CellExportStyle(6500);
    }

    private function initializeSegmentStyles(): void
    {
        $this->styles['noInfoMessageFont'] = ['size' => 12];
        $wideColumnWidth = 6950;
        $smallColumnWidth = 1550;
        $headerCellStyle = ['borderSize'  => 5, 'borderColor' => '000000', 'bold' => true];
        $headerPargraphStyle = ['spaceBefore' => Converter::cmToTwip(0.15), 'spaceAfter' => Converter::cmToTwip(0.15)];
        $headerFontStyle = ['bold' => true];
        $bodyCellStyle = ['borderSize'  => 5, 'borderColor' => '000000'];
        $bodyParagraphStyle = ['lineHeight'  => 1.2, 'spaceBefore' => Converter::cmToTwip(0.15), 'spaceAfter' => Converter::cmToTwip(0.15)];

        $this->styles['segmentsTable'] = [
            'cellMargin' => Converter::cmToTwip(0.15),
        ];
        $this->styles['segmentsTableHeaderRow'] = ['tblHeader' => true];
        $this->styles['segmentsTableHeaderRowHeight'] = Converter::cmToTwip(0.5);
        $this->styles['segmentsTableHeaderCell'] = new CellExportStyle(
            $wideColumnWidth,
            $headerCellStyle,
            $headerPargraphStyle,
            $headerFontStyle
        );
        $this->styles['segmentsTableBodyCell'] = new CellExportStyle(
            $wideColumnWidth,
            $bodyCellStyle,
            $bodyParagraphStyle
        );

        $this->styles['segmentsTableHeaderCellID'] = new CellExportStyle(
            $smallColumnWidth,
            $headerCellStyle,
            $headerPargraphStyle,
            $headerFontStyle
        );
        $this->styles['segmentsTableBodyCellID'] = new CellExportStyle(
            $smallColumnWidth,
            $bodyCellStyle,
            $bodyParagraphStyle
        );
    }

    private function initializeFooterStyles(): void
    {
        $this->styles['footerStatementInfoFont'] = [];
        $this->styles['footerStatementInfoParagraph'] = ['alignment' => Jc::START];

        $this->styles['footerPaginationFont'] = [];
        $this->styles['footerPaginationParagraph'] = ['alignment' => Jc::END];
        $this->styles['footerCellWidth'] = 7750;
        $this->styles['footerCell'] = ['borderColor' => 'ffffff', 'borderSize' => 0];
    }
}
