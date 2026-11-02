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
use InvalidArgumentException;

/**
 * Computes when an {@see ExportSchedule} is next due, from its frequency/weekday/day-of-month.
 */
class ExportScheduleRunCalculator
{
    /**
     * Strictly after the day of $from, never the day itself. The dispatcher only runs once a night
     * (shortly after midnight), so by the time a schedule is saved or has just been dispatched, today's
     * slot has already passed: a due date of "today" would only be picked up the following night, on a
     * day the user did not choose. This also matches the "next export" date the frontend shows.
     */
    public function nextRunAfter(ExportSchedule $schedule, DateTime $from): DateTime
    {
        $from = $this->atMidnight($from);

        return match ($schedule->getFrequency()) {
            ExportSchedule::FREQUENCY_DAILY   => (clone $from)->modify('+1 day'),
            ExportSchedule::FREQUENCY_WEEKLY  => $this->nextWeekday($from, $schedule->getWeekday()),
            ExportSchedule::FREQUENCY_MONTHLY => $this->nextDayOfMonth($from, $schedule->getDayOfMonth()),
            default                           => throw new InvalidArgumentException("Unknown export schedule frequency: {$schedule->getFrequency()}"),
        };
    }

    /**
     * Schedules carry a day, never a time of day, so every date this class produces or compares
     * against is normalised to midnight - otherwise a $from carrying the current time of day would
     * make the weekly and monthly branches disagree with the daily one about what "today" means.
     */
    private function atMidnight(DateTime $from): DateTime
    {
        $normalized = clone $from;
        $normalized->setTime(0, 0);

        return $normalized;
    }

    private function nextWeekday(DateTime $from, ?int $weekday): DateTime
    {
        if (null === $weekday) {
            throw new InvalidArgumentException('A weekly export schedule requires a weekday.');
        }

        $currentWeekday = (int) $from->format('N');
        $daysUntilNext = ($weekday - $currentWeekday + 7) % 7;
        if (0 === $daysUntilNext) {
            $daysUntilNext = 7;
        }

        return (clone $from)->modify("+{$daysUntilNext} days");
    }

    /**
     * Safe to build the date directly (no month-end overflow, e.g. no "31 February") because
     * $dayOfMonth is restricted by the frontend to at most 25, which exists in every month.
     */
    private function nextDayOfMonth(DateTime $from, ?int $dayOfMonth): DateTime
    {
        if (null === $dayOfMonth) {
            throw new InvalidArgumentException('A monthly export schedule requires a day of month.');
        }

        $candidate = clone $from;
        $candidate->setDate((int) $from->format('Y'), (int) $from->format('n'), $dayOfMonth);

        if ($candidate <= $from) {
            $candidate->modify('+1 month');
        }

        return $candidate;
    }
}
