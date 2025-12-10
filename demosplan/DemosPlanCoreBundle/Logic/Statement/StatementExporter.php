<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Shared\Converter;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class StatementExporter
{
    protected array $styles;

    public function __construct(
        private readonly StyleInitializer $styleInitializer,
        private readonly TranslatorInterface $translator,
    ) {
        $this->styles = $styleInitializer->initialize();
    }

    /**
     * Export statements as DOCX table.
     *
     * @throws Exception
     */
    public function exportAll(
        Procedure $procedure,
        Statement ...$statements,
    ): WriterInterface {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements($phpWord, $procedure, $statements);
    }

    /**
     * @throws Exception
     */
    private function exportEmptyStatements(PhpWord $phpWord, Procedure $procedure): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        $section->addText(
            $this->translator->trans('statements.list.empty'),
            $this->styles['noInfoMessageFont']
        );

        return IOFactory::createWriter($phpWord);
    }

    /**
     * @param array<int, Statement> $statements
     *
     * @throws Exception
     */
    private function exportStatements(PhpWord $phpWord, Procedure $procedure, array $statements): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        $table = $this->createStatementsTable($section);

        foreach ($statements as $statement) {
            $this->addStatementRow($table, $statement);
        }

        return IOFactory::createWriter($phpWord);
    }

    private function createStatementsTable(Section $section): Table
    {
        $table = $section->addTable([
            'cellMargin' => Converter::cmToTwip(0.15),
        ]);

        // Add header row
        $headerRow = $table->addRow(
            Converter::cmToTwip(0.5),
            ['tblHeader' => true]
        );

        $headerCellStyle = ['borderSize' => 5, 'borderColor' => '000000'];
        $headerFontStyle = ['bold' => true];

        // Column 1: Statement ID (externId) - 1550
        $headerRow->addCell(1550, $headerCellStyle)->addText(
            $this->translator->trans('id'),
            $headerFontStyle
        );

        // Column 2: Status - 1800
        $headerRow->addCell(1800, $headerCellStyle)->addText(
            $this->translator->trans('status'),
            $headerFontStyle
        );

        // Column 3: Eing.Nr. (internId) - 1550
        $headerRow->addCell(1550, $headerCellStyle)->addText(
            $this->translator->trans('internId'),
            $headerFontStyle
        );

        // Column 4: Einreicher*in (submitter) - 2800
        $headerRow->addCell(2800, $headerCellStyle)->addText(
            $this->translator->trans('submitter'),
            $headerFontStyle
        );

        // Column 5: Text - 6700 (fills page width)
        $headerRow->addCell(6700, $headerCellStyle)->addText(
            $this->translator->trans('text'),
            $headerFontStyle
        );

        return $table;
    }

    private function addStatementRow(Table $table, Statement $statement): void
    {
        $row = $table->addRow();
        $bodyCellStyle = ['borderSize' => 5, 'borderColor' => '000000'];

        // Statement ID (externId) - 1550
        $row->addCell(1550, $bodyCellStyle)->addText($statement->getExternId() ?? '');

        // Status - 1800
        $row->addCell(1800, $bodyCellStyle)->addText($statement->getStatus() ?? '');

        // Eing.Nr. (internId) - 1550
        $row->addCell(1550, $bodyCellStyle)->addText($statement->getInternId() ?? '');

        // Einreicher*in (submitter + organization) - 2800
        $submitterText = $this->getSubmitterText($statement);
        $row->addCell(2800, $bodyCellStyle)->addText($submitterText);

        // Statement text (strip HTML tags) - 6700
        $row->addCell(6700, $bodyCellStyle)->addText(strip_tags($statement->getText() ?? ''));
    }

    private function getSubmitterText(Statement $statement): string
    {
        $submitterText = '';
        if ($statement->getMeta()) {
            $submitterText .= $statement->getMeta()->getAuthorName() ?? '';
            if ($statement->getMeta()->getOrgaName()) {
                $submitterText .= ' (' . $statement->getMeta()->getOrgaName() . ')';
            }
        }

        return $submitterText;
    }

    private function addHeader(Section $section, Procedure $procedure, ?string $headerType = null): void
    {
        $header = null === $headerType ? $section->addHeader() : $section->addHeader($headerType);

        // Procedure name as title
        $header->addText(
            $procedure->getName(),
            $this->styles['documentTitleFont'],
            $this->styles['documentTitleParagraph']
        );

        // Export date (using same translation key as segments export)
        $currentDate = new DateTime();
        $header->addText(
            $this->translator->trans('segments.export.statement.export.date', ['date' => $currentDate->format('d.m.Y')]),
            $this->styles['currentDateFont'],
            $this->styles['currentDateParagraph']
        );
    }
}
