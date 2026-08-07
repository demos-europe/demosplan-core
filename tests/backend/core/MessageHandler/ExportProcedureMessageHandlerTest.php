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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureExportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Message\ExportProcedureMessage;
use demosplan\DemosPlanCoreBundle\MessageHandler\ExportProcedureMessageHandler;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Tests\Base\UnitTestCase;

class ExportProcedureMessageHandlerTest extends UnitTestCase
{
    /** @var ExportProcedureMessageHandler */
    protected $sut;

    // Mock-suffixed to avoid colliding with the untyped properties declared on the base test case.
    private ?EntityManagerInterface $entityManagerMock = null;
    private ?ExportService $exportServiceMock = null;
    private ?FileService $fileServiceMock = null;
    private ?LoggerInterface $loggerMock = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->exportServiceMock = $this->createMock(ExportService::class);
        $this->fileServiceMock = $this->createMock(FileService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->sut = new ExportProcedureMessageHandler(
            $this->createMock(CurrentUserService::class),
            $this->entityManagerMock,
            $this->exportServiceMock,
            $this->fileServiceMock,
            $this->loggerMock,
            $this->createMock(PermissionsInterface::class),
            $this->createMock(RequestStack::class)
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
        ($this->sut)(new ExportProcedureMessage('missing-job', ['p1'], 'u1'));
    }

    public function testInvokeMarksJobFailedWhenUserNotFound(): void
    {
        // Arrange - job exists but its user cannot be resolved
        $job = new ProcedureExportJob();
        $this->entityManagerMock->method('find')->willReturnCallback(
            static fn (string $class) => ProcedureExportJob::class === $class ? $job : null
        );
        $this->exportServiceMock->expects($this->never())->method('generateProcedureExportZip');

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1'], 'missing-user'));

        // Assert
        self::assertSame(ProcedureExportJob::STATUS_FAILED, $job->getStatus());
        self::assertStringContainsString('missing-user', (string) $job->getErrorMessage());
    }

    public function testInvokeStoresFileAndCompletesJobOnSuccess(): void
    {
        // Arrange
        $job = new ProcedureExportJob();
        $user = $this->createMock(User::class);
        $this->entityManagerMock->method('find')->willReturnCallback(
            static fn (string $class) => match ($class) {
                ProcedureExportJob::class => $job,
                User::class               => $user,
                default                   => null,
            }
        );

        // The exporter is reused unchanged; return a streamed ZIP with a download name.
        $response = new StreamedResponse(static function (): void {
            echo 'zip-bytes';
        });
        $response->headers->set('Content-Disposition', "attachment; filename*=UTF-8''Verfahrensexport.zip");
        $this->exportServiceMock->expects($this->once())
            ->method('generateProcedureExportZip')
            ->with(['p1', 'p2'], false)
            ->willReturn($response);

        $file = $this->createMock(File::class);
        $file->method('getHash')->willReturn('hash-1');
        $this->fileServiceMock->expects($this->once())
            ->method('saveTemporaryFile')
            ->with($this->isType('string'), 'Verfahrensexport.zip', 'u1', null, FileService::VIRUSCHECK_NONE)
            ->willReturn($file);

        // Act
        ($this->sut)(new ExportProcedureMessage('job-1', ['p1', 'p2'], 'u1'));

        // Assert
        self::assertSame(ProcedureExportJob::STATUS_COMPLETED, $job->getStatus());
        self::assertSame('hash-1', $job->getFileHash());
        self::assertSame('Verfahrensexport.zip', $job->getFileName());
    }
}
