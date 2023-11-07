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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Webmozart\Assert\Assert;

use function array_key_exists;

class StatementSpreadsheetImporter extends AbstractStatementSpreadsheetImporter
{
    /**
     * Maps the actual column names to callbacks to be invoked with the cell corresponding that column.
     *
     * I.e. the order of the returned callbacks will match the order of the given columns.
     *
     * The callbacks will be connected to the given builder, i.e. invoking its setter methods with the corresponding
     * cell.
     *
     * `null` values indicate (currently) missing support for the corresponding column, while still allowing it to
     * be present in the input file. Such columns should be simply ignored.
     *
     * @return array<string, callable(Cell): ConstraintViolationListInterface|null>
     */
    protected function getColumnCallbacks(StatementFromRowBuilder $builder, RowCellIterator $actualColumnNames): array
    {
        $columnMapping = [
            'ID'                  => [$builder, 'setExternId'],
            'Gruppenname'         => null,
            'Text'                => [$builder, 'setText'],
            'Begründung'          => null,
            'Kreis'               => null,
            'Schlagwort'          => null,
            'Schlagwortkategorie' => null,
            'Dokumentenkategorie' => [$builder, 'setPlanningDocumentCategoryName'],
            'Dokument'            => null,
            'Absatz'              => null,
            'Status'              => null,
            'Priorität'           => null,
            'Votum'               => null,
            'Organisation'        => [$builder, 'setOrgaName'],
            'Abteilung'           => [$builder, 'setDepartmentName'],
            'Verfasser*in'        => [$builder, 'setAuthorName'],
            'Einreicher*in'       => [$builder, 'setSubmitterName'],
            'E-Mail'              => [$builder, 'setSubmiterEmailAddress'],
            'Straße'              => [$builder, 'setSubmitterStreetName'],
            'Hausnummer'          => [$builder, 'setSubmitterHouseNumber'],
            'PLZ'                 => [$builder, 'setSubmitterPostalCode'],
            'Ort'                 => [$builder, 'setSubmitterCity'],
            'Dateiname(n)'        => null,
            'Einreichungsdatum'   => [$builder, 'setSubmitDate'],
            'Verfassungsdatum'    => [$builder, 'setAuthoredDate'],
            'Eingangsnummer'      => [$builder, 'setInternId'],
            'Notiz'               => [$builder, 'setMemo'],
        ];

        // Currently an exception will be thrown in case of unsorted columns. To avoid that you can adjust the sorting
        // of the array above to the order of the actual columns.
        $expectedColumns = array_keys($columnMapping);
        foreach ($expectedColumns as $expectedColumnName) {
            Assert::true($actualColumnNames->valid());
            $actualColumn = $actualColumnNames->current();
            Assert::notNull($actualColumn);
            $actualColumnName = $actualColumn->getValue();
            Assert::same($actualColumnName, $expectedColumnName);
            $actualColumnNames->next();
        }
        // assures there can be only the supported column names.
        Assert::false($actualColumnNames->valid());

        return $columnMapping;
    }

    public function process(SplFileInfo $workbook): void
    {
        [$assessmentTable] = $this->extractWorksheets($workbook, 1);
        $worksheetTitle = $assessmentTable->getTitle();

        $rows = $assessmentTable->getRowIterator();
        $head = $rows->current();

        $rows->next();
        if (!$rows->valid()) {
            // no rows after head, nothing to do
            return;
        }

        $highestColumn = $assessmentTable->getHighestColumn();
        $headIterator = $head->getCellIterator('A', $highestColumn);
        $currentProcedure = $this->currentProcedureService->getProcedure()
            ?? throw new MissingPostParameterException('Current procedure is missing.');

        $builder = new StatementFromRowBuilder(
            $this->validator,
            $currentProcedure,
            $this->currentUser->getUser(),
            $this->orgaService->getOrga(User::ANONYMOUS_USER_ORGA_ID),
            $this->elementsService->getStatementElement($currentProcedure->getId()),
            $this->getStatementTextConstraint(),
            [$this, 'replaceLineBreak']
        );

        $usedExternIds = $this->statementService->getExternIdsInUse($currentProcedure->getId());

        // loop through all rows and (if valid) create corresponding original statements and statement copies
        $columnCallbacks = $this->getColumnCallbacks($builder, $headIterator);
        for (; $rows->valid(); $rows->next()) {
            $row = $rows->current();
            $zeroBasedStatementIndex = $row->getRowIndex() - 2;
            $cells = iterator_to_array($row->getCellIterator('A', $highestColumn));

            // fill builder with cells
            $violationLists = array_map(
                static fn (?callable $setter, Cell $cell): ?ConstraintViolationListInterface => null === $setter ? null : $setter($cell),
                $columnCallbacks,
                $cells
            );

            // add violations collected so far
            array_map(
                fn (ConstraintViolationListInterface $violations) => $this->addImportViolations(
                    $violations,
                    $zeroBasedStatementIndex,
                    $worksheetTitle
                ),
                array_diff($violationLists, [null])
            );

            // create the original statement and its copy if valid
            $originalStatementOrViolations = $builder->buildStatementAndReset();
            if ($originalStatementOrViolations instanceof Statement) {
                $externId = $originalStatementOrViolations->getExternId();
                if (array_key_exists($externId, $usedExternIds)) {
                    // skip statements with existing extern IDs
                    $this->skippedStatements[$externId] = ($this->skippedStatements[$externId] ?? 0) + 1;

                    continue;
                }
                $usedExternIds[$externId] = $externId;

                $statementCopy = $this->createCopy($originalStatementOrViolations);
                $violations = $this->validator->validate($statementCopy, null, [StatementInterface::IMPORT_VALIDATION]);
                if (0 === $violations->count()) {
                    $this->generatedStatements[] = $statementCopy;
                } else {
                    $this->addImportViolations($violations, $zeroBasedStatementIndex, $worksheetTitle);
                }
            } else {
                $this->addImportViolations($originalStatementOrViolations, $zeroBasedStatementIndex, $worksheetTitle);
            }
        }
    }
}
