<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\MessageHandler;

use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureExportJob;
use demosplan\DemosPlanCoreBundle\Exception\DemosException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobStatusWriter;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Message\ExportProcedureMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\ExportProcedureMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class ExportProcedureMessageHandlerTest extends UnitTestCase
{
    /** @var ExportProcedureMessageHandler */
    protected $sut;

    // Mock-suffixed to avoid colliding with the untyped properties declared on the base test case.
    private ?EntityManagerInterface $entityManagerMock = null;
    private ?ExportJobContextRestorer $contextRestorerMock = null;
    private ?ExportJobStatusWriter $statusWriterMock = null;
    private ?ExportService $exportServiceMock = null;
    private ?FileService $fileServiceMock = null;
    private ?LoggerInterface $loggerMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->contextRestorerMock = $this->createMock(ExportJobContextRestorer::class);
        $this->statusWriterMock = $this->createMock(ExportJobStatusWriter::class);
        $this->exportServiceMock = $this->createMock(ExportService::class);
        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'translated:'.$key);

        $this->sut = new ExportProcedureMessageHandler(
            $this->entityManagerMock,
            $this->contextRestorerMock,
            new ExportJobFailureReason($translator),
            $this->statusWriterMock,
            new ExportResponseFileStore($this->fileServiceMock),
            $this->exportServiceMock,
            $this->loggerMock,
            $this->createMock(RequestStack::class),
            $translator
        );
    }

    public function testInvokeLogsErrorAndStopsWhenJobNotFound(): void
    {
        // Arrange - the job row is gone, so nothing should be exported
        $this->entityManagerMock->method('find')->willReturn(null);
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Procedure export job not found', ['jobId' => 'missing-job']);
        $this->exportServiceMock->expects($this->never())->method('generateProcedureExportZip');

        // Act
        ($this->sut)(new ExportProcedureMessage('missing-job', ['p1'], 'u1', 'c1'));
    }

    public function testInvokeRestoresUserAndCustomerContextBeforeExporting(): void
    {
        // Arrange
        $job = new ProcedureExportJob();
        $this->mockFindReturning($job);

        // The permission set of the resulting export depends on this, so it must happen for the
        // acting user and their customer - not the worker's default subdomain.
        $this->contextRestorerMock->expects($this->once())
            ->method('restore')
            ->with('u1', 'c1');

        $this->exportServiceMock->method('generateProcedureExportZip')
            ->willReturn($this->streamedZipResponse());
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1'], 'u1', 'c1'));
    }

    public function testInvokeMarksJobFailedWithGenericReasonWhenContextCannotBeRestored(): void
    {
        // Arrange - the acting user cannot be resolved; the raw message must not reach the job row
        $job = new ProcedureExportJob();
        $this->mockFindReturning($job);
        $this->contextRestorerMock->method('restore')
            ->willThrowException(new RuntimeException('Export job user not found: missing-user'));
        $this->exportServiceMock->expects($this->never())->method('generateProcedureExportZip');

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1'], 'missing-user', 'c1'));

        // Assert
        self::assertSame(ProcedureExportJob::STATUS_FAILED, $job->getStatus());
        self::assertSame('translated:error.export', $job->getErrorMessage());
        self::assertStringNotContainsString('missing-user', (string) $job->getErrorMessage());
    }

    public function testInvokeStoresTranslatedUserMessageOfDemosException(): void
    {
        // Arrange - DemosException carries a translation key for the user, and the log detail apart
        $job = new ProcedureExportJob();
        $this->mockFindReturning($job);
        $this->exportServiceMock->method('generateProcedureExportZip')
            ->willThrowException(new DemosException('error.statements.zip.export', 'technical detail'));

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1'], 'u1', 'c1'));

        // Assert
        self::assertSame(ProcedureExportJob::STATUS_FAILED, $job->getStatus());
        self::assertSame('translated:error.statements.zip.export', $job->getErrorMessage());
    }

    public function testInvokeWritesJobStatusThroughStatusWriterEvenWhenExportFails(): void
    {
        // Arrange - a Doctrine failure closes the EntityManager, so the outcome must not be written
        // with a plain flush or the job would stay 'processing' forever
        $job = new ProcedureExportJob();
        $this->mockFindReturning($job);
        $this->exportServiceMock->method('generateProcedureExportZip')
            ->willThrowException(new RuntimeException('deadlock'));
        $this->statusWriterMock->expects($this->once())->method('persist')->with($job);

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1'], 'u1', 'c1'));
    }

    public function testInvokeStoresFileAndCompletesJobOnSuccess(): void
    {
        // Arrange
        $job = new ProcedureExportJob();
        $this->mockFindReturning($job);

        // The exporter is reused unchanged; return a streamed ZIP with a download name.
        $this->exportServiceMock->expects($this->once())
            ->method('generateProcedureExportZip')
            ->with(['p1', 'p2'], false)
            ->willReturn($this->streamedZipResponse());

        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'Verfahrensexport.zip', 'u1', null, FileService::VIRUSCHECK_NONE)
            ->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1', 'p2'], 'u1', 'c1'));

        // Assert
        self::assertSame(ProcedureExportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame('hash-1', $job->getFileHash());
        self::assertSame('Verfahrensexport.zip', $job->getFileName());
    }

    public function testInvokeNamesFileFromTranslationWhenResponseDeclaresNoFilename(): void
    {
        // Arrange - a ZIP response without Content-Disposition must still yield the translated
        // archive name, not a hardcoded literal that diverges per project or locale.
        $job = new ProcedureExportJob();
        $this->mockFindReturning($job);
        $this->exportServiceMock->method('generateProcedureExportZip')->willReturn(
            new StreamedResponse(static function (): void {
                echo 'zip-bytes';
            })
        );

        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with(
                $this->isType('string'),
                'translated:procedure.export_filename.zip',
                'u1',
                null,
                FileService::VIRUSCHECK_NONE
            )
            ->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1'], 'u1', 'c1'));

        // Assert
        self::assertSame('translated:procedure.export_filename.zip', $job->getFileName());
    }

    private function mockFindReturning(ProcedureExportJob $job): void
    {
        $this->entityManagerMock->method('find')->willReturnCallback(
            static fn (string $class) => ProcedureExportJob::class === $class ? $job : null
        );
    }

    private function streamedZipResponse(): StreamedResponse
    {
        $response = new StreamedResponse(static function (): void {
            echo 'zip-bytes';
        });
        $response->headers->set('Content-Disposition', "attachment; filename*=UTF-8''Verfahrensexport.zip");

        return $response;
    }

    private function fileWithHash(string $hash): File
    {
        $file = $this->createMock(File::class);
        $file->method('getHash')->willReturn($hash);

        return $file;
    }
}
