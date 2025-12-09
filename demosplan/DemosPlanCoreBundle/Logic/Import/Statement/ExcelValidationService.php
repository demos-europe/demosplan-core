<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use demosplan\DemosPlanCoreBundle\ValueObject\Import\ImportValidationResult;
use demosplan\DemosPlanCoreBundle\ValueObject\Import\SegmentImportDTO;
use demosplan\DemosPlanCoreBundle\ValueObject\Import\StatementImportDTO;
use Exception;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Pass 1: Fast validation of Excel import without entity creation.
 * Memory-efficient validation using lightweight DTOs.
 */
class ExcelValidationService
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Validate Excel file structure and data without creating entities.
     * Memory-efficient: Only DTOs and error messages kept in memory.
     *
     * @return ImportValidationResult Validation errors (empty if valid)
     */
    public function validateExcelFile(SplFileInfo $fileInfo): ImportValidationResult
    {
        $startTime = microtime(true);
        $result = new ImportValidationResult();

        $this->logger->info('[ExcelValidation] Pass 1: Starting validation', [
            'file'      => $fileInfo->getFilename(),
            'memory_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        try {
            $spreadsheet = IOFactory::load($fileInfo->getPathname());

            // Find worksheets
            [$segmentsWorksheet, $metaDataWorksheet] = $this->findWorksheets($spreadsheet, $result);
            if ($result->hasErrors()) {
                return $result; // Worksheet structure invalid
            }

            // Parse and validate segments (build internId tracker)
            $segmentsByStatementId = $this->validateSegmentsWorksheet($segmentsWorksheet, $result);

            // Parse and validate statements
            $this->validateStatementsWorksheet($metaDataWorksheet, $segmentsByStatementId, $result);

            $this->logger->info('[ExcelValidation] Pass 1: Validation complete', [
                'duration_sec' => round(microtime(true) - $startTime, 2),
                'errors'       => $result->getErrorCount(),
                'memory_mb'    => round(memory_get_usage(true) / 1024 / 1024, 2),
            ]);
        } catch (Exception $e) {
            $this->logger->error('[ExcelValidation] Validation failed with exception', [
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
            $result->addError('Fehler beim Lesen der Excel-Datei: '.$e->getMessage(), 0, 'Allgemein');
        }

        return $result;
    }

    /**
     * Find required worksheets in spreadsheet.
     *
     * @return array{0: Worksheet, 1: Worksheet}|array{}
     */
    private function findWorksheets(Spreadsheet $spreadsheet, ImportValidationResult $result): array
    {
        $segmentsWorksheet = null;
        $metaDataWorksheet = null;

        foreach ($spreadsheet->getAllSheets() as $worksheet) {
            $title = $worksheet->getTitle();

            if (str_contains($title, 'Abschnitte')) {
                $segmentsWorksheet = $worksheet;
            } elseif (str_contains($title, 'Metadaten')) {
                $metaDataWorksheet = $worksheet;
            }
        }

        if (null === $segmentsWorksheet) {
            $result->addError('Arbeitsblatt "Abschnitte" nicht gefunden', 0, 'Struktur');
        }
        if (null === $metaDataWorksheet) {
            $result->addError('Arbeitsblatt "Metadaten" nicht gefunden', 0, 'Struktur');
        }

        if ($result->hasErrors()) {
            return [];
        }

        return [$segmentsWorksheet, $metaDataWorksheet];
    }

    /**
     * Validate segments worksheet and return grouped segments.
     *
     * @return array<string, array<SegmentImportDTO>> Segments grouped by statement ID
     */
    private function validateSegmentsWorksheet(Worksheet $worksheet, ImportValidationResult $result): array
    {
        $segmentsByStatementId = [];
        $usedInternIds = [];
        $worksheetTitle = $worksheet->getTitle() ?? 'Abschnitte';
        $columnNames = $this->getFirstRowValues($worksheet);

        foreach ($worksheet->getRowIterator(2) as $lineNumber => $row) {
            $values = $this->extractRowValues($row, $worksheet);

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $this->validateAndAddSegment(
                $values,
                $columnNames,
                $lineNumber,
                $worksheetTitle,
                $result,
                $segmentsByStatementId,
                $usedInternIds
            );
        }

        $this->logger->info('[ExcelValidation] Segments validated', [
            'segments_count'  => array_sum(array_map('count', $segmentsByStatementId)),
            'statement_count' => count($segmentsByStatementId),
            'memory_mb'       => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);

        return $segmentsByStatementId;
    }

    /**
     * Validate a single segment row and add it to the collection.
     *
     * @param array<string, array<SegmentImportDTO>> $segmentsByStatementId
     * @param array<string, int>                     $usedInternIds
     */
    private function validateAndAddSegment(
        array $values,
        array $columnNames,
        int $lineNumber,
        string $worksheetTitle,
        ImportValidationResult $result,
        array &$segmentsByStatementId,
        array &$usedInternIds,
    ): void {
        $data = array_combine($columnNames, $values);

        $dto = new SegmentImportDTO(
            rowNumber: $lineNumber,
            statementId: (string) ($data['Stellungnahme ID'] ?? ''),
            internId: (string) ($data['Abschnitt Interner ID'] ?? ''),
            externId: $data['Abschnitt Externer ID'] ?? null,
            recommendation: (string) ($data['Erwiderung'] ?? ''),
            tags: $data['Schlagworte'] ?? null,
            places: $data['Orte'] ?? null,
            counties: $data['Kreise'] ?? null,
            municipalities: $data['Gemeinden'] ?? null,
            priorityAreas: $data['Vorranggebiete'] ?? null,
        );

        // Validate DTO
        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            $result->addErrors($violations, $lineNumber, $worksheetTitle);
        }

        // Check duplicate internId
        $this->checkDuplicateInternId($dto, $lineNumber, $worksheetTitle, $result, $usedInternIds);

        // Group segments by statement ID
        if (!isset($segmentsByStatementId[$dto->statementId])) {
            $segmentsByStatementId[$dto->statementId] = [];
        }
        $segmentsByStatementId[$dto->statementId][] = $dto;

        unset($dto);
    }

    /**
     * Check for duplicate intern IDs across segments.
     *
     * @param array<string, int> $usedInternIds
     */
    private function checkDuplicateInternId(
        SegmentImportDTO $dto,
        int $lineNumber,
        string $worksheetTitle,
        ImportValidationResult $result,
        array &$usedInternIds,
    ): void {
        if (empty($dto->internId) || '' === trim($dto->internId)) {
            return;
        }

        if (isset($usedInternIds[$dto->internId])) {
            $result->addError(
                "Doppelter Abschnitt Interner ID '{$dto->internId}' (bereits verwendet in Zeile {$usedInternIds[$dto->internId]})",
                $lineNumber,
                $worksheetTitle
            );
        } else {
            $usedInternIds[$dto->internId] = $lineNumber;
        }
    }

    /**
     * Validate statements worksheet and cross-validate with segments.
     *
     * @param array<string, array<SegmentImportDTO>> $segmentsByStatementId
     */
    private function validateStatementsWorksheet(
        Worksheet $worksheet,
        array $segmentsByStatementId,
        ImportValidationResult $result,
    ): void {
        $worksheetTitle = $worksheet->getTitle() ?? 'Metadaten';
        $columnNames = $this->getFirstRowValues($worksheet);
        $statementIdsSeen = [];

        foreach ($worksheet->getRowIterator(2) as $lineNumber => $row) {
            $values = $this->extractRowValues($row, $worksheet);

            if ($this->isEmptyRow($values)) {
                continue;
            }

            $this->validateSingleStatement(
                $values,
                $columnNames,
                $segmentsByStatementId,
                $lineNumber,
                $worksheetTitle,
                $result,
                $statementIdsSeen
            );
        }

        // Cross-validation: Check that all segments have a corresponding statement
        $this->validateOrphanedSegments($segmentsByStatementId, $statementIdsSeen, $result);

        $this->logger->info('[ExcelValidation] Statements validated', [
            'statements_count' => count($statementIdsSeen),
            'memory_mb'        => round(memory_get_usage(true) / 1024 / 1024, 2),
        ]);
    }

    /**
     * Validate a single statement row.
     *
     * @param array<string, array<SegmentImportDTO>> $segmentsByStatementId
     * @param array<string, bool>                    $statementIdsSeen
     */
    private function validateSingleStatement(
        array $values,
        array $columnNames,
        array $segmentsByStatementId,
        int $lineNumber,
        string $worksheetTitle,
        ImportValidationResult $result,
        array &$statementIdsSeen,
    ): void {
        $data = array_combine($columnNames, $values);
        $statementId = (string) ($data['Stellungnahme ID'] ?? '');

        // Check if statement has segments
        $segmentCount = isset($segmentsByStatementId[$statementId])
            ? count($segmentsByStatementId[$statementId])
            : 0;

        if (0 === $segmentCount) {
            $result->addError(
                "Stellungnahme ID '{$statementId}' hat keine zugehörigen Abschnitte",
                $lineNumber,
                $worksheetTitle
            );

            return;
        }

        // Create and validate DTO
        $dto = new StatementImportDTO(
            rowNumber: $lineNumber,
            statementId: $statementId,
            externId: $data['Externe ID'] ?? null,
            submitterName: (string) ($data['Name'] ?? ''),
            submitterType: (string) ($data['Typ'] ?? ''),
            street: $data['Straße'] ?? null,
            postalCode: $data['Postleitzahl'] ?? null,
            city: $data['Stadt'] ?? null,
            email: $data['E-Mail'] ?? null,
            phone: $data['Telefon'] ?? null,
            text: '',
            publicStatement: $this->getPublicStatement($data['Typ'] ?? 'öffentlich'),
            segmentCount: $segmentCount,
        );

        $violations = $this->validator->validate($dto);
        if ($violations->count() > 0) {
            $result->addErrors($violations, $lineNumber, $worksheetTitle);
        }

        $statementIdsSeen[$statementId] = true;
        unset($dto);
    }

    /**
     * Check for segments without corresponding statements.
     *
     * @param array<string, array<SegmentImportDTO>> $segmentsByStatementId
     * @param array<string, bool>                    $statementIdsSeen
     */
    private function validateOrphanedSegments(
        array $segmentsByStatementId,
        array $statementIdsSeen,
        ImportValidationResult $result,
    ): void {
        foreach ($segmentsByStatementId as $statementId => $segments) {
            if (!isset($statementIdsSeen[$statementId])) {
                $firstSegment = $segments[0];
                $result->addError(
                    "Abschnitte für Stellungnahme ID '{$statementId}' gefunden, aber keine Metadaten",
                    $firstSegment->rowNumber,
                    'Abschnitte + Metadaten'
                );
            }
        }
    }

    /**
     * Extract cell values from a row.
     */
    private function extractRowValues($row, Worksheet $worksheet): array
    {
        $cellIterator = $row->getCellIterator('A', $worksheet->getHighestColumn());
        $values = [];
        foreach ($cellIterator as $cell) {
            $values[] = $cell->getFormattedValue();
        }

        return $values;
    }

    /**
     * Get first row values as column names.
     *
     * @return array<int, string>
     */
    private function getFirstRowValues(Worksheet $worksheet): array
    {
        $firstRow = $worksheet->getRowIterator(1, 1)->current();
        $cellIterator = $firstRow->getCellIterator('A', $worksheet->getHighestColumn());

        // Extract values immediately during iteration to avoid worksheet detachment
        $values = [];
        foreach ($cellIterator as $cell) {
            $values[] = $cell->getFormattedValue();
        }

        return $values;
    }

    /**
     * Check if row is empty (all cells empty or whitespace).
     *
     * @param array<mixed> $values
     */
    private function isEmptyRow(array $values): bool
    {
        foreach ($values as $value) {
            if (!empty(trim((string) $value))) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine public/internal statement type.
     */
    private function getPublicStatement(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'intern', 'internal' => 'internal',
            default => 'external',
        };
    }
}
