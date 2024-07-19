<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\HeaderFooterManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use demosplan\DemosPlanCoreBundle\ValueObject\ExportOrgaInfoHeader;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsExporter
{
    private const STANDARD_DPI = 72;
    private const STANDARD_PT_TEXT = 10;
    private const MAX_WIDTH_INCH = 10.69;
    private const MAX_HEIGHT_INCH = 5.42;
    /**
     * @var array<string, mixed>
     */
    protected array $styles;

    protected HeaderFooterManager $headerFooterManager;

    protected TranslatorInterface $translator;

    protected Slugify $slugify;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly HtmlHelper $htmlHelper,
        protected readonly ImageLinkConverter $imageLinkConverter,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
        $this->styles = $styleInitializer->initialize();
        $this->slugify = $slugify;
        $this->headerFooterManager = new HeaderFooterManager($htmlHelper, $translator, $this->styles);
    }

    /**
     * @throws Exception
     */
    public function export(Procedure $procedure, Statement $statement, array $tableHeaders): WriterInterface
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->addFontStyle('global', $this->styles['globalFont']);
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->headerFooterManager->addHeader($section, $procedure, Footer::FIRST);
        $this->headerFooterManager->addHeader($section, $procedure);
        $this->addStatementInfo($section, $statement);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement, $tableHeaders);
        $this->headerFooterManager->addFooter($section, $statement);

        return IOFactory::createWriter($phpWord);
    }

    protected function addSimilarStatementSubmitters(Section $section, Statement $statement): void
    {
        $similarStatementSubmitters = $this->getSimilarStatementSubmitters($statement);
        if ('' !== $similarStatementSubmitters) {
            $similarStatementSubmittersText = $this->translator->trans('segments.export.statement.similar.submitters', ['similarSubmitters' => $similarStatementSubmitters]);
            $section->addText(
                $similarStatementSubmittersText,
                $this->styles['globalFont'],
                $this->styles['globalSection']
            );

            $section->addTextBreak(2);
        }
    }

    private function getSimilarStatementSubmitters(Statement $statement): string
    {
        $submitterStrings = [];
        foreach ($statement->getSimilarStatementSubmitters() as $submitter) {
            $values = [
                $submitter->getEmailAddress(),
                $submitter->getStreetNameWithStreetNumber(),
                $submitter->getPostalCodeWithCity(),
            ];
            $values = array_filter($values, static fn (?string $value): bool =>null !== $value);
            $values = implode(', ', $values);
            $values = trim($values);
            if ('' !== $values) {
                $values = " ($values)";
            }

            $submitterStrings[] = "{$submitter->getFullName()}$values";
        }

        return implode(', ', $submitterStrings);
    }

    protected function addStatementInfo(Section $section, Statement $statement): void
    {
        $table = $section->addTable($this->styles['statementInfoTable']);
        $orgaInfoHeader = new ExportOrgaInfoHeader($statement, $this->currentUser, $this->translator);

        if ('' !== $statement->getAuthoredDateString()) {
            $authoredDateRow = $table->addRow();
            $this->addSegmentCell($authoredDateRow, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
            $this->addSegmentCell($authoredDateRow, '', $this->styles['statementInfoEmptyCell']);
            $authoredAt = $this->translator->trans('statement.date.authored').': '.$statement->getAuthoredDateString();
            $this->addSegmentCell($authoredDateRow, $authoredAt, $this->styles['statementInfoTextCell']);
        }

        if ('' !== $statement->getSubmitDateString()) {
            $submitDateRow = $table->addRow();
            $this->addSegmentCell($submitDateRow, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
            $this->addSegmentCell($submitDateRow, '', $this->styles['statementInfoEmptyCell']);
            $submittedAt = $this->translator->trans('statement.date.submitted').': '.$statement->getSubmitDateString();
            $this->addSegmentCell($submitDateRow, $submittedAt, $this->styles['statementInfoTextCell']);
        }

        $textRow = $table->addRow();
        $this->addSegmentCell($textRow, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($textRow, '', $this->styles['statementInfoEmptyCell']);
        $externIdText = $this->translator->trans('segments.export.statement.extern.id', ['externId' => $statement->getExternId()]);
        $this->addSegmentCell($textRow, $externIdText, $this->styles['statementInfoTextCell']);

        if (null !== $statement->getInternId() && '' !== $statement->getInternId()) {
            $internIdRow = $table->addRow();
            $this->addSegmentCell($internIdRow, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
            $this->addSegmentCell($internIdRow, '', $this->styles['statementInfoEmptyCell']);
            $internIdText = $this->translator->trans('segments.export.statement.intern.id', ['internId' => $statement->getInternId()]);
            $this->addSegmentCell($internIdRow, $internIdText, $this->styles['statementInfoTextCell']);
        }

        // formation only
        $row5 = $table->addRow();
        $this->addSegmentCell($row5, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row5, '', $this->styles['statementInfoEmptyCell']);
        $this->addSegmentCell($row5, '', $this->styles['statementInfoTextCell']);

        $row6 = $table->addRow();
        $this->addSegmentCell($row6, $orgaInfoHeader->getNextHeader(), $this->styles['statementInfoTextCell']);
        $this->addSegmentCell($row6, '', $this->styles['statementInfoEmptyCell']);

        $section->addTextBreak(2);
    }

    protected function addSegments(Section $section, Statement $statement, array $tableHeaders): void
    {
        if ($statement->getSegmentsOfStatement()->isEmpty()) {
            $this->addNoSegmentsMessage($section);
        } else {
            $this->addSegmentsTable($section, $statement, $tableHeaders);
        }
    }

    private function addNoSegmentsMessage(Section $section): void
    {
        $noEntriesMessage = $this->translator->trans('statement.has.no.segments');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);
    }

    private function addSegmentsTable(Section $section, Statement $statement, array $tableHeaders): void
    {
        $table = $this->addSegmentsTableHeader($section, $tableHeaders);
        $sortedSegments = $this->sortSegmentsByOrderInProcedure($statement->getSegmentsOfStatement()->toArray());

        foreach ($sortedSegments as $segment) {
            $this->addSegmentTableBody($table, $segment, $statement->getExternId());
        }
        $this->addImages($section);
    }

    private function addImages(Section $section): void
    {
        // Add images after all segments of one statement.
        $images = $this->imageLinkConverter->getImages();
        if ([] === $images) {
            return;
        }
        $imageSpaceCurrentlyUsed = 0;
        $section->addPageBreak();
        foreach ($images as $imageReference => $imagePath) {
            [$width, $height] = getimagesize($imagePath);
            [$maxWidth, $maxHeight] = $this->getMaxWidthAndHeight();

            if ($width > $maxWidth) {
                $factor = $maxWidth / $width;
                $width = $maxWidth;
                $height *= $factor;
            }
            if ($height > $maxHeight) {
                $factor = $maxHeight / $height;
                $height = $maxHeight;
                $width *= $factor;
            }
            if ($height > $maxHeight - $imageSpaceCurrentlyUsed) {
                $section->addPageBreak();
            }
            $imageSpaceCurrentlyUsed += $height + self::STANDARD_PT_TEXT * 2;

            $imageStyle = [
                'width'  => $width,
                'height' => $height,
                'align'  => Jc::START,
            ];

            $section->addText($imageReference);
            $section->addBookmark($imageReference);
            $section->addImage($imagePath, $imageStyle);
        }

        // remove already printed images
        $this->imageLinkConverter->resetImages();
    }

    protected function sortSegmentsByOrderInProcedure(array $segments): array
    {
        uasort($segments, [$this, 'compareOrderInProcedure']);

        return $segments;
    }

    private function compareOrderInProcedure(Segment $segmentA, Segment $segmentB): int
    {
        return $segmentA->getOrderInProcedure() - $segmentB->getOrderInProcedure();
    }

    private function getMaxWidthAndHeight(): array
    {
        $maxWidth = self::MAX_WIDTH_INCH * self::STANDARD_DPI;
        $maxHeight = self::MAX_HEIGHT_INCH * self::STANDARD_DPI - self::STANDARD_PT_TEXT;

        return [$maxWidth, $maxHeight];
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
        // Replace image tags in segment recommendation text with a linked reference to the image.
        $recommendationText = $this->imageLinkConverter->convert($segment->getRecommendation(), $statementExternId);
        $this->addSegmentHtmlCell(
            $textRow,
            $recommendationText,
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
