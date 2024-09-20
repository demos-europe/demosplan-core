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
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\HeaderFooterManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\PhpWordSectionBuilder;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StatementDetailsManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\SegmentSorter;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsExporter
{
    public function __construct(
        protected readonly HeaderFooterManager $headerFooterManager,
        protected readonly ImageLinkConverter $imageLinkConverter,
        protected readonly ImageManager $imageManager,
        private readonly PhpWordSectionBuilder $phpWordSectionBuilder,
        protected readonly SegmentSorter $segmentSorter,
        protected readonly Slugify $slugify,
        protected readonly StatementDetailsManager $statementDetailsManager,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * @throws Exception
     */
    public function exportForOneStatement(Procedure $procedure, Statement $statement, array $tableHeaders): WriterInterface
    {
        $phpWord = $this->phpWordSectionBuilder->createPhpWord();
        $this->buildSection($phpWord, $procedure, $statement, $tableHeaders);

        return IOFactory::createWriter($phpWord);
    }

    /**
     * @throws Exception
     */
    public function exportForMultipleStatements(
        array $tableHeaders,
        Procedure $procedure,
        Statement ...$statements,
    ): WriterInterface {
        Settings::setOutputEscapingEnabled(true);
        $phpWord = $this->phpWordSectionBuilder->createPhpWord();
        $section = $this->phpWordSectionBuilder->createNewSection($phpWord);
        $this->phpWordSectionBuilder->addSectionHeader($section, $procedure);

        if (0 === count($statements)) {
            $this->phpWordSectionBuilder->addNoStatementsMessage($section);

            return IOFactory::createWriter($phpWord);
        }

        foreach ($statements as $index => $statement) {
            $this->phpWordSectionBuilder->addMainContentToSection($section, $statement, $tableHeaders);
            $this->phpWordSectionBuilder->addSectionFooter($section, $statement);
            $section = $this->phpWordSectionBuilder->getNewSectionIfNeeded($phpWord, $section, $index, $statements);
        }

        return IOFactory::createWriter($phpWord);
    }

    private function buildSection(
        PhpWord $phpWord,
        Procedure $procedure,
        Statement $statement,
        array $tableHeaders,
    ): void {
        $section = $this->phpWordSectionBuilder->createNewSection($phpWord);
        $this->phpWordSectionBuilder->addSectionHeader($section, $procedure);
        $this->phpWordSectionBuilder->addMainContentToSection($section, $statement, $tableHeaders);
        $this->phpWordSectionBuilder->addSectionFooter($section, $statement);
    }
}
