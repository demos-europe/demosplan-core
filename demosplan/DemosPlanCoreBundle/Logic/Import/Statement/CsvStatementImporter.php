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
use Exception;
use Generator;
use League\Csv\Exception as CsvException;
use League\Csv\Reader;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Contracts\Translation\TranslatorInterface;

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
class CsvStatementImporter
{
    private const DELIMITER = ';';
    private const ENCLOSURE = '"';
    private const COLUMN_INSTITUTION = 'Institution';
    private const COLUMN_INTERN_ID = 'Eingangsnummer';
    private const DETECTABLE_ENCODINGS = 'UTF-8, ISO-8859-1, ISO-8859-15';

    /**
     * Upper bound on the number of data rows a single file may contain. The whole import runs
     * synchronously on a worker shared with unrelated scheduled tasks (mail sending, phase
     * switching, ...), so a file with no cap could block that worker, and the memory it holds,
     * for an unbounded amount of time.
     */
    private const MAX_ROWS = 3_000;

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

    /**
     * Eingangsnummer values already used, either by an existing statement or by an earlier row of the
     * file currently being processed - keyed and valued by themselves. Reset at the start of every
     * {@link process()} call, so state from one file never leaks into the next on this shared instance.
     *
     * @var array<non-empty-string, string>
     */
    private array $usedInternIds = [];

    public function __construct(
        private readonly CurrentProcedureService $currentProcedureService,
        private readonly ExcelImporter $statementImporter,
        private readonly LoggerInterface $logger,
        private readonly StatementService $statementService,
        private readonly StatementValidator $statementValidator,
        private readonly TranslatorInterface $translator,
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

            // mirrors StatementSpreadsheetImporter::process(): a row whose Eingangsnummer is
            // already used - by an existing statement or an earlier row in this file - is skipped
            // rather than rejected. Reset for every process() call, so a previous file processed on
            // this same (shared, re-used) instance never leaks into this one.
            $this->usedInternIds = $this->statementService->getInternIdsInUse(
                $this->currentProcedureService->getProcedureWithCertainty()->getId()
            );

            $rowCount = 0;
            foreach ($this->readRows($reader) as $fileLine => $row) {
                if (++$rowCount > self::MAX_ROWS) {
                    $result->addError(
                        $this->translator->trans(
                            'statements.import.csv.error.too_many_rows',
                            ['limit' => self::MAX_ROWS]
                        ),
                        $fileLine,
                        $sheetTitle
                    );

                    break;
                }

                $this->processRow($row, $fileLine, $sheetTitle, $result);
            }
        } catch (CsvException|Exception $e) {
            // thrown by the csv parsing only - a violation of a single row never ends up here
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
     * @param array<string, string|null> $row
     */
    private function processRow(
        array $row,
        int $fileLine,
        string $sheetTitle,
        SegmentExcelImportResult $result,
    ): void {
        // an empty or entirely absent Institution means a statement submitted by the public, a filled
        // one by that institution - see the class doc comment
        $type = null === ($row[self::COLUMN_INSTITUTION] ?? null)
            ? ExcelImporter::PUBLIC
            : ExcelImporter::INSTITUTION;

        $internId = $row[self::COLUMN_INTERN_ID] ?? null;
        if (null !== $internId && array_key_exists($internId, $this->usedInternIds)) {
            // a duplicate Eingangsnummer is treated the same as any other row-level violation
            // (e.g. an invalid date): reported as an error, which aborts the whole import - the
            // remaining rows are still read so the user gets the full list of problems at once
            $this->logger->info('[CsvStatementImporter] Duplicate Eingangsnummer', [
                'line'     => $fileLine,
                'file'     => $sheetTitle,
                'internId' => $internId,
            ]);
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

        if (null !== $internId) {
            $this->usedInternIds[$internId] = $internId;
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
        } catch (Exception $e) {
            // createNewOriginalStatement() can abort for many reasons (unmappable submit type,
            // a procedure without a configured statement element, ...), some of which already
            // report themselves on the importer beforehand; the remaining rows are still worth
            // reading, so this stays a violation of this row rather than aborting the whole file
            $this->logger->error('[CsvStatementImporter] Failed to create statement from row', [
                'line'      => $fileLine,
                'file'      => $sheetTitle,
                'exception' => $e->getMessage(),
            ]);

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
