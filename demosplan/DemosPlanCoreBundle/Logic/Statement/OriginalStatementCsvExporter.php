<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Logic\Export\CsvExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\Exporter\StatementArrayConverter;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use ReflectionException;

class OriginalStatementCsvExporter
{
    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        private readonly CsvExporter $csvExporter,
        private readonly StatementArrayConverter $statementArrayConverter)
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
        return $this->csvExporter->generate($formattedData, $columnsDefinition);
    }

    /**
     * @throws ReflectionException
     */
    public function convertStatementsToArrays(array $statements): array
    {
        $statementsArray = [];

        foreach ($statements as $statement) {
            $statementArray = $this->statementArrayConverter->convertIntoExportableArray($statement);
            $statementsArray[] = $statementArray;
        }

        return $statementsArray;
    }
}
