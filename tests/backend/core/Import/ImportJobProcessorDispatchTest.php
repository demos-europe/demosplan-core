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
use demosplan\DemosPlanCoreBundle\Entity\Import\ImportJob;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Import\ImportJobProcessor;
use demosplan\DemosPlanCoreBundle\Repository\ImportJobRepository;
use Doctrine\ORM\EntityManagerInterface;
use Tests\Base\FunctionalTestCase;

/**
 * Verifies that a queued job reaches the importer its type asks for.
 */
class ImportJobProcessorDispatchTest extends FunctionalTestCase
{
    protected ?ImportJobProcessor $sut = null;

    private ?EntityManagerInterface $entityManager = null;
    private ?FileService $fileService = null;
    private ?ImportJobRepository $importJobRepository = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ImportJobProcessor::class);
        $this->entityManager = self::getContainer()->get(EntityManagerInterface::class);
        $this->fileService = self::getContainer()->get(FileService::class);
        $this->importJobRepository = self::getContainer()->get(ImportJobRepository::class);
    }

    public function testStatementJobImportsTheCsvAndCompletes(): void
    {
        $statementsBefore = $this->countEntries(Statement::class);
        $job = $this->queueJob('valid.csv', ImportJob::TYPE_STATEMENTS);

        $processed = $this->sut->processPendingJobs();

        self::assertSame(1, $processed);
        $job = $this->importJobRepository->find($job->getId());
        self::assertSame(ImportJob::STATUS_COMPLETED, $job->getStatus(), (string) $job->getError());
        self::assertSame(6, $job->getResult()['statements']);
        // a statements-only job must not claim a segment count
        self::assertArrayNotHasKey('segments', $job->getResult());
        self::assertSame($statementsBefore + 12, $this->countEntries(Statement::class));
    }

    public function testStatementJobWithViolationsFailsWithoutImporting(): void
    {
        $statementsBefore = $this->countEntries(Statement::class);
        $job = $this->queueJob('invalid_date.csv', ImportJob::TYPE_STATEMENTS);

        $this->sut->processPendingJobs();

        $job = $this->importJobRepository->find($job->getId());
        self::assertSame(ImportJob::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('Zeile 2', (string) $job->getError());
        // no worksheet is named for a csv, it holds a single table
        self::assertStringNotContainsString('Arbeitsblatt', (string) $job->getError());
        self::assertSame($statementsBefore, $this->countEntries(Statement::class));
    }

    private function queueJob(string $filename, string $importType): ImportJob
    {
        /** @var Procedure $procedure */
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        /** @var User $user */
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // saveTemporaryFile() moves the file it is given, so hand it a copy and keep the fixture
        $temporaryPath = sys_get_temp_dir().'/'.uniqid('csv-import-test-', true).'-'.$filename;
        copy(__DIR__.'/res/csv_statement_import/'.$filename, $temporaryPath);

        $file = $this->fileService->saveTemporaryFile(
            $temporaryPath,
            $filename,
            $user->getId(),
            $procedure->getId(),
            FileService::VIRUSCHECK_NONE
        );

        $job = new ImportJob();
        $job->setProcedure($procedure);
        $job->setUser($user);
        $job->setImportType($importType);
        $job->setFilePath($file->getIdent());
        $job->setFileName($filename);

        $this->entityManager->persist($job);
        $this->entityManager->flush();

        return $job;
    }
}
