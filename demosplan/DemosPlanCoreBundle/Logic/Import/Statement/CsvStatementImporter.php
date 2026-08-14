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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Validator\StatementValidator;
use Generator;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\Translation\TranslatorInterface;
use UnexpectedValueException;

/**
 * Creates {@link Statement} entities from a CSV file holding one statement per row.
 *
 * Unlike the spreadsheet import, which derives the submitter type from the worksheet a row lives in, a
 * CSV holds a single flat table. `Institution` and `Abteilung` are optional: a file without them holds
 * only statements submitted by the public. In a file that has them, a row with an empty `Institution`
 * is a statement submitted by a member of the public, a non-empty one by that institution. Having only
 * one of the two columns is treated as a missing-columns error, not as a further, valid variant.
 *
 * The entities are neither persisted nor flushed here, see {@link CsvStatementImport}.
 */
readonly class CsvStatementImporter
{
    private const DELIMITER = ';';
    private const ENCLOSURE = '"';
    private const COLUMN_INSTITUTION = 'Institution';
    private const COLUMN_INTERN_ID = 'Eingangsnummer';
    private const DETECTABLE_ENCODINGS = 'UTF-8, ISO-8859-1, ISO-8859-15';

    /**
     * Columns every row must have, regardless of whether the file holds institution statements.
     */
    private const REQUIRED_COLUMNS = [
        'Name',
        'E-Mail',
        'Straße',
        'Hausnummer',
        'PLZ',
        'Ort',
        'Einreichungsdatum',
        'Verfassungsdatum',
        'Art der Einreichung',
        'Eingangsnummer',
        'Stellungnahmetext',
        'Memo',
    ];

    /**
     * Only valid together: either both are present, marking a file that also holds institution
     * statements, or both are absent, marking a file that holds public statements exclusively.
     */
    private const INSTITUTION_COLUMNS = [self::COLUMN_INSTITUTION, 'Abteilung'];

    public function __construct(
        private CurrentProcedureService $currentProcedureService,
        private ExcelImporter $statementImporter,
        private LoggerInterface $logger,
        private StatementService $statementService,
        private StatementValidator $statementValidator,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * Reads the given CSV file and returns the statements it describes, together with all violations
     * found along the way. Rows are independent: a violation in one row does not stop the others from
     * being read, so the user gets the full list of problems at once.
     *
     * @param string|null $displayName name shown alongside the violations; defaults to the file name
     *                                 on disk, which is a hash for files coming from the file storage
     */
    public function process(SplFileInfo $file, ?string $displayName = null): SegmentExcelImportResult
    {
        $result = new SegmentExcelImportResult();
        $sheetTitle = $displayName ?? $file->getFilename();

        try {
            $reader = $this->createReader($file);

            $missingColumns = $this->getMissingColumns($reader->getHeader());
            if ([] !== $missingColumns) {
                $result->addError(
                    $this->translator->trans(
                        'statements.import.csv.error.missing.columns',
                        ['columns' => implode(', ', $missingColumns)]
                    ),
                    1,
                    $sheetTitle
                );

                return $result;
            }

            // Eingangsnummer duplicates have to be known before any row is turned into a statement,
            // since a later row can duplicate an earlier one - so the whole file is read up front here.
            // This is not a new memory cost: every statement built below already ends up materialized
            // in $result before persistence begins, so holding the lighter, still-unbuilt rows first is
            // strictly cheaper.
            $rows = iterator_to_array($this->readRows($reader));
        } catch (CsvException $e) {
            // thrown by the csv parsing only - a violation of a single row never ends up here, and the
            // rows are parsed lazily, so this also covers a file that only turns out to be broken
            // halfway through
            $this->logger->error('[CsvStatementImporter] Could not read CSV file', [
                'file'      => $sheetTitle,
                'exception' => $e->getMessage(),
            ]);
            $result->addError(
                $this->translator->trans('statements.import.csv.error.unreadable'),
                0,
                $sheetTitle
            );

            return $result;
        }

        $duplicateInternIds = $this->findDuplicateInternIds($rows);

        foreach ($rows as $fileLine => $row) {
            $this->processRow($row, $fileLine, $sheetTitle, $result, $duplicateInternIds);
        }

        $this->logger->info('[CsvStatementImporter] CSV parsed', [
            'file'        => $sheetTitle,
            'statements'  => $result->getStatementCount(),
            'error_count' => count($result->getErrors()),
        ]);

        return $result;
    }

    /**
     * @throws CsvException
     */
    private function createReader(SplFileInfo $file): Reader
    {
        $reader = Reader::from($file->getPathname());
        $reader->setDelimiter(self::DELIMITER);
        $reader->setEnclosure(self::ENCLOSURE);
        // CSV files exported from Excel escape quotes by doubling them, so no escape character applies
        $reader->setEscape('');
        $reader->setHeaderOffset(0);
        $reader->skipInputBOM();

        return $reader;
    }

    /**
     * @param array<int, string> $header
     *
     * @return list<string>
     */
    private function getMissingColumns(array $header): array
    {
        // the header itself carries no encoding information either, same as every data cell - without
        // this an umlaut-containing column name (e.g. "Straße") in a non-UTF-8 file would never match
        // its UTF-8 counterpart in REQUIRED_COLUMNS and be reported as missing even though it is present
        $presentColumns = array_map(fn (string $column): string => trim($this->toUtf8($column)), $header);

        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $presentColumns));
        $missingInstitutionColumns = array_values(array_diff(self::INSTITUTION_COLUMNS, $presentColumns));

        // both entirely absent is a valid, public-statements-only file - see the class doc comment
        if (count($missingInstitutionColumns) === count(self::INSTITUTION_COLUMNS)) {
            return $missing;
        }

        return [...$missing, ...$missingInstitutionColumns];
    }

    /**
     * Yields the non-empty data rows keyed by their line number within the file.
     *
     * @return Generator<int, array<string, string|null>>
     */
    private function readRows(Reader $reader): Generator
    {
        foreach ($reader->getRecords() as $offset => $record) {
            $row = $this->normalizeRow($record);

            if ($this->statementImporter->isEmpty(array_values($row))) {
                continue;
            }

            // the header sits at offset 0, so the first data row is on the second line of the file
            yield $offset + 1 => $row;
        }
    }

    /**
     * Trims all cells and makes sure they are valid UTF-8, as CSV files carry no encoding information.
     *
     * Empty cells become null, the way an empty cell of a spreadsheet reaches
     * {@link ExcelImporter::createNewOriginalStatement()}. Without this an omitted `Eingangsnummer`
     * would be stored as an empty string, and the second statement without one would collide on the
     * `internId_procedure` unique index.
     *
     * @param array<string, string|null> $record
     *
     * @return array<string, string|null>
     */
    private function normalizeRow(array $record): array
    {
        $row = [];
        foreach ($record as $column => $value) {
            $value = trim($this->toUtf8((string) $value));
            $row[trim((string) $column)] = '' === $value ? null : $value;
        }

        return $row;
    }

    private function toUtf8(string $value): string
    {
        $encoding = mb_detect_encoding($value, self::DETECTABLE_ENCODINGS, true);

        if (!is_string($encoding) || 'UTF-8' === $encoding) {
            return $value;
        }

        return (string) mb_convert_encoding($value, 'UTF-8', $encoding);
    }

    /**
     * Determines which Eingangsnummer values in $rows cannot be used: either because the file itself
     * uses the same value more than once, or because it is already assigned to an existing statement
     * in the current procedure. Both would otherwise surface as a raw unique-constraint violation
     * during persistence - see {@link CsvStatementImport}.
     *
     * @param array<int, array<string, string|null>> $rows
     *
     * @return array<string, true>
     */
    private function findDuplicateInternIds(array $rows): array
    {
        $countsInFile = [];
        foreach ($rows as $row) {
            $internId = $row[self::COLUMN_INTERN_ID] ?? null;
            if (null !== $internId) {
                $countsInFile[$internId] = ($countsInFile[$internId] ?? 0) + 1;
            }
        }

        if ([] === $countsInFile) {
            return [];
        }

        $duplicatedInFile = array_keys(array_filter(
            $countsInFile,
            static fn (int $count): bool => $count > 1
        ));

        $currentProcedure = $this->currentProcedureService->getProcedureWithCertainty();
        $usedInDatabase = array_intersect_key(
            $this->statementService->getInternIdsInUse($currentProcedure->getId()),
            $countsInFile
        );

        return array_fill_keys([...$duplicatedInFile, ...array_keys($usedInDatabase)], true);
    }

    /**
     * @param array<string, string|null> $row
     * @param array<string, true>        $duplicateInternIds
     */
    private function processRow(
        array $row,
        int $fileLine,
        string $sheetTitle,
        SegmentExcelImportResult $result,
        array $duplicateInternIds,
    ): void {
        // an empty or entirely absent Institution means a statement submitted by the public, a filled
        // one by that institution - see the class doc comment
        $type = null === ($row[self::COLUMN_INSTITUTION] ?? null)
            ? ExcelImporter::PUBLIC
            : ExcelImporter::INSTITUTION;

        $internId = $row[self::COLUMN_INTERN_ID] ?? null;
        if (null !== $internId && isset($duplicateInternIds[$internId])) {
            $result->addError(
                $this->translator->trans(
                    'statements.import.csv.error.duplicate.internid',
                    ['value' => $internId]
                ),
                $fileLine,
                $sheetTitle
            );

            return;
        }

        $row['publicStatement'] = ExcelImporter::INSTITUTION === $type
            ? Statement::INTERNAL
            : Statement::EXTERNAL;

        $errorsBefore = count($this->statementImporter->getErrors());

        try {
            $originalStatement = $this->statementImporter->createNewOriginalStatement(
                $row,
                $result->getStatementCount(),
                $this->toImporterLine($fileLine),
                $sheetTitle
            );
        } catch (UnexpectedValueException $e) {
            // an unmappable submit type aborts createNewOriginalStatement() after reporting itself;
            // the remaining rows are still worth reading, so this stays a violation of this row
            if (0 === $this->transferErrors($errorsBefore, $result)) {
                $result->addError($e->getMessage(), $fileLine, $sheetTitle);
            }

            return;
        }

        // createNewOriginalStatement() reports invalid dates, submit types and texts on the importer
        if (count($this->statementImporter->getErrors()) > $errorsBefore) {
            $this->transferErrors($errorsBefore, $result);

            return;
        }

        $statement = $this->statementImporter->createCopy($originalStatement, flush: false);

        $violations = $this->statementValidator->validate($statement, [StatementInterface::IMPORT_VALIDATION]);
        if (0 !== $violations->count()) {
            $result->addErrors($violations, $fileLine, $sheetTitle);

            return;
        }

        $result->addStatement($statement);
    }

    /**
     * {@link AbstractStatementSpreadsheetImporter::addImportViolations()} adds two to the line number
     * it is given, to translate a zero based worksheet array index into a spreadsheet row number. CSV
     * rows are counted from the top of the file, so the offset has to be taken back out again.
     */
    private function toImporterLine(int $fileLine): int
    {
        return $fileLine - 2;
    }

    /**
     * Moves the violations the shared importer collected for the current row over into our result.
     *
     * @return int the number of violations moved
     */
    private function transferErrors(int $errorsBefore, SegmentExcelImportResult $result): int
    {
        $newErrors = array_slice($this->statementImporter->getErrors(), $errorsBefore);

        foreach ($newErrors as $error) {
            $result->addError($error->getMessage(), $error->getLineNumber(), $error->getWorksheetTitle());
        }

        return count($newErrors);
    }
}
