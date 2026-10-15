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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobContextRestorer;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFailureReason;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobStatusWriter;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportResponseFileStore;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentsExportResponseBuilder;
use demosplan\DemosPlanCoreBundle\Message\ExportSegmentsMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\ExportSegmentsMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\UnitTestCase;

class ExportSegmentsMessageHandlerTest extends UnitTestCase
{
    /** @var ExportSegmentsMessageHandler */
    protected $sut;

    // Mock-suffixed to avoid colliding with the untyped properties declared on the base test case.
    private ?EntityManagerInterface $entityManagerMock = null;
    private ?ExportJobContextRestorer $contextRestorerMock = null;
    private ?ExportJobStatusWriter $statusWriterMock = null;
    private ?FileService $fileServiceMock = null;
    private ?LoggerInterface $loggerMock = null;
    private ?ProcedureHandler $procedureHandlerMock = null;
    private ?SegmentsExportResponseBuilder $responseBuilderMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->contextRestorerMock = $this->createMock(ExportJobContextRestorer::class);
        $this->statusWriterMock = $this->createMock(ExportJobStatusWriter::class);
        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->procedureHandlerMock = $this->createMock(ProcedureHandler::class);
        $this->procedureHandlerMock->method('getProcedureWithCertainty')->willReturn($this->createMock(Procedure::class));
        $this->responseBuilderMock = $this->createMock(SegmentsExportResponseBuilder::class);

        $translator = $this->createMock(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $key): string => 'translated:'.$key);

        $this->sut = new ExportSegmentsMessageHandler(
            $this->entityManagerMock,
            $this->contextRestorerMock,
            new ExportJobFailureReason($translator),
            $this->statusWriterMock,
            new ExportResponseFileStore($this->fileServiceMock),
            $this->loggerMock,
            $this->procedureHandlerMock,
            $this->responseBuilderMock
        );
    }

    public function testInvokeLogsErrorAndStopsWhenJobNotFound(): void
    {
        // Arrange - the job row is gone, so nothing should be exported
        $this->entityManagerMock->method('find')->willReturn(null);
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Segments export job not found', ['jobId' => 'missing-job']);
        $this->responseBuilderMock->expects($this->never())->method('build');

        // Act
        ($this->sut)(new ExportSegmentsMessage('missing-job', SegmentsExportResponseBuilder::TYPE_DOCX, 'proc-1', 'u1', 'c1', []));
    }

    public function testInvokeRestoresProcedureScopedContextBeforeExporting(): void
    {
        // Arrange - procedure-scoped permissions decide which columns the export contains, so the
        // procedure must be part of the restored context
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->contextRestorerMock->expects($this->once())
            ->method('restore')
            ->with('u1', 'c1', 'proc-1');

        $this->responseBuilderMock->method('build')->willReturn($this->streamedResponse());
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportSegmentsMessage('job-1', SegmentsExportResponseBuilder::TYPE_DOCX, 'proc-1', 'u1', 'c1', []));
    }

    public function testInvokePassesExportTypeAndQueryParamsToBuilder(): void
    {
        // Arrange
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $queryParams = ['filter' => ['procedureId' => ['condition' => ['value' => 'proc-1']]], 'isObscured' => true];
        $this->responseBuilderMock->expects($this->once())
            ->method('build')
            ->with(
                self::isInstanceOf(Procedure::class),
                self::callback(static fn (ParameterBag $query): bool => $queryParams === $query->all()),
                SegmentsExportResponseBuilder::TYPE_ZIP
            )
            ->willReturn($this->streamedResponse());
        $this->fileServiceMock->method('saveTemporaryFile')->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportSegmentsMessage('job-1', SegmentsExportResponseBuilder::TYPE_ZIP, 'proc-1', 'u1', 'c1', $queryParams));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_COMPLETED, $job->getStatus());
    }

    public function testInvokeMarksJobFailedWithGenericReasonWhenContextCannotBeRestored(): void
    {
        // Arrange - the acting user cannot be resolved; the raw message must not reach the job row
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->contextRestorerMock->method('restore')
            ->willThrowException(new RuntimeException('Export job user not found: missing-user'));
        $this->responseBuilderMock->expects($this->never())->method('build');

        // Act
        ($this->sut)(new ExportSegmentsMessage('job-1', SegmentsExportResponseBuilder::TYPE_DOCX, 'proc-1', 'missing-user', 'c1', []));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_FAILED, $job->getStatus());
        self::assertSame('translated:error.export', $job->getErrorMessage());
        self::assertStringNotContainsString('missing-user', (string) $job->getErrorMessage());
    }

    public function testInvokeWritesJobStatusThroughStatusWriterEvenWhenExportFails(): void
    {
        // Arrange - a Doctrine failure closes the EntityManager, so the outcome must not be written
        // with a plain flush or the job would stay 'processing' forever
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->responseBuilderMock->method('build')->willThrowException(new RuntimeException('deadlock'));
        $this->statusWriterMock->expects($this->once())->method('persist')->with($job);

        // Act
        ($this->sut)(new ExportSegmentsMessage('job-1', SegmentsExportResponseBuilder::TYPE_DOCX, 'proc-1', 'u1', 'c1', []));
    }

    public function testInvokeStoresFileAndCompletesJobOnSuccess(): void
    {
        // Arrange
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->responseBuilderMock->expects($this->once())
            ->method('build')
            ->willReturn($this->streamedResponse());
        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'export.docx', 'u1', 'proc-1', FileService::VIRUSCHECK_NONE)
            ->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportSegmentsMessage('job-1', SegmentsExportResponseBuilder::TYPE_DOCX, 'proc-1', 'u1', 'c1', []));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame('hash-1', $job->getFileHash());
        self::assertSame('export.docx', $job->getFileName());
    }

    public function testInvokeFallsBackToDefaultNameWhenResponseCarriesNoDisposition(): void
    {
        // Arrange - the stored name must still carry the extension, or the browser hands the user a
        // file it cannot open
        $job = new AssessmentTableExportJob();
        $this->mockFindReturning($job);
        $this->responseBuilderMock->method('build')->willReturn(new StreamedResponse(static function (): void {
            echo 'zip-bytes';
        }));
        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'Synopse.zip', 'u1', 'proc-1', FileService::VIRUSCHECK_NONE)
            ->willReturn($this->fileWithHash('hash-1'));

        // Act
        ($this->sut)(new ExportSegmentsMessage('job-1', SegmentsExportResponseBuilder::TYPE_ZIP, 'proc-1', 'u1', 'c1', []));

        // Assert
        self::assertSame('Synopse.zip', $job->getFileName());
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
            echo 'docx-bytes';
        });
        $response->headers->set('Content-Disposition', 'attachment; filename="export.docx"');

        return $response;
    }

    private function fileWithHash(string $hash): File
    {
        $file = $this->createMock(File::class);
        $file->method('getHash')->willReturn($hash);

        return $file;
    }
}
