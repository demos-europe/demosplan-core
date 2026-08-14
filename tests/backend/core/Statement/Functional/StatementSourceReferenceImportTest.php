<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\StatementSpreadsheetImporter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use Doctrine\ORM\EntityManagerInterface;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Covers the source reference an assessment table export carries so an importing instance can pair
 * its statements with the ones they originate from.
 */
class StatementSourceReferenceImportTest extends FunctionalTestCase
{
    private const CATEGORY = 'Gesamtstellungnahme';
    private const SOURCE_ID_1 = 'ae2f4c1e-3b7d-4a10-9c62-0f1b8d5e7a31';
    private const SOURCE_ID_2 = 'b1c9d0e2-5f43-4c88-a7d1-2e6f9b0c4a55';
    private const SOURCE_ID_3 = 'c7e5a3b1-9d02-4f76-8b34-1a5c8e2d6f90';

    /** @var list<string>|null */
    private ?array $temporaryFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles ?? [] as $temporaryFile) {
            if (is_file($temporaryFile)) {
                unlink($temporaryFile);
            }
        }
        $this->temporaryFiles = [];

        parent::tearDown();
    }

    public function testImportsEveryCopyWhenSourceReferencesDiffer(): void
    {
        // Three assessment table copies of the same statement: same extern id, distinct source
        // references, distinct text chunks.
        $workbook = $this->createWorkbook(
            ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz', 'Referenz'],
            [
                ['M1', 'first chunk', self::CATEGORY, null, null, self::SOURCE_ID_1],
                ['M1', 'second chunk', self::CATEGORY, null, null, self::SOURCE_ID_2],
                ['M1', 'third chunk', self::CATEGORY, null, null, self::SOURCE_ID_3],
            ]
        );

        $sut = $this->createImporter();
        $sut->process($workbook);

        self::assertFalse($sut->hasErrors(), var_export($sut->getErrorsAsArray(), true));
        self::assertCount(3, $sut->getGeneratedStatements());
        self::assertSame([], $sut->getSkippedStatements());

        $importedSourceIds = array_map(
            static fn (Statement $statement): ?string => $statement->getSourceStatementId(),
            $sut->getGeneratedStatements()
        );
        sort($importedSourceIds);
        self::assertSame([self::SOURCE_ID_1, self::SOURCE_ID_2, self::SOURCE_ID_3], $importedSourceIds);
    }

    public function testStoresSourceReferenceOnOriginalStatementAsWell(): void
    {
        $workbook = $this->createWorkbook(
            ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz', 'Referenz'],
            [['M1', 'some text', self::CATEGORY, null, null, self::SOURCE_ID_1]]
        );

        $sut = $this->createImporter();
        $sut->process($workbook);

        $generatedStatements = $sut->getGeneratedStatements();
        self::assertCount(1, $generatedStatements);
        $original = $generatedStatements[0]->getOriginal();
        self::assertInstanceOf(Statement::class, $original);
        self::assertSame(self::SOURCE_ID_1, $original->getSourceStatementId());
    }

    public function testSkipsStatementsWhoseSourceReferenceWasAlreadyImported(): void
    {
        $header = ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz', 'Referenz'];
        $firstImport = $this->createImporter();
        $firstImport->process($this->createWorkbook($header, [
            ['M1', 'first chunk', self::CATEGORY, null, null, self::SOURCE_ID_1],
        ]));
        self::assertCount(1, $firstImport->getGeneratedStatements());

        // Re-importing a newer export adds only statements not imported before.
        $secondImport = $this->createImporter();
        $secondImport->process($this->createWorkbook($header, [
            ['M1', 'first chunk', self::CATEGORY, null, null, self::SOURCE_ID_1],
            ['M2', 'another statement', self::CATEGORY, null, null, self::SOURCE_ID_2],
        ]));

        self::assertFalse($secondImport->hasErrors(), var_export($secondImport->getErrorsAsArray(), true));
        self::assertCount(1, $secondImport->getGeneratedStatements());
        self::assertSame(
            self::SOURCE_ID_2,
            $secondImport->getGeneratedStatements()[0]->getSourceStatementId()
        );
        self::assertSame(['M1' => 1], $secondImport->getSkippedStatements());
    }

    public function testKeepsInternIdOnFirstStatementOnlyWhenCopiesShareIt(): void
    {
        // Copies report the intern id of the statement they were copied from, but it has to stay
        // unique per procedure.
        $workbook = $this->createWorkbook(
            ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz', 'Eingangsnummer', 'Referenz'],
            [
                ['M1', 'first chunk', self::CATEGORY, null, null, 'E-123', self::SOURCE_ID_1],
                ['M1', 'second chunk', self::CATEGORY, null, null, 'E-123', self::SOURCE_ID_2],
            ]
        );

        $sut = $this->createImporter();
        $sut->process($workbook);

        self::assertFalse($sut->hasErrors(), var_export($sut->getErrorsAsArray(), true));
        self::assertCount(2, $sut->getGeneratedStatements());

        $internIds = array_map(
            static fn (Statement $statement): ?string => $statement->getOriginal()?->getInternId(false),
            $sut->getGeneratedStatements()
        );
        sort($internIds);
        self::assertSame([null, 'E-123'], $internIds);
    }

    public function testSkipsDuplicateExternIdsWhenReferenceColumnIsAbsent(): void
    {
        // Legacy exports carry no reference column - the extern id stays the only identity there.
        $workbook = $this->createWorkbook(
            ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz'],
            [
                ['M1', 'first chunk', self::CATEGORY, null, null],
                ['M1', 'second chunk', self::CATEGORY, null, null],
            ]
        );

        $sut = $this->createImporter();
        $sut->process($workbook);

        self::assertCount(1, $sut->getGeneratedStatements());
        self::assertSame(['M1' => 1], $sut->getSkippedStatements());
        self::assertNull($sut->getGeneratedStatements()[0]->getSourceStatementId());
    }

    public function testSkipsDuplicateExternIdsWhenReferenceColumnIsEmpty(): void
    {
        // An empty reference column falls back to the extern id, same as a legacy export.
        $workbook = $this->createWorkbook(
            ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz', 'Referenz'],
            [
                ['M1', 'first chunk', self::CATEGORY, null, null, null],
                ['M1', 'second chunk', self::CATEGORY, null, null, null],
            ]
        );

        $sut = $this->createImporter();
        $sut->process($workbook);

        self::assertCount(1, $sut->getGeneratedStatements());
        self::assertSame(['M1' => 1], $sut->getSkippedStatements());
        self::assertNull($sut->getGeneratedStatements()[0]->getSourceStatementId());
    }

    public function testReportsViolationForMalformedSourceReference(): void
    {
        $workbook = $this->createWorkbook(
            ['ID', 'Text', 'Dokumentenkategorie', 'Dokument', 'Absatz', 'Referenz'],
            [['M1', 'some text', self::CATEGORY, null, null, 'not-a-uuid']]
        );

        $sut = $this->createImporter();
        $sut->process($workbook);

        self::assertTrue($sut->hasErrors());
    }

    private function createImporter(): StatementSpreadsheetImporter
    {
        return new StatementSpreadsheetImporter(
            self::getContainer()->get(CurrentProcedureService::class),
            self::getContainer()->get(CurrentUserService::class),
            self::getContainer()->get(ElementsService::class),
            self::getContainer()->get(OrgaService::class),
            self::getContainer()->get(StatementCopier::class),
            self::getContainer()->get(StatementService::class),
            self::getContainer()->get(TranslatorInterface::class),
            self::getContainer()->get(ValidatorInterface::class),
            self::getContainer()->get(EntityManagerInterface::class)
        );
    }

    /**
     * @param list<string>            $header
     * @param list<list<string|null>> $rows
     */
    private function createWorkbook(array $header, array $rows): SplFileInfo
    {
        $spreadsheet = new Spreadsheet();
        $worksheet = $spreadsheet->getActiveSheet();
        $worksheet->fromArray([$header, ...$rows], null, 'A1', true);

        $path = tempnam(sys_get_temp_dir(), 'source_reference_import_').'.xlsx';
        $this->temporaryFiles[] = $path;
        (new Xlsx($spreadsheet))->save($path);
        $spreadsheet->disconnectWorksheets();

        return new SplFileInfo($path, '', basename($path));
    }
}
