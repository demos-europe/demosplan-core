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
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\FileNameGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageLinkConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ImageManager;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\StyleInitializer;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementArrayConverter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementExportTagFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ConvertedSegment;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpWord\Element\Footer;
use PhpOffice\PhpWord\Element\Section;
use PhpOffice\PhpWord\Element\Table;
use PhpOffice\PhpWord\Exception\Exception;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\Settings;
use PhpOffice\PhpWord\Writer\WriterInterface;
use ReflectionException;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsByStatementsExporter extends SegmentsExporter
{
    private const SEGMENT_ID_COLUMN_WIDTH = 1550;
    private const SEGMENT_TEXT_AND_RECOMMENDATION_COLUMN_WIDTH = 6950;

    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        CurrentUserInterface $currentUser,
        HtmlHelper $htmlHelper,
        protected ImageManager $imageManager,
        ImageLinkConverter $imageLinkConverter,
        private readonly FileNameGenerator $fileNameGenerator,
        Slugify $slugify,
        StyleInitializer $styleInitializer,
        TranslatorInterface $translator,
        private readonly StatementArrayConverter $statementArrayConverter,
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
            self::SEGMENT_ID_COLUMN_WIDTH,
            self::SEGMENT_TEXT_AND_RECOMMENDATION_COLUMN_WIDTH);
    }

    /**
     * @throws Exception
     */
    public function exportAll(
        array $tableHeaders,
        Procedure $procedure,
        bool $obscure,
        bool $exportFilteredByTags = false,
        bool $censorCitizenData = false,
        bool $censorInstitutionData = false,
        Statement ...$statements,
    ): WriterInterface {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if ([] === $statements) {
            return $this->exportEmptyStatements($phpWord, $procedure, $exportFilteredByTags);
        }

        return $this->exportStatements(
            $phpWord,
            $procedure,
            $statements,
            $tableHeaders,
            $censorCitizenData,
            $censorInstitutionData,
            $obscure,
            $exportFilteredByTags
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
    public function exportAllXlsx(StatementExportTagFilter $tagFilter, Statement ...$statements): IWriter
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

        $writer = $this->assessmentTableXlsExporter->createExcel($exportData, $columnsDefinition);

        // Add meta data info sheet if permission allows
        if ($this->currentUser->hasPermission('feature_segments_export_excel_metadata')) {
            $this->assessmentTableXlsExporter->addFilterInfoSheet($writer, $tagFilter);
        }

        return $writer;
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

    public function exportStatementSegmentsInSeparateDocx(
        Statement $statement,
        Procedure $procedure,
        array $tableHeaders,
        bool $censorCitizenData,
        bool $censorInstitutionData,
        bool $obscureParameter,
        bool $exportFilteredByTags = false,
    ): PhpWord {
        $censored = $this->needsToBeCensored(
            $statement,
            $censorCitizenData,
            $censorInstitutionData,
        );

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST, $exportFilteredByTags);
        $this->addHeader($section, $procedure, null, $exportFilteredByTags);
        $this->exportStatement($section, $statement, $tableHeaders, $censored, $obscureParameter);

        return $phpWord;
    }

    protected function addContent(Section $section, Statement $statement, array $tableHeaders, bool $isObscure = false): void
    {
        if ($statement->getSegmentsOfStatement()->isEmpty()) {
            $this->addNoSegmentsMessage($section);
        } else {
            $this->addSegmentsTable($section, $statement, $tableHeaders, $isObscure);
        }
    }

    protected function addSegmentsTable(Section $section, Statement $statement, array $tableHeaders, bool $isObscure): void
    {
        $table = $this->addSegmentsTableHeader($section, $tableHeaders);
        $sortedSegments = $this->sortSegmentsByOrderInProcedure($statement->getSegmentsOfStatement()->toArray());

        foreach ($sortedSegments as $segment) {
            $this->addSegmentTableBody($table, $segment, $statement->getExternId(), $isObscure);
        }
        $this->imageManager->addImages($section);
    }

    private function addSegmentTableBody(Table $table, Segment $segment, string $statementExternId, bool $isObscure): void
    {
        $textRow = $table->addRow();
        // Replace image tags in segment text and in segment recommendation text with text references.
        $convertedSegment = $this->imageLinkConverter->convert($segment, $statementExternId, true, $isObscure);
        $this->addSegmentHtmlCell(
            $textRow,
            $segment->getExternId(),
            $this->styles['segmentsTableBodyCellID']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $convertedSegment->getText(),
            $this->styles['segmentsTableBodyCell']
        );
        $this->addSegmentHtmlCell(
            $textRow,
            $convertedSegment->getRecommendationText(),
            $this->styles['segmentsTableBodyCell']
        );
    }

    private function addSegmentsTableHeader(Section $section, array $tableHeaders): Table
    {
        $headerConfigs = [
            [
                'text'  => $tableHeaders['col1'] ?? $this->translator->trans('segments.export.segment.id'),
                'style' => $this->styles['segmentsTableHeaderCellID'],
            ],
            [
                'text'  => $tableHeaders['col2'] ?? $this->translator->trans('segments.export.statement.label'),
                'style' => $this->styles['segmentsTableHeaderCell'],
            ],
            [
                'text'  => $tableHeaders['col3'] ?? $this->translator->trans('segment.recommendation'),
                'style' => $this->styles['segmentsTableHeaderCell'],
            ],
        ];

        return $this->createTableWithHeader($section, $headerConfigs);
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
}
