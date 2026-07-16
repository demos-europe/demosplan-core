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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\FileResponseGenerator\FileResponseGeneratorStrategy;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\AssessmentTableExporter\AssessmentTableExporterStrategy;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Message\ExportAssessmentTableMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\ExportAssessmentTableMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Base\UnitTestCase;

class ExportAssessmentTableMessageHandlerTest extends UnitTestCase
{
    /** @var ExportAssessmentTableMessageHandler */
    protected $sut;

    // Mock-suffixed to avoid colliding with the untyped properties declared on the base test case.
    private ?AssessmentTableExporterStrategy $assessmentExporterMock = null;
    private ?EntityManagerInterface $entityManagerMock = null;
    private ?FileResponseGeneratorStrategy $responseGeneratorMock = null;
    private ?FileService $fileServiceMock = null;
    private ?LoggerInterface $loggerMock = null;
    private ?ProcedureService $procedureServiceMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assessmentExporterMock = $this->createMock(AssessmentTableExporterStrategy::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->responseGeneratorMock = $this->createMock(FileResponseGeneratorStrategy::class);
        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->procedureServiceMock = $this->createMock(ProcedureService::class);

        $this->sut = new ExportAssessmentTableMessageHandler(
            $this->assessmentExporterMock,
            $this->createMock(CurrentProcedureService::class),
            $this->createMock(CurrentUserService::class),
            $this->entityManagerMock,
            $this->responseGeneratorMock,
            $this->fileServiceMock,
            $this->loggerMock,
            $this->createMock(PermissionsInterface::class),
            $this->procedureServiceMock,
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
        ($this->sut)(new ExportAssessmentTableMessage('missing-job', 'pdf', [], 'u1', 'proc-1'));
    }

    public function testInvokeMarksJobFailedWhenUserNotFound(): void
    {
        // Arrange - job exists but its user cannot be resolved
        $job = new AssessmentTableExportJob();
        $this->entityManagerMock->method('find')->willReturnCallback(
            static fn (string $class) => AssessmentTableExportJob::class === $class ? $job : null
        );
        $this->assessmentExporterMock->expects($this->never())->method('export');

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'pdf', [], 'missing-user', 'proc-1'));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('missing-user', (string) $job->getErrorMessage());
    }

    public function testInvokeStoresFileAndCompletesJobOnSuccess(): void
    {
        // Arrange
        $job = new AssessmentTableExportJob();
        $user = $this->createMock(User::class);
        $this->entityManagerMock->method('find')->willReturnCallback(
            static fn (string $class) => match ($class) {
                AssessmentTableExportJob::class => $job,
                User::class                      => $user,
                default                          => null,
            }
        );
        $this->procedureServiceMock->method('getProcedure')->willReturn($this->createMock(Procedure::class));

        // The exporter and response generator are reused unchanged; return a response with a name.
        $parameters = ['exportType' => 'statementsOnly'];
        $this->assessmentExporterMock->expects($this->once())
            ->method('export')
            ->with('pdf', $parameters)
            ->willReturn([]);

        $response = new StreamedResponse(static function (): void {
            echo 'pdf-bytes';
        });
        $response->headers->set('Content-Disposition', 'attachment; filename="export.pdf"');
        $this->responseGeneratorMock->expects($this->once())
            ->method('__invoke')
            ->willReturn($response);

        $file = $this->createMock(File::class);
        $file->method('getHash')->willReturn('hash-1');
        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'export.pdf', 'u1', 'proc-1', FileService::VIRUSCHECK_NONE)
            ->willReturn($file);

        // Act
        ($this->sut)(new ExportAssessmentTableMessage('job-1', 'pdf', $parameters, 'u1', 'proc-1'));

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame('hash-1', $job->getFileHash());
        self::assertSame('export.pdf', $job->getFileName());
    }
}
