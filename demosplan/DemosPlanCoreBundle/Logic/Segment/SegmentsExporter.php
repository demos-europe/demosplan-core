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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\HeaderFooterManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentTableManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StatementDetailsManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\SegmentSorter;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsExporter
{
    /**
     * @var array<string, mixed>
     */
    protected array $styles;

    public function __construct(
        protected readonly HeaderFooterManager $headerFooterManager,
        protected readonly ImageLinkConverter $imageLinkConverter,
        protected readonly ImageManager $imageManager,
        protected readonly SegmentSorter $segmentSorter,
        private readonly SegmentTableManager $segmentTableManager,
        protected readonly Slugify $slugify,
        protected readonly StatementDetailsManager $statementDetailsManager,
        StyleInitializer $styleInitializer,
        protected readonly TranslatorInterface $translator
    ) {
        $this->styles = $styleInitializer->getStyles();
    }

    /**
     * @throws Exception
     */
    public function export(Procedure $procedure, Statement $statement, array $tableHeaders): WriterInterface
    {
        $phpWord = $this->createPhpWord();
        $this->buildSection($phpWord, $procedure, $statement, $tableHeaders);

        return IOFactory::createWriter($phpWord);
    }

    private function buildSection(
        PhpWord $phpWord,
        Procedure $procedure,
        Statement $statement,
        array $tableHeaders
    ): void {
        $section = $this->createNewSection($phpWord);
        $this->addSectionHeader($section, $procedure);
        $this->addMainContentToSection($section, $statement, $tableHeaders);
        $this->addSectionFooter($section, $statement);
    }

    private function createPhpWord(): PhpWord
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->addFontStyle('global', $this->styles['globalFont']);

        return $phpWord;
    }

    private function createNewSection(PhpWord $phpWord): Section
    {
        return $phpWord->addSection($this->styles['globalSection']);
    }

    private function addSectionHeader(Section $section, Procedure $procedure): void
    {
        $this->headerFooterManager->addHeader($section, $procedure, Footer::FIRST);
        $this->headerFooterManager->addHeader($section, $procedure);
    }

    private function addMainContentToSection(Section $section, Statement $statement, array $tableHeaders): void
    {
        $this->statementDetailsManager->addStatementInfo($section, $statement);
        $this->statementDetailsManager->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement, $tableHeaders);
    }

    private function addSectionFooter(Section $section, Statement $statement): void
    {
        $this->headerFooterManager->addFooter($section, $statement);
    }

    public function addSegments(Section $section, Statement $statement, array $tableHeaders): void
    {
        if ($statement->getSegmentsOfStatement()->isEmpty()) {
            $this->addNoSegmentsMessage($section);
        } else {
            $this->segmentTableManager->addSegmentsTable($section, $statement, $tableHeaders);
        }
    }

    private function addNoSegmentsMessage(Section $section): void
    {
        $noEntriesMessage = $this->translator->trans('statement.has.no.segments');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);
    }
}
