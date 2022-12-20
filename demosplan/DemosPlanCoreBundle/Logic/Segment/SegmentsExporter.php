<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use Cocur\Slugify\Slugify;
use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use demosplan\DemosPlanCoreBundle\ValueObject\ExportOrgaInfoHeader;
use demosplan\DemosPlanUserBundle\Logic\CurrentUserInterface;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsExporter
{
    /**
     * @var array<string, mixed>
     */
    protected $styles;

    /**
     * @var TranslatorInterface
     */
    protected $translator;
    /**
     * @var CurrentUserInterface
     */
    private $currentUser;

    /**
     * @var Slugify
     */
    protected $slugify;

    public function __construct(
        CurrentUserInterface $currentUser,
        Slugify $slugify,
        TranslatorInterface $translator)
    {
        $this->translator = $translator;
        $this->initializeStyles();
        $this->currentUser = $currentUser;
        $this->slugify = $slugify;
    }

    /**
     * @throws Exception
     */
    public function export(Procedure $procedure, Statement $statement): WriterInterface
    {
        $phpWord = new PhpWord();
        Settings::setOutputEscapingEnabled(true);
        $phpWord->addFontStyle('global', $this->styles['globalFont']);
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure);
        $this->addStatementInfo($section, $statement);
        $this->addSegments($section, $statement);
        $this->addFooter($section, $statement);

        return IOFactory::createWriter($phpWord);
    }

    protected function addHeader(Section $section, Procedure $procedure): void
    {
        $header = $section->addHeader();
        $header->addText(
            $procedure->getName(),
            $this->styles['documentTitleFont'],
            $this->styles['documentTitleParagraph']
        );

        $currentDate = new DateTime();
        $header->addText(
            $this->translator->trans('segments.export.statement.export.date', ['date' => $currentDate->format('d.m.Y')]),
            $this->styles['currentDateFont'],
            $this->styles['currentDateParagraph']
        );
    }

    protected function addStatementInfo(Section $section, Statement $statement): void
    {
        $table = $section->addTable($this->styles['statementInfoTable']);

        $orgaInfoHeader = new ExportOrgaInfoHeader($statement, $this->currentUser, $this->translator);

        $row1 = $table->addRow();
        $this->addSegmentCell($row1, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row1, '', $this->styles['statementInfoEmptyCell']);
        $creationDate = $statement->getCreated()->format('d.m.Y');
        $creationText = $this->translator->trans('segments.export.statement.creation.date', ['date' => $creationDate]);
        $this->addSegmentCell($row1, $creationText, $this->styles['statementInfoTextCell']);

        $row2 = $table->addRow();
        $this->addSegmentCell($row2, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row2, '', $this->styles['statementInfoEmptyCell']);
        $externIdText = $this->translator->trans('segments.export.statement.extern.id', ['externId' => $statement->getExternId()]);
        $this->addSegmentCell($row2, $externIdText, $this->styles['statementInfoTextCell']);

        $row3 = $table->addRow();
        $this->addSegmentCell($row3, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row3, '', $this->styles['statementInfoEmptyCell']);
        $internIdText = $this->translator->trans('segments.export.statement.intern.id', ['internId' => $statement->getInternId()]);
        $this->addSegmentCell($row3, $internIdText, $this->styles['statementInfoTextCell']);

        $row4 = $table->addRow();
        $this->addSegmentCell($row4, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row4, '', $this->styles['statementInfoEmptyCell']);
        $this->addSegmentCell($row4, '', $this->styles['statementInfoTextCell']);

        $row5 = $table->addRow();
        $this->addSegmentCell($row5, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row5, '', $this->styles['statementInfoEmptyCell']);

        $section->addTextBreak(2);
    }

    protected function addSegments(Section $section, Statement $statement): void
    {
        if ($statement->getSegmentsOfStatement()->isEmpty()) {
            $this->addNoSegmentsMessage($section);
        } else {
            $this->addSegmentsTable($section, $statement);
        }
    }

    protected function addFooter(Section $section, Statement $statement): void
    {
        $footer = $section->addFooter();
        $table = $footer->addTable();
        $row = $table->addRow();

        $cell1 = $row->addCell($this->styles['footerCellWidth'], $this->styles['footerCell']);
        $footerLeftString = $this->getFooterLeftString($statement);
        $cell1->addText($footerLeftString, $this->styles['footerStatementInfoFont'], $this->styles['footerStatementInfoParagraph']);

        $cell2 = $row->addCell($this->styles['footerCellWidth'], $this->styles['footerCell']);
        $cell2->addPreserveText(
            $this->translator->trans('segments.export.pagination'),
            $this->styles['footerPaginationFont'],
            $this->styles['footerPaginationParagraph']
        );
    }

    private function addNoSegmentsMessage(Section $section): void
    {
        $noEntriesMessage = $this->translator->trans('statement.has.no.segments');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);
    }

    private function addSegmentsTable(Section $section, Statement $statement): void
    {
        $table = $this->addSegmentsTableHeader($section);
        $sortedSegments = $statement->getSegmentsOfStatement()->toArray();
        uasort($sortedSegments, static function (Segment $segmentA, Segment $segmentB) {
            return $segmentA->getOrderInProcedure() - $segmentB->getOrderInProcedure();
        });

        foreach ($sortedSegments as $segment) {
            $this->addSegmentTableBody($table, $segment);
        }
    }

    private function addSegmentsTableHeader(Section $section): Table
    {
        $table = $section->addTable($this->styles['segmentsTable']);
        $headerRow = $table->addRow(
            $this->styles['segmentsTableHeaderRowHeight'],
            $this->styles['segmentsTableHeaderRow']
        );
        $this->addSegmentCell(
            $headerRow,
            $this->translator->trans('segments.export.segment.id'),
            $this->styles['segmentsTableHeaderCellID']
        );
        $this->addSegmentCell(
            $headerRow,
            $this->translator->trans('segments.export.statement.label'),
            $this->styles['segmentsTableHeaderCell']
        );
        $this->addSegmentCell(
            $headerRow,
            $this->translator->trans('segment.recommendation'),
            $this->styles['segmentsTableHeaderCell']
        );

        return $table;
    }

    private function addSegmentTableBody(Table $table, Segment $segment): void
    {
        $textRow = $table->addRow();
        $this->addSegmentHtmlCell(
            $textRow,
            $segment->getExternId(),
            $this->styles['segmentsTableBodyCellID']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $segment->getText(),
            $this->styles['segmentsTableBodyCell']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $segment->getRecommendation(),
            $this->styles['segmentsTableBodyCell']
        );
    }

    private function addSegmentHtmlCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
    {
        $cell = $row->addCell(
            $cellExportStyle->getWidth(),
            $cellExportStyle->getCellStyle()
        );
        Html::addHtml($cell, $this->getHtmlValidText($text, $cellExportStyle), false, false);
    }

    private function getHtmlValidText(string $text): string
    {
        return str_replace('<br>', '<br/>', $text);
    }

    private function addSegmentCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
    {
        $cell = $row->addCell(
            $cellExportStyle->getWidth(),
            $cellExportStyle->getCellStyle()
        );
        $cell->addText($text, $cellExportStyle->getFontStyle(), $cellExportStyle->getParagraphStyle());
    }

    private function getFooterLeftString(Statement $statement): string
    {
        $info = [];
        if ($this->validInfoString($statement->getUserName())) {
            $info[] = $statement->getUserName();
        }
        if ($this->validInfoString($statement->getExternId())) {
            $info[] = $statement->getExternId();
        }
        if ($this->validInfoString($statement->getInternId())) {
            $info[] = $statement->getInternId();
        }

        return implode(', ', $info);
    }

    private function validInfoString(?string $text): bool
    {
        return null !== $text && '' !== trim($text);
    }

    private function initializeStyles(): void
    {
        // Global
        $this->styles['globalSection'] = [
            'orientation'  => 'landscape',
            'marginLeft'   => Converter::cmToTwip(1.27),
            'marginRight'  => Converter::cmToTwip(1.27),
        ];
        $this->styles['globalFont'] = ['name' => 'Arial'];

        // Header
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

        // Segments
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

        // Footer
        $this->styles['footerStatementInfoFont'] = [];
        $this->styles['footerStatementInfoParagraph'] = ['alignment' => Jc::START];

        $this->styles['footerPaginationFont'] = [];
        $this->styles['footerPaginationParagraph'] = ['alignment' => Jc::END];
        $this->styles['footerCellWidth'] = 7750;
        $this->styles['footerCell'] = ['borderColor' => 'ffffff', 'borderSize' => 0];
    }
}
