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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\SegmentSorter;
use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Shared\Html;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentTableManager
{
    /**
     * @var array<string, mixed>
     */
    private array $styles;

    public function __construct(
        private readonly HtmlHelper $htmlHelper,
        private readonly ImageLinkConverter $imageLinkConverter,
        private readonly ImageManager $imageManager,
        private readonly SegmentSorter $segmentSorter,
        StyleInitializer $styleInitializer,
        private readonly TranslatorInterface $translator,
    ) {
        $this->styles = $styleInitializer->getStyles();
    }

    public function addSegmentsTable(Section $section, Statement $statement, array $tableHeaders): void
    {
        $table = $this->addSegmentsTableHeader($section, $tableHeaders);
        $sortedSegments = $this->segmentSorter->sortSegmentsByOrderInProcedure($statement->getSegmentsOfStatement()->toArray());

        foreach ($sortedSegments as $segment) {
            $this->addSegmentTableBody($table, $segment, $statement->getExternId());
        }
        $this->imageManager->addImages($section);
    }

    private function addSegmentsTableHeader(Section $section, array $tableHeaders): Table
    {
        $table = $section->addTable($this->styles['segmentsTable']);
        $headerRow = $table->addRow(
            $this->styles['segmentsTableHeaderRowHeight'],
            $this->styles['segmentsTableHeaderRow']
        );
        $this->addSegmentCell(
            $headerRow,
            htmlspecialchars(
                $tableHeaders['col1'] ?? $this->translator->trans('segments.export.segment.id'),
                ENT_NOQUOTES,
                'UTF-8'
            ),
            $this->styles['segmentsTableHeaderCellID']
        );
        $this->addSegmentCell(
            $headerRow,
            htmlspecialchars(
                $tableHeaders['col2'] ?? $this->translator->trans('segments.export.statement.label'),
                ENT_NOQUOTES,
                'UTF-8'
            ),
            $this->styles['segmentsTableHeaderCell']
        );
        $this->addSegmentCell(
            $headerRow,
            htmlspecialchars(
                $tableHeaders['col3'] ?? $this->translator->trans('segment.recommendation'),
                ENT_NOQUOTES,
                'UTF-8'
            ),
            $this->styles['segmentsTableHeaderCell']
        );

        return $table;
    }

    private function addSegmentTableBody(Table $table, Segment $segment, string $statementExternId): void
    {
        $textRow = $table->addRow();
        // Replace image tags in segment recommendation text with a linked reference to the image.
        $convertedSegment = $this->imageLinkConverter->convert($segment, $statementExternId);
        $this->addSegmentHtmlCell(
            $textRow,
            $segment->getExternId(),
            $this->styles['segmentsTableBodyCellID']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $convertedSegment->getText(),
            $this->styles['segmentsTableBodyCell']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $convertedSegment->getRecommendationText(),
            $this->styles['segmentsTableBodyCell']
        );
    }

    private function addSegmentHtmlCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
    {
        $cell = $row->addCell(
            $cellExportStyle->getWidth(),
            $cellExportStyle->getCellStyle()
        );
        Html::addHtml($cell, $this->htmlHelper->getHtmlValidText($text), false, false);
    }

    private function addSegmentCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
    {
        $cell = $row->addCell(
            $cellExportStyle->getWidth(),
            $cellExportStyle->getCellStyle()
        );
        $cell->addText($text, $cellExportStyle->getFontStyle(), $cellExportStyle->getParagraphStyle());
    }
}
