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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementAttachmentInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Exception\AssessmentTableZipExportException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\AssessmentTable\AssessmentTableServiceOutput;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Exception;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

class AssessmentTableZipExporter extends AssessmentTableFileExporterAbstract
{
    private const ATTACHMENTS_NOT_ADDABLE = 'error.statements.zip.export.attachments.not.addable';
    private const SHEET_MISSING_IN_XLSX = 'error.statements.zip.export.incomplete.xlsx';
    private const SHEET_MISSING_COLUMN = 'error.statements.zip.export.column.missing';
    private const ATTACHMENTS_NOT_ADDABLE_LOG =
        'An error occurred during the getting of Statement Attachments for Zip export. Zip export was canceled.';
    private const SHEET_MISSING_IN_XLSX_LOG = 'No worksheet in xlsx for zip export!';
    private const SHEET_MISSING_COLUMN_LOG = 'No column for references to attachment in worksheet for zip export!';
    private array $supportedTypes = ['zip'];

    public function __construct(
        AssessmentHandler $assessmentHandler,
        AssessmentTableServiceOutput $assessmentTableServiceOutput,
        CurrentProcedureService $currentProcedureService,
        LoggerInterface $logger,
        RequestStack $requestStack,
        StatementHandler $statementHandler,
        TranslatorInterface $translator,
        private readonly AssessmentTablePdfExporter $pdfExporter,
        private readonly AssessmentTableXlsExporter $xlsExporter,
        private readonly StatementService $statementService,
        private readonly FileService $fileService
    ) {
        parent::__construct(
            $assessmentTableServiceOutput,
            $currentProcedureService,
            $assessmentHandler,
            $translator,
            $logger,
            $requestStack,
            $statementHandler
        );
    }

    public function supports(string $format): bool
    {
        return in_array($format, $this->supportedTypes, true);
    }

    /**
     * @throws Exception
     */
    public function __invoke(array $parameters): array
    {
        if (!array_key_exists('exportType', $parameters)) {
            $this->logger->error('Export type not set in parameters for zip export.');
            throw new AssessmentTableZipExportException('error', 'Export type not set in parameters for zip export.');
        }

        $exportType = $parameters['exportType'];
        if ('statementsWithAttachments' === $exportType) {
            return $this->exportStatementsAsZipWithAttachments($parameters, $exportType);
        }
        if ('originalStatements' === $exportType) {
            return $this->exportOriginalStatementsAsPdfsInZip($parameters, $exportType);
        }

        throw new AssessmentTableZipExportException('error', 'Export type not set in parameters for zip export.');
    }

    private function exportStatementsAsZipWithAttachments(array $parameters, string $exportType): array
    {
        $xlsxArray = $this->xlsExporter->__invoke($parameters);

        try {
            $statementAttachments = $this->getAttachmentsOfStatements($xlsxArray['statementIds']);
        } catch (Exception $e) {
            $this->logger->error(self::ATTACHMENTS_NOT_ADDABLE_LOG, [$e]);
            throw new AssessmentTableZipExportException('error', self::ATTACHMENTS_NOT_ADDABLE);
        }

        /** @var Xlsx $xlsxWriter */
        $xlsxWriter = $xlsxArray['writer'];
        $xlsxArray['writer'] = $this->writeReferencesIntoXlsx($xlsxWriter, $statementAttachments);

        return [
            'zipFileName' => $this->translator->trans('evaluation.assessment.table.export'),
            'xlsx'        => $xlsxArray,
            'attachments' => $statementAttachments,
            'exportType'  => $exportType,
        ];
    }

