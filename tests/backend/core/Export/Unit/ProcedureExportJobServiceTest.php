<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobDownloadResponseFactory;
use demosplan\DemosPlanCoreBundle\Logic\Export\ProcedureExportJobService;
use demosplan\DemosPlanCoreBundle\Logic\Export\RunningExportJobLookup;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcedureExportJobServiceTest extends TestCase
{
    private ?ProcedureExportJobService $sut = null;
    private ExportJobDownloadResponseFactory&MockObject $downloadResponseFactory;
    private EntityManagerInterface&MockObject $entityManager;
    private MessageBusInterface&MockObject $messageBus;
    private RunningExportJobLookup&MockObject $runningExportJobLookup;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->messageBus = $this->createMock(MessageBusInterface::class);
        $this->runningExportJobLookup = $this->createMock(RunningExportJobLookup::class);

        $this->downloadResponseFactory = $this->createMock(ExportJobDownloadResponseFactory::class);

        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn('u1');
        $currentUserService = $this->createMock(CurrentUserService::class);
        $currentUserService->method('getUser')->willReturn($user);

        $this->sut = new ProcedureExportJobService(
            $currentUserService,
            $this->downloadResponseFactory,
            $this->entityManager,
            $this->messageBus,
            $this->runningExportJobLookup
        );
    }

    public function testStartReturnsRunningJobInsteadOfQueueingDuplicate(): void
    {
        $running = new AssessmentTableExportJob();
        (new ReflectionProperty($running, 'id'))->setValue($running, 'running-job-id');
        $this->runningExportJobLookup->method('find')
            ->with(
                AssessmentTableExportJob::class,
                ['userId' => 'u1', 'procedureId' => 'proc-1', 'parametersHash' => 'hash'],
                [AssessmentTableExportJob::STATUS_PENDING, AssessmentTableExportJob::STATUS_PROCESSING]
            )
            ->willReturn($running);
        $this->entityManager->expects(self::never())->method('persist');
        $this->messageBus->expects(self::never())->method('dispatch');

        $jobId = $this->sut->start('u1', 'proc-1', 'hash', static fn (): stdClass => new stdClass());

        self::assertSame('running-job-id', $jobId);
    }

    public function testStartPersistsJobAndDispatchesMessageForIt(): void
    {
        $this->runningExportJobLookup->method('find')->willReturn(null);

        // A real EntityManager assigns the id via the custom ID generator inside persist()
        $persistedJob = null;
        $this->entityManager->expects(self::once())
            ->method('persist')
            ->willReturnCallback(static function (AssessmentTableExportJob $job) use (&$persistedJob): void {
                (new ReflectionProperty($job, 'id'))->setValue($job, 'generated-job-id');
                $persistedJob = $job;
            });
        $message = new stdClass();
        $this->messageBus->expects(self::once())
            ->method('dispatch')
            ->with($message)
            ->willReturn(new Envelope($message));

        $jobId = $this->sut->start('u1', 'proc-1', 'hash', static function (string $jobId) use ($message): stdClass {
            $message->jobId = $jobId;

            return $message;
        });

        self::assertSame('generated-job-id', $jobId);
        self::assertSame('generated-job-id', $message->jobId);
        self::assertSame('u1', $persistedJob->getUserId());
        self::assertSame('proc-1', $persistedJob->getProcedureId());
        self::assertSame('hash', $persistedJob->getParametersHash());
    }

    public function testCreateStatusResponseReturnsNotFoundForForeignJob(): void
    {
        $this->entityManager->method('find')->willReturn($this->job('someone-else', 'proc-1'));

        $response = $this->sut->createStatusResponse('proc-1', 'job-1');

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testCreateStatusResponseReturnsStatusForOwnJob(): void
    {
        $job = $this->job('u1', 'proc-1');
        $job->setStatus(AssessmentTableExportJob::STATUS_PROCESSING);
        $this->entityManager->method('find')->willReturn($job);

        $response = $this->sut->createStatusResponse('proc-1', 'job-1');

        self::assertSame(
            ['status' => AssessmentTableExportJob::STATUS_PROCESSING, 'error' => null],
            json_decode((string) $response->getContent(), true)
        );
    }

    public function testCreateDownloadResponseThrowsNotFoundForJobOfOtherProcedure(): void
    {
        $job = $this->job('u1', 'other-procedure');
        $job->setStatus(AssessmentTableExportJob::STATUS_COMPLETED);
        $this->entityManager->method('find')->willReturn($job);
        $this->downloadResponseFactory->expects(self::never())->method('createForJob');

        $this->expectException(NotFoundHttpException::class);

        $this->sut->createDownloadResponse('proc-1', 'job-1');
    }

    private function job(string $userId, string $procedureId): AssessmentTableExportJob
    {
        $job = new AssessmentTableExportJob();
        $job->setUserId($userId);
        $job->setProcedureId($procedureId);

        return $job;
    }
}
