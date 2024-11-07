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

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use demosplan\DemosPlanCoreBundle\ValueObject\ExportOrgaInfoHeader;
use PhpOffice\PhpWord\Element\Row;
use PhpOffice\PhpWord\Element\Section;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementDetailsManager
{
    /**
     * @var array<string, mixed>
     */
    private array $styles;

    public function __construct(
        private readonly CurrentUserInterface $currentUser,
        StyleInitializer $styleInitializer,
        private readonly TranslatorInterface $translator,
    ) {
        $this->styles = $styleInitializer->getStyles();
    }

    public function addStatementInfo(Section $section, Statement $statement): void
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

    public function addSimilarStatementSubmitters(Section $section, Statement $statement): void
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

    private function addSegmentCell(Row $row, string $text, CellExportStyle $cellExportStyle): void
    {
        $cell = $row->addCell(
            $cellExportStyle->getWidth(),
            $cellExportStyle->getCellStyle()
        );
        $cell->addText($text, $cellExportStyle->getFontStyle(), $cellExportStyle->getParagraphStyle());
    }
}
