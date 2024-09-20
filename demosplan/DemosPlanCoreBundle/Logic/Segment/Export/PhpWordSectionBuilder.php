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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\PhpWord;
use Symfony\Contracts\Translation\TranslatorInterface;

class PhpWordSectionBuilder
{
    /**
     * @var array<string, mixed>
     */
    protected array $styles;

    public function __construct(
        private readonly HeaderFooterManager $headerFooterManager,
        private readonly SegmentTableManager $segmentTableManager,
        private readonly StatementDetailsManager $statementDetailsManager,
        StyleInitializer $styleInitializer,
        private readonly TranslatorInterface $translator,
    ) {
        $this->styles = $styleInitializer->getStyles();
    }

    public function createPhpWord(): PhpWord
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $phpWord->addFontStyle('global', $this->styles['globalFont']);

        return $phpWord;
    }

    public function createNewSection(PhpWord $phpWord): Section
    {
        return $phpWord->addSection($this->styles['globalSection']);
    }

    public function addSectionHeader(Section $section, Procedure $procedure): void
    {
        $this->headerFooterManager->addHeader($section, $procedure, Footer::FIRST);
        $this->headerFooterManager->addHeader($section, $procedure);
    }

    public function addMainContentToSection(Section $section, Statement $statement, array $tableHeaders): void
    {
        $this->statementDetailsManager->addStatementInfo($section, $statement);
        $this->statementDetailsManager->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement, $tableHeaders);
    }

    public function addSectionFooter(Section $section, Statement $statement): void
    {
        $this->headerFooterManager->addFooter($section, $statement);
    }

    public function addNoStatementsMessage(Section $section): void
    {
        $noEntriesMessage = $this->translator->trans('statements.filtered.none');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);
    }

    /**
     * @param array<int, Statement> $statements
     */
    public function getNewSectionIfNeeded(PhpWord $phpWord, Section $section, int $i, array $statements): Section
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

    private function addSegments(Section $section, Statement $statement, array $tableHeaders): void
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
