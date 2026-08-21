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
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Exception\AssessmentTableZipExportException;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobStatusWriter;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableExporterStrategy;
use demosplan\DemosPlanCoreBundle\Message\ExportAssessmentTableMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\ExportAssessmentTableMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class ExportAssessmentTableMessageHandlerTest extends UnitTestCase
{
    /** @var ExportAssessmentTableMessageHandler */
    protected $sut;

    // Mock-suffixed to avoid colliding with the untyped properties declared on the base test case.
    private ?AssessmentTableExporterStrategy $assessmentExporterMock = null;
    private ?EntityManagerInterface $entityManagerMock = null;
    private ?ExportJobContextRestorer $contextRestorerMock = null;
    private ?ExportJobStatusWriter $statusWriterMock = null;
    private ?FileResponseGeneratorStrategy $responseGeneratorMock = null;
    private ?FileService $fileServiceMock = null;
    private ?LoggerInterface $loggerMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assessmentExporterMock = $this->createMock(AssessmentTableExporterStrategy::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->contextRestorerMock = $this->createMock(ExportJobContextRestorer::class);
        $this->statusWriterMock = $this->createMock(ExportJobStatusWriter::class);
        $this->responseGeneratorMock = $this->createMock(FileResponseGeneratorStrategy::class);
        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'translated:'.$key);

        $this->sut = new ExportAssessmentTableMessageHandler(
            $this->assessmentExporterMock,
            $this->entityManagerMock,
            $this->contextRestorerMock,
            new ExportJobFailureReason($translator),
            $this->statusWriterMock,
            new ExportResponseFileStore($this->fileServiceMock),
            $this->responseGeneratorMock,
            $this->loggerMock,
            $this->createMock(RequestStack::class)
        );
    }

    public function testInvokeLogsErrorAndStopsWhenJobNotFound(): void
    {
        // Arrange - the job row is gone, so nothing should be exported
        $this->entityManagerMock->method('find')->willReturn(null);
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Assessment table export job not found', ['jobId' => 'missing-job']);
        $this->assessmentExporterMock->expects($this->never())->method('export');

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('missing-job', 'pdf', [], 'u1', 'proc-1', 'c1'));
    }

    public function testInvokeRestoresProcedureScopedContextBeforeExporting(): void
    {
        // Arrange - procedure-scoped permissions decide whether the export is anonymised and which
        // columns it contains, so the procedure must be part of the restored context
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->contextRestorerMock->expects($this->once())
            ->method('restore')
            ->with('u1', 'c1', 'proc-1');

        $this->responseGeneratorMock->method('__invoke')->willReturn($this->streamedResponse());
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'pdf', [], 'u1', 'proc-1', 'c1'));
    }

    public function testInvokeMarksJobFailedWithGenericReasonWhenContextCannotBeRestored(): void
    {
        // Arrange - the acting user cannot be resolved; the raw message must not reach the job row
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->contextRestorerMock->method('restore')
            ->willThrowException(new RuntimeException('Export job user not found: missing-user'));
        $this->assessmentExporterMock->expects($this->never())->method('export');

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'pdf', [], 'missing-user', 'proc-1', 'c1'));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_FAILED, $job->getStatus());
        self::assertSame('translated:error.export', $job->getErrorMessage());
        self::assertStringNotContainsString('missing-user', (string) $job->getErrorMessage());
    }

    public function testInvokeKeepsTranslatedUserMessageOfZipExportException(): void
    {
        // Arrange - the synchronous export surfaces getUserMsg(); getMessage() is empty here, so
        // using it would leave the user with a failure and no reason
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->assessmentExporterMock->method('export')
            ->willThrowException(new AssessmentTableZipExportException('error', 'error.statements.zip.export'));

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'zip', [], 'u1', 'proc-1', 'c1'));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_FAILED, $job->getStatus());
        self::assertSame('translated:error.statements.zip.export', $job->getErrorMessage());
    }

    public function testInvokeFallsBackToDefaultNameWhenResponseCarriesNoDisposition(): void
    {
        // Arrange - a ZIP built by ZipStream inside the stream callback may reach the worker without
        // a Content-Disposition on the response object; the stored name must still carry the
        // extension, or the browser hands the user a file it cannot open
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->responseGeneratorMock->method('__invoke')->willReturn(new StreamedResponse(static function (): void {
            echo 'zip-bytes';
        }));
        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'Abwaegungstabelle.zip', 'u1', 'proc-1', FileService::VIRUSCHECK_NONE)
            ->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'zip', [], 'u1', 'proc-1', 'c1'));

        // Assert
        self::assertSame('Abwaegungstabelle.zip', $job->getFileName());
    }

    public function testInvokeFallbackNameUsesRequestedExportFormatAsExtension(): void
    {
        // Arrange - the fallback extension must follow the requested format, not a fixed guess
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->responseGeneratorMock->method('__invoke')->willReturn(new StreamedResponse(static function (): void {
            echo 'docx-bytes';
        }));
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'docx', [], 'u1', 'proc-1', 'c1'));

        // Assert
        self::assertSame('Abwaegungstabelle.docx', $job->getFileName());
    }

    public function testInvokeWritesJobStatusThroughStatusWriterEvenWhenExportFails(): void
    {
        // Arrange - a Doctrine failure closes the EntityManager, so the outcome must not be written
        // with a plain flush or the job would stay 'processing' forever
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->assessmentExporterMock->method('export')->willThrowException(new RuntimeException('deadlock'));
        $this->statusWriterMock->expects($this->once())->method('persist')->with($job);

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'pdf', [], 'u1', 'proc-1', 'c1'));
    }

    public function testInvokeStoresFileAndCompletesJobOnSuccess(): void
    {
        // Arrange
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);

        // The exporter and response generator are reused unchanged; return a response with a name.
        $parameters = ['exportType' => 'statementsOnly'];
        $this->assessmentExporterMock->expects($this->once())
            ->method('export')
            ->with('pdf', $parameters)
            ->willReturn([]);
        $this->responseGeneratorMock->expects($this->once())
            ->method('__invoke')
            ->willReturn($this->streamedResponse());

        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'export.pdf', 'u1', 'proc-1', FileService::VIRUSCHECK_NONE)
            ->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'pdf', $parameters, 'u1', 'proc-1', 'c1'));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame('hash-1', $job->getFileHash());
        self::assertSame('export.pdf', $job->getFileName());
    }

    private function mockFindReturning(AssessmentTableExportJob $job): void
    {
        $this->entityManagerMock->method('find')->willReturnCallback(
            static fn (string $class) => AssessmentTableExportJob::class === $class ? $job : null
        );
    }

    private function streamedResponse(): StreamedResponse
    {
        $response = new StreamedResponse(static function (): void {
            echo 'pdf-bytes';
        });
        $response->headers->set('Content-Disposition', 'attachment; filename="export.pdf"');

        return $response;
    }

    private function fileWithHash(string $hash): File
    {
        $file = $this->createMock(File::class);
        $file->method('getHash')->willReturn($hash);

        return $file;
    }
}
