<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureExportJob;
use demosplan\DemosPlanCoreBundle\Entity\Statement\AssessmentTableExportJob;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobMaintenance;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;

class ExportJobMaintenanceTest extends FunctionalTestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        // Built directly rather than fetched from the container: with a single consumer the service
        // is inlined and therefore absent from the test service locator.
        $this->sut = new ExportJobMaintenance(
            $this->getEntityManager(),
            $this->getContainer()->get(FileService::class),
            $this->getContainer()->get(LoggerInterface::class),
            $this->getContainer()->get(TranslatorInterface::class)
        );
    }

    public function testFailStaleJobsClosesOutAbandonedJobs(): void
    {
        // Arrange - a worker killed mid-export leaves the row in 'processing' forever
        $stale = $this->persistJob(AssessmentTableExportJob::STATUS_PROCESSING, '-2 days');

        // Act
        $this->sut->failStaleJobs();

        // Assert - the browser must be told it failed instead of polling indefinitely
        self::assertSame(AssessmentTableExportJob::STATUS_FAILED, $stale->getStatus());
        self::assertNotNull($stale->getErrorMessage());
        self::assertNotSame('', $stale->getErrorMessage());
    }

    public function testFailStaleJobsLeavesRunningJobsAlone(): void
    {
        // Arrange - a large export is expected to take a while
        $running = $this->persistJob(AssessmentTableExportJob::STATUS_PROCESSING, '-1 minute');
        $pending = $this->persistJob(AssessmentTableExportJob::STATUS_PENDING, '-1 minute');

        // Act
        $this->sut->failStaleJobs();

        // Assert
        self::assertSame(AssessmentTableExportJob::STATUS_PROCESSING, $running->getStatus());
        self::assertSame(AssessmentTableExportJob::STATUS_PENDING, $pending->getStatus());
    }

    public function testFailStaleJobsCoversProcedureExportJobsToo(): void
    {
        // Arrange
        $stale = new ProcedureExportJob();
        $stale->setUserId('user-1');
        $stale->setParametersHash(str_pad('h', 64, 'h'));
        $stale->setStatus(ProcedureExportJob::STATUS_PROCESSING);
        $stale->setModifiedDate(new DateTime('-2 days'));
        $this->getEntityManager()->persist($stale);
        $this->getEntityManager()->flush();

        // Act
        $this->sut->failStaleJobs();

        // Assert
        self::assertSame(ProcedureExportJob::STATUS_FAILED, $stale->getStatus());
    }

    public function testPurgeExpiredResultsRemovesFinishedJobsPastRetention(): void
    {
        // Arrange - exported documents contain personal data and must not be kept indefinitely
        $expired = $this->persistJob(AssessmentTableExportJob::STATUS_COMPLETED, '-30 days');
        $expiredId = $expired->getId();

        // Act
        $this->sut->purgeExpiredResults();

        // Assert
        self::assertNull(
            $this->getEntityManager()->find(AssessmentTableExportJob::class, $expiredId)
        );
    }

    public function testPurgeExpiredResultsKeepsRecentResultsDownloadable(): void
    {
        // Arrange - inside the retention window, so it must survive. Deliberately older than a day:
        // it pins the window rather than passing for any value of RESULT_RETENTION.
        $recent = $this->persistJob(AssessmentTableExportJob::STATUS_COMPLETED, '-5 days');
        $recentId = $recent->getId();

        // Act
        $this->sut->purgeExpiredResults();

        // Assert
        self::assertInstanceOf(
            AssessmentTableExportJob::class,
            $this->getEntityManager()->find(AssessmentTableExportJob::class, $recentId)
        );
    }

    private function persistJob(string $status, string $modifiedDate): AssessmentTableExportJob
    {
        $job = new AssessmentTableExportJob();
        $job->setProcedureId('11111111-1111-1111-1111-111111111111');
        $job->setUserId('user-1');
        $job->setParametersHash(str_pad('h', 64, 'h'));
        $job->setStatus($status);
        $job->setModifiedDate(new DateTime($modifiedDate));

        $this->getEntityManager()->persist($job);
        $this->getEntityManager()->flush();

        return $job;
    }
}
