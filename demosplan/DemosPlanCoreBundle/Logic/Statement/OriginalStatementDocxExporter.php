<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\DocumentExporter\BaseDocxExporter;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class OriginalStatementDocxExporter extends BaseDocxExporter
{


    public function __construct(
        protected  StyleInitializer  $styleInitializer,
        protected TranslatorInterface $translator,
        private readonly SegmentsByStatementsExporter $segmentsByStatementsExporter)
    {
        parent::__construct(
            $styleInitializer,
            $translator,
        );
    }

    public function export(array $statements, Procedure $procedure): WriterInterface
    {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->segmentsByStatementsExporter->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements(
            $phpWord,
            $procedure,
            $statements,
            [],
            false,
            false,
            false,
            true,
        );
    }

    public function exportStatements(
        PhpWord $phpWord,
        Procedure $procedure,
        array $statements,
        array $tableHeaders,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        bool $obscure,
        bool $isOriginalStatementExport,
    ): WriterInterface {
        $section = $phpWord->addSection($this->segmentsByStatementsExporter->styles['globalSection']);
        $this->segmentsByStatementsExporter->addHeader($section, $procedure, Footer::FIRST);
        $this->segmentsByStatementsExporter->addHeader($section, $procedure);

        foreach ($statements as $index => $statement) {
            $censored = $this->segmentsByStatementsExporter->needsToBeCensored(
                $statement,
                $censorCitizenData,
                $censorInstitutionData,
            );

            $this->segmentsByStatementsExporter->exportStatement($section, $statement, $tableHeaders, $censored, $obscure, $isOriginalStatementExport);
            $section = $this->segmentsByStatementsExporter->getNewSectionIfNeeded($phpWord, $section, $index, $statements);
        }

        return IOFactory::createWriter($phpWord);
    }

    public function addHeader(Section $section, Procedure $procedure, ?string $headerType = null): void
    {
        // TODO: Implement addHeader() method.
    }
}
