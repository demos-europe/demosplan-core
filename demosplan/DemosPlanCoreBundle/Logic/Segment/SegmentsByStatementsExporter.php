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

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\HeaderFooterManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StatementDetailsManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsByStatementsExporter
{
    /**
     * @var array<string, mixed>
     */
    protected array $styles;
    public function __construct(
        private readonly HeaderFooterManager $headerFooterManager,
        private readonly SegmentsExporter $segmentsExporter,
        private readonly StatementDetailsManager $statementDetailsManager,
        StyleInitializer $styleInitializer,
        private readonly TranslatorInterface $translator
    ) {
        $this->styles = $styleInitializer->getStyles();
    }

    /**
     * @throws Exception
     */
    public function exportAll(array $tableHeaders, Procedure $procedure, Statement ...$statements): WriterInterface
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements($phpWord, $procedure, $statements, $tableHeaders);
    }

    /**
     * @throws Exception
     */
    private function exportEmptyStatements(PhpWord $phpWord, Procedure $procedure): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->headerFooterManager->addHeader($section, $procedure, Footer::FIRST);
        $this->headerFooterManager->addHeader($section, $procedure);

        return $this->addNoStatementsMessage($phpWord, $section);
    }

    /**
     * @param array<int, Statement> $statements
     *
     * @throws Exception
     */
    private function exportStatements(PhpWord $phpWord, Procedure $procedure, array $statements, array $tableHeaders): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->headerFooterManager->addHeader($section, $procedure, Footer::FIRST);
        $this->headerFooterManager->addHeader($section, $procedure);

        foreach ($statements as $index => $statement) {
            $this->exportStatement($section, $statement, $tableHeaders);
            $section = $this->getNewSectionIfNeeded($phpWord, $section, $index, $statements);
        }

        return IOFactory::createWriter($phpWord);
    }

    public function exportStatementSegmentsInSeparateDocx(Statement $statement, Procedure $procedure, array $tableHeaders): PhpWord
    {
        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->headerFooterManager->addHeader($section, $procedure, Footer::FIRST);
        $this->headerFooterManager->addHeader($section, $procedure);
        $this->exportStatement($section, $statement, $tableHeaders);

        return $phpWord;
    }

    public function exportStatement(Section $section, Statement $statement, array $tableHeaders): void
    {
        $this->statementDetailsManager->addStatementInfo($section, $statement);
        $this->statementDetailsManager->addSimilarStatementSubmitters($section, $statement);
        $this->segmentsExporter->addSegments($section, $statement, $tableHeaders);
        $this->headerFooterManager->addFooter($section, $statement);
    }

    /**
     * @throws Exception
     */
    private function addNoStatementsMessage(PhpWord $phpWord, Section $section): WriterInterface
    {
        $noEntriesMessage = $this->translator->trans('statements.filtered.none');
        $section->addText($noEntriesMessage, $this->styles['noInfoMessageFont']);

        return IOFactory::createWriter($phpWord);
    }

    /**
     * @param array<int, Statement> $statements
     */
    private function getNewSectionIfNeeded(PhpWord $phpWord, Section $section, int $i, array $statements): Section
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
}
