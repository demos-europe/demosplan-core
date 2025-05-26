<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableXlsExporter;
use Exception;
use League\Csv\Writer;
use Symfony\Component\PropertyAccess\PropertyAccess;

class OriginalStatementCsvExporter extends CoreService
{
    public function __construct(
        private readonly AssessmentTableXlsExporter $assessmentTableXlsExporter,
        private readonly StatementService $statementService,
        private readonly AssessmentTableServiceOutput $assesmentTableServiceOutput)
    {
    }

    public function export(array $statements): string
    {
        $columnsDefinition = $this->assessmentTableXlsExporter->createColumnsDefinitionForStatementsOrSegments(true);
        $attributesToExport = array_column($columnsDefinition, 'key');

        $statementArrays = $this->convertStatementsToArrays($statements, $attributesToExport);
        $formattedData = $this->assessmentTableXlsExporter->prepareDataForExcelExport(
            $statementArrays,
            false,
            $attributesToExport
        );

        return $this->generateCsv($formattedData, $columnsDefinition);
    }

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

    private function convertStatementsToArrays(array $statements, array $attributesToExport): array
    {
        $propertyAccessor = PropertyAccess::createPropertyAccessor();
        $result = [];

        foreach ($statements as $statement) {
            $data = [];

            foreach ($attributesToExport as $key) {
                try {
                    if ('phase' === $key) {
                        $data['phase'] = $this->statementService->getProcedurePhaseName(
                            $statement->getPhase(),
                            $statement->isSubmittedByCitizen()
                        );
                        continue;
                    }

                    if ('meta.authoredDate' === $key) {
                        $data['meta.authoredDate'] = $this->assesmentTableServiceOutput->getFormattedAuthoredDateFromStatement($statement);
                        continue;
                    }
                    // Try to access the property directly
                    $data[$key] = $propertyAccessor->getValue($statement, $key);
                } catch (Exception $e) {
                    // For complex properties or when direct access fails
                    $data[$key] = '';
                }
            }

            $result[] = $data;
        }

        return $result;
    }
}
