<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter;

use demosplan\DemosPlanCoreBundle\Entity\File;
use Exception;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class AssessmentTableZipExporter extends AssessmentTableXlsExporter
{
    protected array $supportedTypes = ['zip'];

    /**
     * @throws Exception
     */
    public function __invoke(array $parameters): array
    {
        $xlsxArray = parent::__invoke($parameters);

        $statementAttachments = $this->getAttachmentsOfStatements($parameters['items']);

        /** @var Xlsx $xlsxWriter */
        $xlsxWriter = $xlsxArray['writer'];
        $xlsxArray['writer'] = $this->writeReferencesIntoXlsx($xlsxWriter, $statementAttachments);

        return [
            'zipFileName' => $this->translator->trans('evaluation.assessment.table.export'),
            'xlsx'        => $xlsxArray,
            'attachments' => $statementAttachments,
        ];
    }

    /**
     * @return array<int, array<int, File>>
     *
     * @throws Exception
     */
    private function getAttachmentsOfStatements(array $statementIds): array
    {
        $files = [];
        $index = 0;
        foreach ($statementIds as $statementId) {
            $statementAttachments =
                $this->assessmentHandler->getStatementService()->getFileContainersForStatement($statementId);
            $files[$index] = [];
            foreach ($statementAttachments as $statementAttachment) {
                $files[$index][] = $statementAttachment->getFile();
            }
            ++$index;
        }

        return $files;
    }

    /**
     * @param array<int, array<int, File> $files
     */
    private function writeReferencesIntoXlsx(Xlsx $xlsxWriter, array $files): Xlsx
    {
        $spreadsheet = $xlsxWriter->getSpreadsheet();
        $sheet = $spreadsheet->getSheetByName($this->translator->trans('considerationtable'));

        if (null === $sheet) {
            $this->logger->error('No worksheet in xlsx for zip export!', [$sheet]);
        }

        $rowCount = $sheet->getHighestRow();
        $lastColumn = $sheet->getHighestColumn();
        $indexStatment = 0;
        for ($row = 2; $row <= $rowCount; ++$row) {
            $referencesAsString = '';
            /** @var File $file */
            foreach ($files[$indexStatment] as $file) {
                $referencesAsString .= $file->getHash().', ';
            }
            $cell = $lastColumn.$row;
            $sheet->setCellValue($cell, trim($referencesAsString, ', '));
            ++$indexStatment;
        }
        $xlsxWriter->setSpreadsheet($spreadsheet);

        return $xlsxWriter;
    }
}
