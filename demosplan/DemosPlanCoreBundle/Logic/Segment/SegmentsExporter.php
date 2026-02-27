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
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
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
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Html;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

abstract class SegmentsExporter
{
    /**
     * @var array<string, mixed>
     */
    protected array $styles;

    public function __construct(
        protected readonly CurrentUserInterface $currentUser,
        private readonly HtmlHelper $htmlHelper,
        protected readonly ImageLinkConverter $imageLinkConverter,
        protected Slugify $slugify,
        StyleInitializer $styleInitializer,
        protected TranslatorInterface $translator,
        private readonly DocumentWriterSelector $writerSelector,
        int $smallColumnWidth = 1550,
        int $wideColumnWidth = 6950,
    ) {
        $this->styles = $styleInitializer->initialize($smallColumnWidth, $wideColumnWidth);
    }

    /**
     * @throws Exception
     */
    public function export(
        Procedure $procedure,
        Statement $statement,
        array $tableHeaders,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        bool $isObscure,
    ): WriterInterface {
        $isCensored = $this->needsToBeCensored(
            $statement,
            $censorCitizenData,
            $censorInstitutionData,
        );

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->addFontStyle('global', $this->styles['globalFont']);
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);
        $this->addStatementInfo($section, $statement, $isCensored);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addContent($section, $statement, $tableHeaders, $isObscure);
        $this->addFooter($section, $statement);

        return IOFactory::createWriter($phpWord, $this->writerSelector->getWriterType());
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

    protected function addHeader(Section $section, Procedure $procedure, ?string $headerType = null, array $exportFilteredByTags = [], array $exportTagTitles = []): void
    {
        $header = null === $headerType ? $section->addHeader() : $section->addHeader($headerType);
        $header->addText(
            $procedure->getName(),
            $this->styles['documentTitleFont'],
            $this->styles['documentTitleParagraph']
        );

        $this->addPreambleIfFirstHeader($header, $headerType, $exportFilteredByTags, $exportTagTitles);

        $currentDate = new DateTime();
        $translationKey = $exportFilteredByTags && $exportTagTitles ? 'segments.export.statement.export.date.filtered' : 'segments.export.statement.export.date';
        $translationParameter = ['date' => $currentDate->format('d.m.Y')];
        if ($this->currentUser->hasPermission('feature_adjust_export_file_name')) {
            $translationKey = $exportFilteredByTags && $exportTagTitles ? 'segments.export.statement.export.filtered' : 'segments.export.statement.export';
            $translationParameter = ['procedureName'  => $procedure->getName()];
        }
        $header->addText(
            $this->translator->trans($translationKey, $translationParameter),
            $this->styles['currentDateFont'],
            $this->styles['currentDateParagraph']
        );
    }

    protected function addPreambleIfFirstHeader(Header $header, ?string $headerType, array $exportFilteredByTags = [], array $exportTagTitles = []): void
    {
        if (Footer::FIRST === $headerType
            && ([] !== $exportFilteredByTags || [] !== $exportTagTitles)
            && $this->currentUser->hasPermission('feature_adjust_export_file_name')) {
            $filteredExportPreamble = $this->translator->trans('docx.export.filtered');
            foreach ($exportTagTitles as $tagTopicContainer)
            {
                $appendToVariable = "<br>Schlagwort: ".$tagTopicContainer[0].' [Thema: '.$tagTopicContainer[1].']';
                $filteredExportPreamble .= $appendToVariable;
            }
            Html::addHtml($header, $this->htmlHelper->getHtmlValidText($filteredExportPreamble), false, false);
        } else {
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

    abstract protected function addContent(Section $section, Statement $statement, array $tableHeaders, bool $isObscure = false): void;

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

    protected function addNoSegmentsMessage(Section $section): void
    {
        $noEntriesMessage = $this->translator->trans('statement.has.no.segments');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);
    }

    protected function sortSegmentsByOrderInProcedure(array $segments): array
    {
        uasort($segments, [$this, 'compareOrderInProcedure']);

        return $segments;
    }

    protected function compareOrderInProcedure(Segment $segmentA, Segment $segmentB): int
    {
        return $segmentA->getOrderInProcedure() - $segmentB->getOrderInProcedure();
    }

    protected function addSegmentHtmlCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
    {
        // remove STX (start of text) EOT (end of text) special chars
        $text = str_replace([chr(2), chr(3)], '', $text);
        $cell = $row->addCell(
            $cellExportStyle->getWidth(),
            $cellExportStyle->getCellStyle()
        );
        Html::addHtml($cell, $this->htmlHelper->getHtmlValidText($text), false, false);
    }

    protected function addSegmentCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
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

    public function needsToBeCensored(Statement $statement, bool $censorCitizenData, bool $censorInstitutionData): bool
    {
        return
            ($statement->isSubmittedByOrganisation() && $censorInstitutionData)
            || ($statement->isSubmittedByCitizen() && $censorCitizenData);
    }

    /**
     * @throws Exception
     */
    protected function exportEmptyStatements(PhpWord $phpWord, Procedure $procedure, array $exportFilteredByTags = [], array $exportTagTitles = []): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST, $exportFilteredByTags, $exportTagTitles);
        $this->addHeader($section, $procedure, null, $exportFilteredByTags, $exportTagTitles);

