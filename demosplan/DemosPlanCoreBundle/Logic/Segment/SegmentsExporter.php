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
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StatementDetailsManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\ValueObject\CellExportStyle;
use PhpOffice\PhpWord\Element\Footer;
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

    protected HeaderFooterManager $headerFooterManager;

    protected TranslatorInterface $translator;

    protected Slugify $slugify;
    protected StatementDetailsManager $statementInfoManager;

    public function __construct(
        CurrentUserInterface $currentUser,
        private readonly HtmlHelper $htmlHelper,
        protected readonly ImageLinkConverter $imageLinkConverter,
        protected readonly ImageManager $imageManager,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
        $this->styles = $styleInitializer->initialize();
        $this->slugify = $slugify;
        $this->headerFooterManager = new HeaderFooterManager($htmlHelper, $translator, $this->styles);
        $this->statementInfoManager = new StatementDetailsManager($currentUser, $translator, $this->styles);
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

    /**
     * @deprecated Use {@link StatementDetailsManager::addSimilarStatementSubmitters()} instead.
     */
    protected function addSimilarStatementSubmitters(Section $section, Statement $statement): void
    {
        $this->statementInfoManager->addSimilarStatementSubmitters($section, $statement);
    }

    /**
     * @deprecated Use {@link StatementDetailsManager::addStatementInfo()} instead.
     */
    protected function addStatementInfo(Section $section, Statement $statement): void
    {
        $this->statementInfoManager->addStatementInfo($section, $statement);
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
