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
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use demosplan\DemosPlanCoreBundle\ValueObject\ExportOrgaInfoHeader;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Header;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsExporter
{
    /**
     * @var array<string, mixed>
     */
    protected array $styles;

    protected TranslatorInterface $translator;

    protected Slugify $slugify;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        private readonly HtmlHelper $htmlHelper,
        private readonly ImageManager $imageManager,
        protected readonly ImageLinkConverter $imageLinkConverter,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator,
    ) {
        $this->translator = $translator;
        $this->styles = $styleInitializer->initialize();
        $this->slugify = $slugify;
    }

    /**
     * @throws Exception
     */
    public function export(Procedure $procedure, Statement $statement, array $tableHeaders, bool $isCensored, bool $isObscure): WriterInterface
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->addFontStyle('global', $this->styles['globalFont']);
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);
        $this->addStatementInfo($section, $statement, $isCensored);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement, $tableHeaders, $isObscure);
        $this->addFooter($section, $statement);

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

    protected function addHeader(Section $section, Procedure $procedure, ?string $headerType = null): void
    {
        $header = null === $headerType ? $section->addHeader() : $section->addHeader($headerType);
        $header->addText(
            $procedure->getName(),
            $this->styles['documentTitleFont'],
            $this->styles['documentTitleParagraph']
        );

        $this->addPreambleIfFirstHeader($header, $headerType);

        $currentDate = new DateTime();
        $header->addText(
            $this->translator->trans('segments.export.statement.export.date', ['date' => $currentDate->format('d.m.Y')]),
            $this->styles['currentDateFont'],
            $this->styles['currentDateParagraph']
        );
    }

    private function addPreambleIfFirstHeader(Header $header, ?string $headerType): void
    {
        if (Footer::FIRST === $headerType) {
            $preamble = $this->translator->trans('docx.export.preamble');
            Html::addHtml($header, $this->htmlHelper->getHtmlValidText($preamble), false, false);
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

    protected function addStatementInfo(Section $section, Statement $statement, bool $censored = false): void
    {
        $table = $section->addTable($this->styles['statementInfoTable']);

        if (!$censored) {
            $orgaInfoHeader = new ExportOrgaInfoHeader($statement, $this->currentUser, $this->translator);
        }

        if ('' !== $statement->getAuthoredDateString()) {
            $authoredDateRow = $table->addRow();

            $this->addSegmentCell(
                $authoredDateRow,
                $censored ? '' : $orgaInfoHeader->getNextHeader(),
                $censored ? $this->styles['statementInfoEmptyCell'] : $this->styles['statementInfoTextCell']
            );

            $this->addSegmentCell($authoredDateRow, '', $this->styles['statementInfoEmptyCell']);
            $authoredAt = $this->translator->trans('statement.date.authored').': '.$statement->getAuthoredDateString();
            $this->addSegmentCell($authoredDateRow, $authoredAt, $this->styles['statementInfoTextCell']);
        }

        if ('' !== $statement->getSubmitDateString()) {
            $submitDateRow = $table->addRow();

            $this->addSegmentCell(
                $submitDateRow,
                $censored ? '' : $orgaInfoHeader->getNextHeader(),
                $censored ? $this->styles['statementInfoEmptyCell'] : $this->styles['statementInfoTextCell']
            );

            $this->addSegmentCell($submitDateRow, '', $this->styles['statementInfoEmptyCell']);
            $submittedAt = $this->translator->trans('statement.date.submitted').': '.$statement->getSubmitDateString();
            $this->addSegmentCell($submitDateRow, $submittedAt, $this->styles['statementInfoTextCell']);
        }

        $textRow = $table->addRow();

        $this->addSegmentCell(
            $textRow,
            $censored ? '' : $orgaInfoHeader->getNextHeader(),
            $censored ? $this->styles['statementInfoEmptyCell'] : $this->styles['statementInfoTextCell']
        );

        $this->addSegmentCell($textRow, '', $this->styles['statementInfoEmptyCell']);
        $externIdText = $this->translator->trans('segments.export.statement.extern.id', ['externId' => $statement->getExternId()]);
        $this->addSegmentCell($textRow, $externIdText, $this->styles['statementInfoTextCell']);

        if (null !== $statement->getInternId() && '' !== $statement->getInternId()) {
            $internIdRow = $table->addRow();

            $this->addSegmentCell(
                $internIdRow,
                $censored ? '' : $orgaInfoHeader->getNextHeader(),
                $censored ? $this->styles['statementInfoEmptyCell'] : $this->styles['statementInfoTextCell']
            );

            $this->addSegmentCell($internIdRow, '', $this->styles['statementInfoEmptyCell']);
            $internIdText = $this->translator->trans('segments.export.statement.intern.id', ['internId' => $statement->getInternId()]);
            $this->addSegmentCell($internIdRow, $internIdText, $this->styles['statementInfoTextCell']);
        }

        // formation only
        $row5 = $table->addRow();

        $this->addSegmentCell(
            $row5,
            $censored ? '' : $orgaInfoHeader->getNextHeader(),
            $censored ? $this->styles['statementInfoEmptyCell'] : $this->styles['statementInfoTextCell']
        );

        $this->addSegmentCell($row5, '', $this->styles['statementInfoEmptyCell']);
        $this->addSegmentCell($row5, '', $this->styles['statementInfoTextCell']);

        $row6 = $table->addRow();

        $this->addSegmentCell(
            $row6,
            $censored ? '' : $orgaInfoHeader->getNextHeader(),
            $censored ? $this->styles['statementInfoEmptyCell'] : $this->styles['statementInfoTextCell']
        );

        $this->addSegmentCell($row6, '', $this->styles['statementInfoEmptyCell']);

        $section->addTextBreak(2);
    }

    protected function addSegments(Section $section, Statement $statement, array $tableHeaders, bool $isObscure = false): void
    {
        if ($statement->getSegmentsOfStatement()->isEmpty()) {
            $this->addNoSegmentsMessage($section);
        } else {
            $this->addSegmentsTable($section, $statement, $tableHeaders, $isObscure);
        }
    }

    protected function addFooter(Section $section, Statement $statement, bool $censored = false): void
    {
        $footer = $section->addFooter();
        $table = $footer->addTable();
        $row = $table->addRow();

        $cell1 = $row->addCell($this->styles['footerCellWidth'], $this->styles['footerCell']);
        $footerLeftString = $this->getFooterLeftString($statement, $censored);
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

    private function addSegmentsTable(Section $section, Statement $statement, array $tableHeaders, bool $isObscure): void
    {
        $table = $this->addSegmentsTableHeader($section, $tableHeaders);
        $sortedSegments = $this->sortSegmentsByOrderInProcedure($statement->getSegmentsOfStatement()->toArray());

        foreach ($sortedSegments as $segment) {
            $this->addSegmentTableBody($table, $segment, $statement->getExternId(), $isObscure);
        }
        $this->imageManager->addImages($section);
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

    private function addSegmentTableBody(Table $table, Segment $segment, string $statementExternId, bool $isObscure): void
    {
        $textRow = $table->addRow();
        // Replace image tags in segment text and in segment recommendation text with text references.
        $convertedSegment = $this->imageLinkConverter->convert($segment, $statementExternId, true, $isObscure);
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
        // remove STX (start of text) EOT (end of text) special chars
        $text = str_replace([chr(2), chr(3)], '', $text);
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

    private function getFooterLeftString(Statement $statement, bool $censored): string
    {
        $info = [];
        if ($this->validInfoString($statement->getUserName()) && !$censored) {
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
}
