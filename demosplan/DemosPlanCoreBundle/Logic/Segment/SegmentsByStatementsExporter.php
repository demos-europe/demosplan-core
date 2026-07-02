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
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentXlsxExportColumnsEventInterface;
use DemosEurope\DemosplanAddon\Contracts\Events\SegmentXlsxExportDataEventInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentXlsxExportColumnsEvent;
use demosplan\DemosPlanCoreBundle\Event\Segment\SegmentXlsxExportDataEvent;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Logic\Export\DocumentWriterSelector;
use demosplan\DemosPlanCoreBundle\Logic\Export\PhpWordConfigurator;
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
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SegmentsByStatementsExporter extends SegmentsExporter
{
    private const SEGMENT_ID_COLUMN_WIDTH = 1550;
    private const SEGMENT_TEXT_AND_RECOMMENDATION_COLUMN_WIDTH = 6950;

    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        CurrentUserInterface $currentUser,
        private readonly EventDispatcherInterface $eventDispatcher,
        HtmlHelper $htmlHelper,
        protected ImageManager $imageManager,
        ImageLinkConverter $imageLinkConverter,
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
        array $exportFilteredByTagsWithTopics = [],
        bool $censorCitizenData = false,
        bool $censorInstitutionData = false,
        string $customHeaderText = '',
        Statement ...$statements,
    ): WriterInterface {
        Settings::setOutputEscapingEnabled(true);

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();

        if ([] === $statements) {
            return $this->exportEmptyStatements($phpWord, $procedure, $exportFilteredByTagsWithTopics, $customHeaderText);
        }

        return $this->exportStatements(
            $phpWord,
            $procedure,
            $statements,
            $tableHeaders,
            $censorCitizenData,
            $censorInstitutionData,
            $obscure,
            $exportFilteredByTagsWithTopics,
            $customHeaderText
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
                $convertedData = $this->statementArrayConverter->convertIntoExportableArray($segmentOrStatement);
                if ($segmentOrStatement instanceof Segment) {
                    $dataEvent = new SegmentXlsxExportDataEvent($segmentOrStatement, $convertedData);
                    $this->eventDispatcher->dispatch($dataEvent, SegmentXlsxExportDataEventInterface::class);
                    $convertedData = $dataEvent->getExportData();
                }
                $exportData[] = $convertedData;
            }
        }

        foreach ($convertedSegments as $convertedSegment) {
            $exportData = $this->updateRecommendationsWithTextReferences($exportData, $convertedSegment);
        }

        $columnsDefinition = $this->assessmentTableXlsExporter->selectFormat('segments');
        $columnsEvent = new SegmentXlsxExportColumnsEvent($columnsDefinition);
        $this->eventDispatcher->dispatch($columnsEvent, SegmentXlsxExportColumnsEventInterface::class);
        $columnsDefinition = $columnsEvent->getColumnsDefinition();

        $writer = $this->assessmentTableXlsExporter->createExcel($exportData, $columnsDefinition);

        $this->assessmentTableXlsExporter->addFilterInfoSheet($writer, $tagFilter);

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
    ): PhpWord {
        $censored = $this->needsToBeCensored(
            $statement,
            $censorCitizenData,
            $censorInstitutionData,
        );

        $phpWord = PhpWordConfigurator::getPreConfiguredPhpWord();
        $section = $phpWord->addSection($this->styles['globalSection']);
        $this->addHeader($section, $procedure, Footer::FIRST);
        $this->addHeader($section, $procedure, null);
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
}
