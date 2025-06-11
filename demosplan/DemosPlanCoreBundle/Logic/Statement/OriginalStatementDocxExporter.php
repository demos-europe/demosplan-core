<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use Cocur\Slugify\Slugify;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OriginalStatementDocxExporter extends SegmentsExporter
{
    public function __construct(
        CurrentUserInterface $currentUser,
        HtmlHelper $htmlHelper,
        ImageManager $imageManager,
        ImageLinkConverter $imageLinkConverter,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator,
    ) {
        parent::__construct($currentUser, $htmlHelper, $imageManager, $imageLinkConverter, $slugify, $styleInitializer, $translator);
    }

    public function exportOriginalStatements(array $statements, Procedure $procedure): WriterInterface
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements(
            $phpWord,
            $procedure,
            $statements,
            [],
            false,
            false,
            false
        );
    }

    protected function addContent(Section $section, Statement $statement, array $tableHeaders, bool $isObscure = false): void
    {
        $this->addStatementTable($section, $statement, $tableHeaders, $isObscure);
    }

    private function addStatementTable(Section $section, Statement $statement, array $tableHeaders, bool $isObscure): void
    {
        $table = $this->addStatementsTableHeader($section, $tableHeaders);
        $this->addStatementTableBody($table, $statement);
    }

    private function addStatementsTableHeader(Section $section, array $tableHeaders): Table
    {
        $table = $section->addTable($this->styles['segmentsTable']);
        $headerRow = $table->addRow(
            $this->styles['segmentsTableHeaderRowHeight'],
            $this->styles['segmentsTableHeaderRow']
        );
        $this->addSegmentCell(
            $headerRow,
            htmlspecialchars(
                $tableHeaders['col1'] ?? $this->translator->trans('statements.export.statement.id'),
                ENT_NOQUOTES,
                'UTF-8'
            ),
            $this->styles['segmentsTableHeaderCellID']
        );
        $this->addSegmentCell(
            $headerRow,
            htmlspecialchars(
                $tableHeaders['col2'] ?? $this->translator->trans('statements.export.statement.label'),
                ENT_NOQUOTES,
                'UTF-8'
            ),
            $this->styles['segmentsTableHeaderCell']
        );

        return $table;
    }

    private function addStatementTableBody(Table $table, Statement $statement): void
    {
        $textRow = $table->addRow();
        $statementText = $statement->getText();
        $newStatementText = str_replace('<br>', '<br/>', $statementText);
        $this->addSegmentHtmlCell(
            $textRow,
            $statement->getExternId(),
            $this->styles['segmentsTableBodyCellID']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $newStatementText,
            $this->styles['segmentsTableBodyCell']
        );
    }
}
