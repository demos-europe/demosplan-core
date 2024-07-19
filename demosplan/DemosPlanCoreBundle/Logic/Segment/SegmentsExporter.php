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
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentTableManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StatementDetailsManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\SegmentSorter;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
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
    protected SegmentTableManager $segmentTableManager;
    protected Slugify $slugify;
    protected StatementDetailsManager $statementInfoManager;

    public function __construct(
        CurrentUserInterface $currentUser,
        private readonly HtmlHelper $htmlHelper,
        protected readonly ImageLinkConverter $imageLinkConverter,
        protected readonly ImageManager $imageManager,
        protected readonly SegmentSorter $segmentSorter,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator
    ) {
        $this->translator = $translator;
        $this->styles = $styleInitializer->initialize();
        $this->slugify = $slugify;
        $this->headerFooterManager = new HeaderFooterManager($htmlHelper, $translator, $this->styles);
        $this->statementInfoManager = new StatementDetailsManager($currentUser, $translator, $this->styles);
        $this->segmentTableManager =
            new SegmentTableManager($translator, $this->imageLinkConverter, $this->htmlHelper, $this->styles);
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
        $table = $this->segmentTableManager->addSegmentsTableHeader($section, $tableHeaders);
        $sortedSegments = $this->segmentSorter->sortSegmentsByOrderInProcedure($statement->getSegmentsOfStatement()->toArray());

        foreach ($sortedSegments as $segment) {
            $this->segmentTableManager->addSegmentTableBody($table, $segment, $statement->getExternId());
        }
        $this->imageManager->addImages($section);
    }
}
