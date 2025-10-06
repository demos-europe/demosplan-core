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
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsExporter;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OriginalStatementExporter extends SegmentsExporter
{
    private const STATEMENT_ID_COLUMN_WIDTH = 1950;
    private const STATEMENT_TEXT_COLUMN_WIDTH = 13500;

    public function __construct(
        CurrentUserInterface $currentUser,
        HtmlHelper $htmlHelper,
        ImageLinkConverter $imageLinkConverter,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator,
        DocumentWriterSelector $writerSelector,
    ) {
        parent::__construct(
            $currentUser,
            $htmlHelper,
            $imageLinkConverter,
            $slugify,
            $styleInitializer,
            $translator,
            $writerSelector,
            self::STATEMENT_ID_COLUMN_WIDTH,
            self::STATEMENT_TEXT_COLUMN_WIDTH);
    }

    public function exportOriginalStatements(array $statements, Procedure $procedure): WriterInterface
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if ([] === $statements) {
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
        $table = $this->addStatementsTableHeader($section, $tableHeaders);
        $this->addStatementTableBody($table, $statement);
    }

    private function addStatementsTableHeader(Section $section, array $tableHeaders): Table
    {
        $headerConfigs = [
            [
                'text'  => $tableHeaders['col1'] ?? $this->translator->trans('statement.external_id'),
                'style' => $this->styles['segmentsTableHeaderCellID'],
            ],
            [
                'text'  => $tableHeaders['col2'] ?? $this->translator->trans('statement'),
                'style' => $this->styles['segmentsTableHeaderCell'],
            ],
        ];

        return $this->createTableWithHeader($section, $headerConfigs);
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
