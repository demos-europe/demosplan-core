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

use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\StatementInterface;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\MissingPostParameterException;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Worksheet\RowCellIterator;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function array_key_exists;

class StatementSpreadsheetImporter extends AbstractStatementSpreadsheetImporter
{
    public function __construct(CurrentProcedureService $currentProcedureService, CurrentUserService $currentUser, ElementsService $elementsService, OrgaService $orgaService, StatementCopier $statementCopier, StatementService $statementService, TranslatorInterface $translator, ValidatorInterface $validator, private readonly EntityManagerInterface $entityManager)
    {
        parent::__construct($currentProcedureService, $currentUser, $elementsService, $orgaService, $statementCopier, $statementService, $translator, $validator);
    }

    /**
     * Maps the actual column names to callbacks to be invoked with the cell corresponding that column.
     *
     * I.e. the order of the returned callbacks will match the order of the given columns.
     *
     * The callbacks will be connected to the given builder, i.e. invoking its setter methods with the corresponding
     * cell.
     *
     * If no callback is given for the current cell - null will be used as value.
     *
     * `null` values indicate (currently) missing support for the corresponding column, while still allowing it to
     * be present in the input file. Such columns should be simply ignored.
     *
     * @return array{array<string, callable(Cell): ConstraintViolationListInterface|null>, AbstractStatementFromRowBuilder}
     */
    protected function getColumnCallbacks(RowCellIterator $actualColumnNames): array
    {
        [$columnMapping, $builder] = $this->getColumnMapping();

        $allColumnNames = [];
        foreach ($actualColumnNames as $cellName) {
            $allColumnNames[$cellName->getValue()] = null;
        }
        // the columns we support we now assign a setter
        $columnMapping = array_intersect_key($columnMapping, $allColumnNames);
        // merge them in the correct order.
        $columnMapping = array_merge($allColumnNames, $columnMapping);

        return [$columnMapping, $builder];
    }

    /**
     * @return array{array<string, callable(Cell): ConstraintViolationListInterface|null>,StatementFromRowBuilder}
     */
    protected function getColumnMapping(): array
    {
        $builder = $this->getStatementFromRowBuilder($this->currentProcedureService->getProcedure());
        $callBackMap = [
            'ID'                            => $builder->setExternId(...),
            'Gruppenname'                   => null,
            'Text'                          => $builder->setText(...),
            'Begründung'                    => null,
            'Kreis'                         => null,
            'Schlagwort'                    => null,
            'Schlagwortkategorie'           => null,
            'Dokumentenkategorie'           => $builder->setPlanningDocumentCategoryTitle(...),
            'Dokument'                      => $builder->setPlanningDocumentTitle(...),
            'Absatz'                        => $builder->setParagraphTitle(...),
            'Status'                        => null,
            'Priorität'                     => null,
            'Votum'                         => null,
            'Organisation'                  => $builder->setOrgaName(...),
            'Abteilung'                     => $builder->setDepartmentName(...),
            'Verfasser*in'                  => $builder->setAuthorName(...),
            'Einreicher*in'                 => $builder->setSubmitterName(...),
            'E-Mail'                        => $builder->setSubmiterEmailAddress(...),
            'Straße'                        => $builder->setSubmitterStreetName(...),
            'Hausnummer'                    => $builder->setSubmitterHouseNumber(...),
            'PLZ'                           => $builder->setSubmitterPostalCode(...),
            'Ort'                           => $builder->setSubmitterCity(...),
            'Dateiname(n)'                  => null,
            'Einreichungsdatum'             => $builder->setSubmitDate(...),
            'Verfassungsdatum'              => $builder->setAuthoredDate(...),
            'Eingangsnummer'                => $builder->setInternId(...),
            'Notiz'                         => $builder->setMemo(...),
            'Rückmeldung'                   => $builder->setFeedback(...),
            'Mitzeichnende'                 => $builder->setNumberOfAnonymVotes(...),
            'Verfahrensschritt'             => null,
            'Art der Einreichung'           => null,
        ];

        return [$callBackMap, $builder];
    }

    /**
     * Verarbeitet Text und erhält dabei Formatierungen wie fett, kursiv und unterstrichen
     */
    protected function preserveFormatting(string $text): string
    {
        // Zuerst Zeilenumbrüche wie bisher verarbeiten
        $text = str_replace(["_x000D_\n", "\n"], '<br>', $text);

        // Bewahre Formatierung, indem wir Markdown-ähnliche Syntax in HTML umwandeln
        // Dies deckt Fälle ab, in denen die Formatierung bereits als Markdown-Syntax vorliegt
        $text = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text); // **bold** zu <strong>
        $text = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $text);             // *italic* zu <em>
        $text = preg_replace('/__(.*?)__/', '<u>$1</u>', $text);               // __underline__ zu <u>

        return $text;
    }

    private function getStatementFromRowBuilder(ProcedureInterface $procedure): StatementFromRowBuilder
    {
        return new StatementFromRowBuilder(
            $this->validator,
            $procedure,
            $this->currentUser->getUser(),
            $this->orgaService->getOrga(User::ANONYMOUS_USER_ORGA_ID),
            $this->elementsService,
            $this->getStatementTextConstraint(),
            $this->preserveFormatting(...)
        );
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

        $usedExternIds = $this->statementService->getExternIdsInUse($currentProcedure->getId());
        $usedInternIds = $this->statementService->getInternIdsInUse($currentProcedure->getId());

        // loop through all rows and (if valid) create corresponding original statements and statement copies
        [$columnCallbacks, $builder] = $this->getColumnCallbacks($headIterator);
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

            $internId = $builder->getInternId();
            $externId = $builder->getExternId();

            if (null !== $internId && array_key_exists($internId, $usedInternIds)) {
                // skip statements with existing intern IDs
                $this->skippedStatements[$internId] = ($this->skippedStatements[$internId] ?? 0) + 1;
                $builder->resetStatement();
                continue;
            }
            if (array_key_exists($externId, $usedExternIds)) {
                // skip statements with existing extern IDs
                $this->skippedStatements[$externId] = ($this->skippedStatements[$externId] ?? 0) + 1;
                $builder->resetStatement();
                continue;
            }

            $usedExternIds[$externId] = $externId;
            $usedInternIds[$internId] = $internId;

            // create the original statement and its copy if valid
            $originalStatementOrViolations = $builder->buildStatementAndReset();
            if ($originalStatementOrViolations instanceof Statement) {
                /*
                 * At this point the original Statement has been build including the file-references.
                 * File-references are persisted inside the { @link FileContainer } but were not flushed yet.
                 * Flushing the FileContainer needs to be done now - as the previously persisted original Statement is
                 * only now in a valid state and will not throw validation errors on flush.
                 * The File container has to be flushed now in order to create copies of them for the statement we
                 * will actually use.
                 */
                $this->entityManager->flush();

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
