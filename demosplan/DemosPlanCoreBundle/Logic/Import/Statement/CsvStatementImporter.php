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
 * Unlike the spreadsheet import, which derives the submitter type from the worksheet a row lives in,
 * a CSV holds a single flat table and uses the `Typ` column instead - the same way the metadata
 * worksheet of the segment import does.
 *
 * The entities are neither persisted nor flushed here, see {@link CsvStatementImport}.
 */
class CsvStatementImporter
{
    private const DELIMITER = ';';
    private const ENCLOSURE = '"';
    private const COLUMN_TYPE = 'Typ';
    private const DETECTABLE_ENCODINGS = 'UTF-8, ISO-8859-1, ISO-8859-15';

    /**
     * All columns the import expects. They are the columns of the `Öffentlichkeit` and `Institution`
     * worksheets of the spreadsheet statement import, merged into one table and prefixed by `Typ`.
     */
    private const REQUIRED_COLUMNS = [
        self::COLUMN_TYPE,
        'ID',
        'Institution',
        'Abteilung',
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

    public function __construct(
        private readonly ExcelImporter $statementImporter,
        private readonly LoggerInterface $logger,
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

            foreach ($this->readRows($reader) as $fileLine => $row) {
                $this->processRow($row, $fileLine, $sheetTitle, $result);
            }
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
        $reader = Reader::createFromPath($file->getPathname());
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
        $presentColumns = array_map(static fn (string $column): string => trim($column), $header);

        return array_values(array_diff(self::REQUIRED_COLUMNS, $presentColumns));
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
    private function processRow(array $row, int $fileLine, string $sheetTitle, SegmentExcelImportResult $result): void
    {
        // an omitted type means a statement submitted by the public, as in the segment import
        $type = $row[self::COLUMN_TYPE] ?? ExcelImporter::PUBLIC;

        if (!in_array($type, [ExcelImporter::PUBLIC, ExcelImporter::INSTITUTION], true)) {
            $result->addError(
                $this->translator->trans(
                    'statements.import.csv.error.type',
                    [
                        'value'      => $type,
                        'validTypes' => implode(', ', [ExcelImporter::PUBLIC, ExcelImporter::INSTITUTION]),
                    ]
                ),
                $fileLine,
                $sheetTitle
            );

            return;
        }

        $row[self::COLUMN_TYPE] = $type;
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
