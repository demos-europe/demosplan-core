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
use InvalidArgumentException;
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
            $schedule->setNextRunAt($this->nextOccurrenceAfter($schedule, $today));
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

    /**
     * Smallest date strictly after $from that matches the schedule's frequency. Self-correcting: if a
     * run was skipped (worker downtime, tick failure), the next occurrence is still computed relative
     * to when it actually ran, not relative to the missed date, so schedules never pile up catch-up runs.
     */
    private function nextOccurrenceAfter(ExportSchedule $schedule, DateTime $from): DateTime
    {
        return match ($schedule->getFrequency()) {
            ExportSchedule::FREQUENCY_DAILY => (clone $from)->modify('+1 day'),
            ExportSchedule::FREQUENCY_WEEKLY => $this->nextWeekdayAfter($from, $schedule->getWeekday()),
            ExportSchedule::FREQUENCY_MONTHLY => $this->nextDayOfMonthAfter($from, $schedule->getDayOfMonth()),
            default => throw new InvalidArgumentException("Unknown export schedule frequency: {$schedule->getFrequency()}"),
        };
    }

    private function nextWeekdayAfter(DateTime $from, int $weekday): DateTime
    {
        $currentWeekday = (int) $from->format('N');
        $daysUntilNext = ($weekday - $currentWeekday + 7) % 7;
        $daysUntilNext = 0 === $daysUntilNext ? 7 : $daysUntilNext;

        return (clone $from)->modify("+{$daysUntilNext} days");
    }

    /**
     * Safe to build the date directly (no month-end overflow, e.g. no "31 February") because
     * $dayOfMonth is restricted by the frontend to at most 25, which exists in every month.
     */
    private function nextDayOfMonthAfter(DateTime $from, int $dayOfMonth): DateTime
    {
        $candidate = clone $from;
        $candidate->setDate((int) $from->format('Y'), (int) $from->format('n'), $dayOfMonth);
        $candidate->setTime(0, 0);

        if ($candidate <= $from) {
            $candidate->modify('+1 month');
        }

        return $candidate;
    }
}