    /**
     * @throws MessageBagException
     * @throws Exception
     */
    private function exportOriginalStatementsAsPdfsInZip(array $parameters, string $exportType): array
    {
        if ([] === $parameters['items']) {
            $outputResult = $this->assessmentHandler->prepareOutputResult(
                $parameters['procedureId'],
                $parameters['original'],
                $parameters
            );
            $statementIds = [];
            foreach ($outputResult->getStatements() as $statement) {
                $statementIds[] = $statement['id'];
            }
            $parameters['items'] = $statementIds;
        }

        $pdfs = [];
        $statementIds = $parameters['items'];
        foreach ($statementIds as $statementId) {
            $parameters['items'] = $statementId;
            $parameters['statementId'] = $statementId;
            $pdf = $this->pdfExporter->__invoke($parameters);
            $pdf['externId'] = $this->statementService->getStatement($statementId)?->getExternId();
            $pdfs[] = $pdf;
        }

        return [
            'zipFileName'              => $this->translator->trans('evaluation.assessment.table.export'),
            'originalStatementsAsPdfs' => $pdfs,
            'exportType'               => $exportType,
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

        $parameters = [
            'procedureId' => $this->currentProcedureService->getProcedure()->getId(),
            'anonymous'   => false,
            'exportType'  => 'statementsOnly',
            'template'    => 'portrait',
            'original'    => true,
            'viewMode'    => 'view_mode_default',
        ];
        // set file attachments if present:
        foreach ($statementIds as $statementId) {
            $statementAttachments = $this->statementService->getFileContainersForStatement($statementId);
            $files[$index] = ['attachments' => [], 'originalAttachment' => null];
            foreach ($statementAttachments as $statementAttachment) {
                $files[$index]['attachments'][] = $statementAttachment->getFile();
            }
            // set the stn attachment:
            // if present just take the given one.
            $files[$index]['originalAttachment'] = $this->getOriginalAttachment($statementId);
            if (null === $files[$index]['originalAttachment']) {
                // if not present yet, invoke the pdfCreator and create an original-stn-pdf to use instead
                $parameters['statementId'] =
                    $this->statementService->getStatement($statementId)?->getOriginal()->getId();
                $files[$index]['originalAttachment'] = $this->pdfExporter->__invoke(
                    $parameters
                );
                $files[$index]['originalAttachment']['fileHash'] = $this->fileService->createHash();
            }
            ++$index;
        }

        return $files;
    }

    /**
     * @throws Exception
     */
    private function getOriginalAttachment(string $statementId): ?File
    {
        $statementAttachments = $this->statementService->getStatement($statementId)->getAttachments();
        foreach ($statementAttachments as $statementAttachment) {
            if (StatementAttachmentInterface::SOURCE_STATEMENT === $statementAttachment->getType()) {
                return $statementAttachment->getFile();
            }
        }

        return null;
    }

    /**
     * @param array<int, array<int, File>> $files
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
        $columnForReferencesToAttachments = $this->getColumnForReferencesToAttachments($sheet, 'statement.attachments.reference');
        $columnForReferencesToOriginalAttachments = $this->getColumnForReferencesToAttachments($sheet, 'statement.original.attachment.reference');
        $indexStatment = 0;
        for ($row = 2; $row <= $rowCount; ++$row) {
            $referencesAsString = '';
            $referencesAsStringOriginal = '';
            if (array_key_exists($indexStatment, $files)) {
                /** @var File $file */
                foreach ($files[$indexStatment]['attachments'] as $file) {
                    $referencesAsString .= $file->getHash().', ';
                }
                if ($files[$indexStatment]['originalAttachment'] instanceof File) {
                    $referencesAsStringOriginal = $files[$indexStatment]['originalAttachment']?->getHash() ?? '';
                }
                if (is_array($files[$indexStatment]['originalAttachment'])) {
                    $referencesAsStringOriginal = $files[$indexStatment]['originalAttachment']['fileHash'];
                }
            }

            $cell = $columnForReferencesToAttachments.$row;
            $sheet->setCellValue($cell, trim($referencesAsString, ', '));
            $cell = $columnForReferencesToOriginalAttachments.$row;
            $sheet->setCellValue($cell, $referencesAsStringOriginal);
            ++$indexStatment;
        }
        $xlsxWriter->setSpreadsheet($spreadsheet);

        return $xlsxWriter;
    }

    /**
     * @throws AssessmentTableZipExportException
     */
    private function getColumnForReferencesToAttachments(Worksheet $sheet, string $title): string
    {
        foreach ($sheet->getColumnIterator() as $column) {
            $columnTitle = $sheet->getCell($column->getColumnIndex().'1')->getValue();
            if ($columnTitle === $this->translator->trans($title)) {
                return $column->getColumnIndex();
            }
        }

        $this->logger->error(self::SHEET_MISSING_COLUMN_LOG, [$sheet]);
        throw new AssessmentTableZipExportException('error', self::SHEET_MISSING_COLUMN);
    }
}
