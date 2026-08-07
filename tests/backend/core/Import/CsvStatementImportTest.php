<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Import;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CsvStatementImport;
use demosplan\DemosPlanCoreBundle\ValueObject\FileInfo;
use Tests\Base\FunctionalTestCase;

class CsvStatementImportTest extends FunctionalTestCase
{
    protected ?CsvStatementImport $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(CsvStatementImport::class);
    }

    public function testValidFileIsPersisted(): void
    {
        $this->setProcedureAndLogin();
        $statementsBefore = $this->countEntries(Statement::class);
        $metasBefore = $this->countEntries(StatementMeta::class);

        $result = $this->sut->importFromFile($this->fileInfo('valid.csv'));

        self::assertFalse($result->hasErrors());
        self::assertSame(6, $result->getStatementCount());
        // every imported statement is stored alongside the original it was copied from
        self::assertSame($statementsBefore + 12, $this->countEntries(Statement::class));
        self::assertSame($metasBefore + 12, $this->countEntries(StatementMeta::class));

        foreach ($result->getStatements() as $statement) {
            self::assertNotNull($statement->getId());
        }
    }

    public function testFileWithViolationsPersistsNothing(): void
    {
        $this->setProcedureAndLogin();
        $statementsBefore = $this->countEntries(Statement::class);

        $result = $this->sut->importFromFile($this->fileInfo('invalid_date.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
        self::assertSame($statementsBefore, $this->countEntries(Statement::class));
    }

    public function testDuplicateInternIdAgainstExistingStatementPersistsNothingAndDoesNotThrow(): void
    {
        // this reproduces the original bug: a duplicate Eingangsnummer used to reach flush()
        // unvalidated and surface as a raw UniqueConstraintViolationException
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        StatementFactory::createOne([
            'procedure' => $procedure,
            'internId'  => 'DUP-EXISTING',
        ]);

        $this->setProcedureAndLogin();
        $statementsBefore = $this->countEntries(Statement::class);

        $result = $this->sut->importFromFile($this->fileInfo('duplicate_internid_in_db.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame(0, $result->getStatementCount());
        self::assertSame($statementsBefore, $this->countEntries(Statement::class));
    }

    public function testStructurallyBrokenFilePersistsNothing(): void
    {
        $this->setProcedureAndLogin();
        $statementsBefore = $this->countEntries(Statement::class);

        $result = $this->sut->importFromFile($this->fileInfo('missing_column.csv'));

        self::assertTrue($result->hasErrors());
        self::assertSame($statementsBefore, $this->countEntries(Statement::class));
    }

    private function setProcedureAndLogin(): void
    {
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    private function fileInfo(string $filename): FileInfo
    {
        $path = __DIR__.'/res/csv_statement_import/'.$filename;

        if (!file_exists($path)) {
            self::fail("Test file not found: {$path}");
        }

        return new FileInfo(
            hash: 'csv-statement-import-test',
            fileName: $filename,
            fileSize: (int) filesize($path),
            contentType: 'text/csv',
            path: dirname($path),
            absolutePath: $path,
            procedure: null
        );
    }
}
