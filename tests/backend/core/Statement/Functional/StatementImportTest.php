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
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\EventDispatcher\EventDispatcherPostInterface;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\AbstractStatementSpreadsheetImporter;
use demosplan\DemosPlanCoreBundle\Logic\Import\Statement\StatementSpreadsheetImporter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\XlsxStatementImport;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class StatementImportTest extends FunctionalTestCase
{
    /**
     * @var XlsxStatementImport;
     */
    protected $sut;
    /**
     * @var FileService
     */
    private $fileService;

    public function setUp(): void
    {
        parent::setUp();

        // StatementSpreadsheetImporter is passed as parameter as AbstractStatementSpreadsheetImporter throws an error when executing the test
        $this->sut = new XlsxStatementImport(
            $this->createMock(EventDispatcherPostInterface::class),
            $this->createMock(StatementSpreadsheetImporter::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(StatementRepository::class),
            $this->createMock(StatementService::class),
            $this->createMock(EntityManagerInterface::class)
        );
        $this->fileService = self::getContainer()->get(FileService::class);
    }

    public function testGenerateStatementsFromExcel(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->setProcedureAndLogin();
        $countBefore = $this->countEntries(Statement::class);
        $testFile = $this->getFileReference('statements_as_xlsx');
        $fileHash = $testFile->getHash();
        $file = $this->fileService->getFileInfo($fileHash);
        $fileInfo = new \Symfony\Component\Finder\SplFileInfo(
            $file->getAbsolutePath(),
            '',
            $file->getHash()
        );
        $this->sut->importFromFile($fileInfo);

        static::assertFalse($this->sut->hasErrors());
        static::assertCount(4, $this->sut->getCreatedStatements());
        $generatedStatementsAfter = $this->getEntries(Statement::class);
        // expect 8 new statement entries, because of the created original statements
        static::assertCount($countBefore + (4 * 2), $generatedStatementsAfter);
    }

    private function setProcedureAndLogin(): void
    {
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::getContainer()->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE));
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    public function testReportEntriesOnImportNewStatements(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->setProcedureAndLogin();
        $countBefore = $this->countEntries(ReportEntry::class);
        $testFile = $this->getFileReference('statements_as_xlsx');
        $fileHash = $testFile->getHash();
        $file = $this->fileService->getFileInfo($fileHash);
        $fileInfo = new \Symfony\Component\Finder\SplFileInfo(
            $file->getAbsolutePath(),
            '',
            $file->getHash()
        );
        $this->sut->importFromFile($fileInfo);

        static::assertFalse($this->sut->hasErrors());
        static::assertCount(4, $this->sut->getCreatedStatements());
        $generatedReportsAfter = $this->getEntries(ReportEntry::class);
        static::assertCount($countBefore + 4, $generatedReportsAfter);
    }

    public function testRollbackOnError(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $this->setProcedureAndLogin();
        $countBefore = $this->countEntries(Statement::class);
        $testFile = $this->getFileReference('statements_as_xlsx_including_an_error');
        $fileHash = $testFile->getHash();
        $file = $this->fileService->getFileInfo($fileHash);
        $fileInfo = new \Symfony\Component\Finder\SplFileInfo(
            $file->getAbsolutePath(),
            '',
            $file->getHash()
        );
        $this->sut->importFromFile($fileInfo);

        static::assertTrue($this->sut->hasErrors());
        static::assertCount(8, $this->sut->getErrorsAsArray());
        $generatedStatementsAfter = $this->getEntries(Statement::class);
        static::assertCount($countBefore + 0, $generatedStatementsAfter);
    }

    // Test if the statements in the provided .xlsx file have an internID that already exists in the database. If they do, avoid importing them and ensure no error is generated.
    public function testSkipDuplicateInternalIdStatements(): void
    {
        $statementSpreadsheetImporter = new StatementSpreadsheetImporter(
            $this->getContainer()->get(CurrentProcedureService::class),
            $this->getContainer()->get(CurrentUserService::class),
            $this->getContainer()->get(ElementsService::class),
            $this->getContainer()->get(OrgaService::class),
            $this->getContainer()->get(StatementCopier::class),
            $this->getContainer()->get(StatementService::class),
            $this->getContainer()->get(TranslatorInterface::class),
            $this->getContainer()->get(ValidatorInterface::class),
            $this->getContainer()->get(EntityManagerInterface::class)
        );
        $this->setProcedureAndLogin();

        $countStatementsBeforeImport = $this->countEntries(Statement::class);

        // This .xlsx file contains 2 statements that have internId ("Eingangsnummer") existing already in DB.
        $splFileInfo = new SplFileInfo(
            $this->getFile('backend/core/Statement/Functional/res', 'dde7f809d7eea51123456799cae3a3bb_intern_id.xlsx', 'xlsx', null)->getAbsolutePath(),
            '',
            $this->getFile('backend/core/Statement/Functional/res', 'dde7f809d7eea51123456799cae3a3bb_intern_id.xlsx', 'xlsx', null)->getHash()
        );

        $statementSpreadsheetImporter->process($splFileInfo);

        static::assertFalse($this->sut->hasErrors());
        static::assertCount(0, $this->sut->getCreatedStatements());
        $generatedStatementsAfterImport = $this->getEntries(Statement::class);

        // Expect same amount of statements as none were imported
        static::assertCount($countStatementsBeforeImport, $generatedStatementsAfterImport);
    }
}
