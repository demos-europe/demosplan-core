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
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentExporterFileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementArrayConverter;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ConvertedSegment;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use ReflectionException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsByStatementsExporter extends SegmentsExporter
{
    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        CurrentUserInterface $currentUser,
        HtmlHelper $htmlHelper,
        ImageManager $imageManager,
        ImageLinkConverter $imageLinkConverter,
        private readonly SegmentExporterFileNameGenerator $fileNameGenerator,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator,
        private readonly StatementArrayConverter $statementArrayConverter,
    ) {
        parent::__construct($currentUser, $htmlHelper, $imageManager, $imageLinkConverter, $slugify, $styleInitializer, $translator);
    }

    public function getSynopseFileName(Procedure $procedure, string $suffix): string
    {
        return 'Synopse-'.$this->slugify->slugify($procedure->getName()).'.'.$suffix;
    }

    /**
     * @throws Exception
     */
    public function exportAll(
        array $tableHeaders,
        Procedure $procedure,
        bool $obscure,
        bool $censorCitizenData = false,
        bool $censorInstitutionData = false,
        Statement ...$statements,
    ): WriterInterface {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if (0 === count($statements)) {
            return $this->exportEmptyStatements($phpWord, $procedure);
        }

        return $this->exportStatements(
            $phpWord,
            $procedure,
            $statements,
            $tableHeaders,
            $censorCitizenData,
            $censorInstitutionData,
            $obscure
        );
    }

    /**
     * Exports Segments or the Statement itself, in case of unsegmented Statement.
     *
     * @throws \PhpOffice\PhpSpreadsheet\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws ReflectionException
     * @throws HandlerException
     */
    public function exportAllXlsx(Statement ...$statements): IWriter
    {
        Settings::setOutputEscapingEnabled(true);
        $exportData = [];
        $convertedSegments = [];
        // unfortunately for xlsx export data needs to be an array
        foreach ($statements as $statement) {
            $segmentsOrStatements = collect([$statement]);
            if (!$statement->getSegmentsOfStatement()->isEmpty()) {
                $segmentsOrStatements = $statement->getSegmentsOfStatement();
                $convertedSegments[] =
                    $this->convertImagesToReferencesInRecommendations($segmentsOrStatements->toArray());
            }
            foreach ($segmentsOrStatements as $segmentOrStatement) {
                $exportData[] = $this->statementArrayConverter->convertIntoExportableArray($segmentOrStatement);
            }
        }

        foreach ($convertedSegments as $convertedSegment) {
            $exportData = $this->updateRecommendationsWithTextReferences($exportData, $convertedSegment);
        }

        $columnsDefinition = $this->assessmentTableXlsExporter->selectFormat('segments');

        return $this->assessmentTableXlsExporter->createExcel($exportData, $columnsDefinition);
    }

    private function convertImagesToReferencesInRecommendations(array $segments): array
    {
        $sortedSegments = $this->sortSegmentsByOrderInProcedure($segments);

        $convertedSegments = [];
        /** @var Segment $segment */
        foreach ($sortedSegments as $segment) {
            $externId = $segment->getExternId();
            $convertedSegment = $this->imageLinkConverter->convert($segment, $externId, false);
            $convertedSegments[$externId] = $convertedSegment;
        }
        $this->imageLinkConverter->resetImages();

        return $convertedSegments;
    }

    /**
     * @param array<string, mixed>            $segmentsOrStatements
     * @param array<string, ConvertedSegment> $convertedSegments
     *
     * @return array<string, mixed>
     */
    private function updateRecommendationsWithTextReferences(
        array $segmentsOrStatements,
        array $convertedSegments,
    ): array {
        foreach ($segmentsOrStatements as $key => $segmentOrStatement) {
            $isNotSegment = !array_key_exists('recommendation', $segmentOrStatement);
            $externIdIsNotOfSegment = !array_key_exists($segmentOrStatement['externId'], $convertedSegments);
            if ($isNotSegment || $externIdIsNotOfSegment) {
                continue;
            }

            $segmentOrStatement['text'] = $convertedSegments[$segmentOrStatement['externId']]->getText();
            $segmentOrStatement['recommendation'] =
                $convertedSegments[$segmentOrStatement['externId']]->getRecommendationText();
            $segmentsOrStatements[$key] = $segmentOrStatement;
        }

        return $segmentsOrStatements;
    }

    /**
     * @throws Exception
     */
    private function exportEmptyStatements(PhpWord $phpWord, Procedure $procedure): WriterInterface
    {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        return $this->addNoStatementsMessage($phpWord, $section);
    }

    /**
     * @param array<int, Statement> $statements
     *
     * @throws Exception
     */
    private function exportStatements(
        PhpWord $phpWord,
        Procedure $procedure,
        array $statements,
        array $tableHeaders,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        bool $obscure,
    ): WriterInterface {
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);

        foreach ($statements as $index => $statement) {
            $censored = $this->needsToBeCensored(
                $statement,
                $censorCitizenData,
                $censorInstitutionData,
            );

            $this->exportStatement($section, $statement, $tableHeaders, $censored, $obscure);
            $section = $this->getNewSectionIfNeeded($phpWord, $section, $index, $statements);
        }

        return IOFactory::createWriter($phpWord);
    }

    public function exportStatementSegmentsInSeparateDocx(
        Statement $statement,
        Procedure $procedure,
        array $tableHeaders,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        bool $obscureParameter,
    ): PhpWord {
        $censored = $this->needsToBeCensored(
            $statement,
            $censorCitizenData,
            $censorInstitutionData,
        );

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure);
        $this->exportStatement($section, $statement, $tableHeaders, $censored, $obscureParameter);

        return $phpWord;
    }

    public function exportStatement(
        Section $section,
        Statement $statement,
        array $tableHeaders,
        $censored = false,
        $obscure = false,
    ): void {
        $this->addStatementInfo($section, $statement, $censored);
        $this->addSimilarStatementSubmitters($section, $statement);
        $this->addSegments($section, $statement, $tableHeaders, $obscure);
        $this->addFooter($section, $statement, $censored);
    }

    /**
     * Creates a file name from each given {@link Statement} to be used in the ZIP the
     * {@link Statement} is exported in.
     *
     * Initially the file name is created from the
     * submitters name and the extern ID of the statement. In case of one or multiple
     * duplicate file names based on this information the database ID is used additionally
     * for all conflicting {@link Statement}s. Non-conflicting {@link Statement}s will
     * still only use the submitter name and intern ID.
     *
     * @param array<int, Statement> $statements
     *
     * @return array<string, Statement>
     */
    public function mapStatementsToPathInZip(
        array $statements,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        string $fileNameTemplate = '',
    ): array {
        $pathedStatements = [];
        $previousKeysOfReaddedDuplicates = [];
        foreach ($statements as $statement) {
            $censored = $this->needsToBeCensored(
                $statement,
                $censorCitizenData,
                $censorInstitutionData,
            );

            $pathInZip = $this->getPathInZip($statement, false, $fileNameTemplate, $censored);
            // in case of a duplicate, add the database ID to the name
            if (array_key_exists($pathInZip, $pathedStatements)) {
                $duplicate = $pathedStatements[$pathInZip];
                $previousKeysOfReaddedDuplicates[$pathInZip] = $pathInZip;
                $duplicateExtendedPathInZip = $this->getPathInZip($duplicate, true, $fileNameTemplate);
                $pathedStatements[$duplicateExtendedPathInZip] = $duplicate;
                $pathInZip = $this->getPathInZip($statement, true, $fileNameTemplate);
            }

            if (array_key_exists($pathInZip, $pathedStatements)) {
                throw new InvalidArgumentException('duplicated statement given');
            }

            $pathedStatements[$pathInZip] = $statement;
        }

        // Remove old keys of duplicates only after the previous loop has completed,
        // as otherwise a third duplicate would be added to the result array without
        // the extended path.
        foreach ($previousKeysOfReaddedDuplicates as $key) {
            unset($pathedStatements[$key]);
        }

        return $pathedStatements;
    }

    /**
     * Creates a file name from the given {@link Statement}.
     *
     * The file name is created from the submitters name and the extern ID of the statement.
     * If the trimmed extern ID is an empty string it will not be included in the result.
     * Optionally the database ID of the statement can be included too to ensure uniqueness.
     *
     * While the extern ID is set in normal parenthesis (`(1234)`), the database ID is set
     * in square brackets (`[abcd-ef12-…]`). This avoids confusion on the users part for
     * the case that the extern ID is an empty string and the database ID is included in
     * the result.
     */
    private function getPathInZip(
        Statement $statement,
        bool $withDbId,
        string $fileNameTemplate = '',
        bool $censored = false,
    ): string {
        // prepare needed variables
        $dbId = $statement->getId();

        $fileName = $this->fileNameGenerator->getFileName($statement, $fileNameTemplate, $censored);

        return $withDbId
            ? "$fileName-$dbId.docx"
            : "$fileName.docx";
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
