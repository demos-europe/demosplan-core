<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ScheduledExportJob;
use demosplan\DemosPlanCoreBundle\Message\GenerateScheduledExportMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Finds rows due today and starts one run for each.
 *
 * Runs once a day, driven by the daily maintenance schedule, so "due" is decided per calendar day -
 * schedules carry a day (or weekday/day-of-month), never a time of day.
 */
class ScheduledExportDispatcher
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ExportScheduleRunCalculator $runCalculator,
        private readonly LoggerInterface $logger,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    /**
     * Starts a run for every schedule that is due and has not already run today, then advances each
     * schedule to its next occurrence.
     */
    public function dispatchDueExports(): int
    {
        $today = new DateTime('today');
        $dispatched = 0;

        foreach ($this->findDueSchedules($today) as $schedule) {
            $job = new ScheduledExportJob();
            $job->setUserId($schedule->getUserId());
            $job->setProcedureId($schedule->getProcedureId());
            $job->setScheduleId((string) $schedule->getId());
            $job->setParametersHash($schedule->getParametersHash());
            $this->entityManager->persist($job);
            $this->entityManager->flush();

            $this->messageBus->dispatch(new GenerateScheduledExportMessage((string) $job->getId()));

            $schedule->setLastRunAt($today);
            $schedule->setNextRunAt($this->runCalculator->nextRunAfter($schedule, $today));
            $schedule->setModifiedDate(new DateTime());
            $this->entityManager->flush();

            ++$dispatched;
        }

        if (0 < $dispatched) {
            $this->logger->info('Dispatched scheduled XLSX Synopse exports', ['count' => $dispatched]);
        }

        return $dispatched;
    }

    private function findDueSchedules(DateTime $today): array
    {
        return $this->entityManager->createQueryBuilder()
            ->select('schedule')
            ->from(ExportSchedule::class, 'schedule')
            ->andWhere('schedule.nextRunAt <= :today')
            // Parenthesised deliberately: without the parentheses, AND binds tighter than OR and this
            // would match every schedule with an old lastRunAt, regardless of nextRunAt.
            ->andWhere('(schedule.lastRunAt IS NULL OR schedule.lastRunAt < :today)')
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();
    }
}
