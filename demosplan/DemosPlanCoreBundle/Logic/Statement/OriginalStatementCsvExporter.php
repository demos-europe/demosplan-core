<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Writer;
use ReflectionException;

class OriginalStatementCsvExporter extends CoreService
{
    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        private readonly SegmentsByStatementsExporter $segmentsByStatementsExporter)
    {
    }

    public function export(array $statements): string
    {
        $columnsDefinition = $this->assessmentTableXlsExporter->selectFormat('statements');
        $attributesToExport = array_column($columnsDefinition, 'key');

        $statementArrays = $this->convertStatementsToArrays($statements);
        $formattedData = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statementArrays,
            false,
            $attributesToExport
        );

        return $this->generateCsv($formattedData, $columnsDefinition);
    }

    /**
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws Exception
     */
    private function generateCsv(array $formattedData, array $columnsDefinition): string
    {
        $csv = Writer::createFromString('');
        $csv->setDelimiter(',');
        $csv->setEnclosure('"');
        $csv->setEscape('\\');

        // Add headers
        $headers = array_column($columnsDefinition, 'title');
        $csv->insertOne($headers);

        // Add data rows
        foreach ($formattedData as $row) {
            $csv->insertOne($row);
        }

        return $csv->toString();
    }

    /**
     * @throws ReflectionException
     */
    public function convertStatementsToArrays(array $statements): array
    {
        $statementsArray = [];

        foreach ($statements as $statement) {
            $statementArray = $this->segmentsByStatementsExporter->convertIntoExportableArray($statement);
            $statementsArray[] = $statementArray;
        }

        return $statementsArray;
    }
}
