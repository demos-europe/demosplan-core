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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\AssessmentTableZipExportException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\EditorService;
use demosplan\DemosPlanCoreBundle\Logic\FormOptionsResolver;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\SimpleSpreadsheetService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Tools\ServiceImporter;
use Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class AssessmentTableZipExporter extends AssessmentTableXlsExporter
{
    private const ATTACHMENTS_NOT_ADDABLE = 'error.statements.zip.export.attachments.not.addable';
    private const SHEET_MISSING_IN_XLSX = 'error.statements.zip.export.incomplete.xlsx';
    private const SHEET_MISSING_COLUMN = 'error.statements.zip.export.column.missing';
    private const ATTACHMENTS_NOT_ADDABLE_LOG =
        'An error occurred during the getting of Statement Attachments for Zip export. Zip export was canceled.';
    private const SHEET_MISSING_IN_XLSX_LOG = 'No worksheet in xlsx for zip export!';
    private const SHEET_MISSING_COLUMN_LOG = 'No column for references to attachment in worksheet for zip export!';
    protected array $supportedTypes = ['zip'];

    public function __construct(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        EditorService $editorService,
        Environment $twig,
        FormOptionsResolver $formOptionsResolver,
        LoggerInterface $logger,
        PermissionsInterface $permissions,
        RequestStack $requestStack,
        ServiceImporter $serviceImport,
        SimpleSpreadsheetService $simpleSpreadsheetService,
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
        private readonly StatementService $statementService
    ) {
        parent::__construct(
            $assessmentHandler,
            $assessmentTableServiceOutput,
            $currentProcedureService,
            $editorService,
            $twig,
            $formOptionsResolver,
            $logger,
            $permissions,
            $requestStack,
            $serviceImport,
            $simpleSpreadsheetService,
            $statementHandler,
            $translator
        );
    }

    /**
     * @throws Exception
     */
    public function __invoke(array $parameters): array
    {
        $xlsxArray = parent::__invoke($parameters);

        try {
            $statementAttachments = $this->getAttachmentsOfStatements($xlsxArray['statementIds']);
        } catch (Exception) {
            $this->logger->error(self::ATTACHMENTS_NOT_ADDABLE_LOG);
            throw new AssessmentTableZipExportException('error', self::ATTACHMENTS_NOT_ADDABLE);
        }

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
            $statementAttachments = $this->statementService->getFileContainersForStatement($statementId);
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
     *
     * @throws AssessmentTableZipExportException
     */
    private function writeReferencesIntoXlsx(Xlsx $xlsxWriter, array $files): Xlsx
    {
        $spreadsheet = $xlsxWriter->getSpreadsheet();
        $sheet = $spreadsheet->getSheetByName($this->translator->trans('considerationtable'));

        if (null === $sheet) {
            $this->logger->error(self::SHEET_MISSING_IN_XLSX_LOG, [$sheet]);
            throw new AssessmentTableZipExportException('error', self::SHEET_MISSING_IN_XLSX);
        }

        $rowCount = $sheet->getHighestRow();
        $columnForReferencesToAttachments = $this->getColumnForReferencesToAttachments($sheet);
        $indexStatment = 0;
        for ($row = 2; $row <= $rowCount; ++$row) {
            $referencesAsString = '';
            if (array_key_exists($indexStatment, $files)) {
                /** @var File $file */
                foreach ($files[$indexStatment] as $file) {
                    $referencesAsString .= $file->getHash().', ';
                }
            }

            $cell = $columnForReferencesToAttachments.$row;
            $sheet->setCellValue($cell, trim($referencesAsString, ', '));
            ++$indexStatment;
        }
        $xlsxWriter->setSpreadsheet($spreadsheet);

        return $xlsxWriter;
    }

    /**
     * @throws AssessmentTableZipExportException
     */
    private function getColumnForReferencesToAttachments(Worksheet $sheet): string
    {
        foreach ($sheet->getColumnIterator() as $column) {
            $columnTitle = $sheet->getCell($column->getColumnIndex().'1')->getValue();
            if ($columnTitle === $this->translator->trans('statement.attachments.reference')) {
                return $column->getColumnIndex();
            }
        }

        $this->logger->error(self::SHEET_MISSING_COLUMN_LOG, [$sheet]);
        throw new AssessmentTableZipExportException('error', self::SHEET_MISSING_COLUMN);
    }
}