        return $this->addNoStatementsMessage($phpWord, $section);
    }

    /**
     * @throws Exception
     */
    private function addNoStatementsMessage(PhpWord $phpWord, Section $section): WriterInterface
    {
        $noEntriesMessage = $this->translator->trans('statements.filtered.none');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);

        return IOFactory::createWriter($phpWord, $this->writerSelector->getWriterType());
    }

    public function exportStatement(
        Section $section,
        Statement $statement,
        array $tableHeaders,
        $censored = false,
        $obscure = false,
    ): void {
        $this->addStatementInfo($section, $statement, $censored);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addContent($section, $statement, $tableHeaders, $obscure);
        $this->addFooter($section, $statement, $censored);
    }

    /**
     * @param array<int, Statement> $statements
     *
     * @throws Exception
     */
    public function exportStatements(
        PhpWord $phpWord,
        Procedure $procedure,
        array $statements,
        array $tableHeaders,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        bool $obscure,
        array $exportFilteredByTags = [],
        array $exportTagTitles = []
    ): WriterInterface {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST, $exportFilteredByTags, $exportTagTitles);
        $this->addHeader($section, $procedure, null, $exportFilteredByTags, $exportTagTitles);

        foreach ($statements as $index => $statement) {
            $censored = $this->needsToBeCensored(
                $statement,
                $censorCitizenData,
                $censorInstitutionData,
            );

            $this->exportStatement($section, $statement, $tableHeaders, $censored, $obscure);
            $section = $this->getNewSectionIfNeeded($phpWord, $section, $index, $statements);
        }

        return IOFactory::createWriter($phpWord, $this->writerSelector->getWriterType());
    }

    /**
     * @param array<int, Statement> $statements
     */
    protected function getNewSectionIfNeeded(PhpWord $phpWord, Section $section, int $i, array $statements): Section
    {
        if ($this->isNotLastStatement($statements, $i)) {
            $section = $phpWord->addSection($this->styles['globalSection']);
        }

        return $section;
    }

    /**
     * @param array<int, Statement> $statements
     */
    private function isNotLastStatement(array $statements, int $i): bool
    {
        return $i !== count($statements) - 1;
    }

    protected function createTableWithHeader(Section $section, array $headerConfigs): Table
    {
        $table = $section->addTable($this->styles['segmentsTable']);
        $headerRow = $table->addRow(
            $this->styles['segmentsTableHeaderRowHeight'],
            $this->styles['segmentsTableHeaderRow']
        );

        foreach ($headerConfigs as $config) {
            $this->addSegmentCell(
                $headerRow,
                htmlspecialchars(
                    (string) $config['text'],
                    ENT_NOQUOTES,
                    'UTF-8'
                ),
                $config['style']
            );
        }

        return $table;
    }
}
