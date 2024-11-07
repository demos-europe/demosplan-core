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

use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Exception\HandlerException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\ExportDataArrayGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\RecommendationConverter;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\SegmentSorter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use PhpOffice\PhpSpreadsheet\Exception;
use PhpOffice\PhpSpreadsheet\Writer\IWriter;
use PhpOffice\PhpWord\Settings;
use ReflectionException;

class XlsxSegmentsExporter
{
    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        private readonly ExportDataArrayGenerator $exportDataArrayGenerator,
        private readonly RecommendationConverter $recommendationConverter,
        private readonly SegmentSorter $segmentSorter,
    ) {
    }

    /**
     * Exports Segments or the Statement itself, in case of unsegmented Statement.
     *
     * @throws Exception
     * @throws \PhpOffice\PhpSpreadsheet\Reader\Exception
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws ReflectionException
     * @throws HandlerException
     */
    public function exportAllXlsx(Statement ...$statements): IWriter
    {
        Settings::setOutputEscapingEnabled(true);
        $exportData = [];
        $convertedSegmentsPerStatement = [];
        // unfortunately for xlsx export data needs to be an array
        foreach ($statements as $statement) {
            $segmentsOrStatements = collect([$statement]);
            if (!$statement->getSegmentsOfStatement()->isEmpty()) {
                $segmentsOrStatements = $statement->getSegmentsOfStatement();
                $convertedSegmentsPerStatement[] =
                    $this->recommendationConverter->convertImagesToReferencesInRecommendations(
                        $this->segmentSorter->sortSegmentsByOrderInProcedure($segmentsOrStatements->toArray())
                    );
            }
            foreach ($segmentsOrStatements as $segmentOrStatement) {
                $exportData[] = $this->exportDataArrayGenerator->convertIntoExportableArray($segmentOrStatement);
            }
        }

        foreach ($convertedSegmentsPerStatement as $convertedSegments) {
            $exportData = $this->recommendationConverter->updateRecommendationsWithTextReferences(
                $exportData,
                $convertedSegments
            );
        }

        $columnsDefinition = $this->assessmentTableXlsExporter->selectFormat('segments');

        return $this->assessmentTableXlsExporter->createExcel($exportData, $columnsDefinition);
    }
}
