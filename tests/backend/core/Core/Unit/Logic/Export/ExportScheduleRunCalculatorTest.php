<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Export;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Statement\ExportSchedule;
use demosplan\DemosPlanCoreBundle\Logic\Export\ExportScheduleRunCalculator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ExportScheduleRunCalculatorTest extends TestCase
{
    private ?ExportScheduleRunCalculator $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new ExportScheduleRunCalculator();
    }

    /**
     * 2026-11-04 is a Wednesday (ISO weekday 3).
     *
     * @return array<string, array{string, ?int, ?int, string, string}>
     */
    public static function nextRunAfterProvider(): array
    {
        return [
            'daily is always tomorrow'                           => [ExportSchedule::FREQUENCY_DAILY, null, null, '2026-11-04 14:00', '2026-11-05'],
            'weekly on a later weekday stays in this week'       => [ExportSchedule::FREQUENCY_WEEKLY, 5, null, '2026-11-04 14:00', '2026-11-06'],
            'weekly on an earlier weekday moves to next week'    => [ExportSchedule::FREQUENCY_WEEKLY, 1, null, '2026-11-04 14:00', '2026-11-09'],
            'weekly on the current weekday moves to next week'   => [ExportSchedule::FREQUENCY_WEEKLY, 3, null, '2026-11-04 14:00', '2026-11-11'],
            'weekly ignores the time of day'                     => [ExportSchedule::FREQUENCY_WEEKLY, 3, null, '2026-11-04 00:30', '2026-11-11'],
            'monthly on a later day stays in this month'         => [ExportSchedule::FREQUENCY_MONTHLY, null, 10, '2026-11-04 14:00', '2026-11-10'],
            'monthly on an earlier day moves to next month'      => [ExportSchedule::FREQUENCY_MONTHLY, null, 1, '2026-11-04 14:00', '2026-12-01'],
            'monthly on the current day moves to next month'     => [ExportSchedule::FREQUENCY_MONTHLY, null, 5, '2026-11-05 14:00', '2026-12-05'],
            'monthly rolls over the year end'                    => [ExportSchedule::FREQUENCY_MONTHLY, null, 5, '2026-12-20 14:00', '2027-01-05'],
        ];
    }

    #[DataProvider('nextRunAfterProvider')]
    public function testNextRunAfterIsStrictlyAfterTheGivenDay(
        string $frequency,
        ?int $weekday,
        ?int $dayOfMonth,
        string $from,
        string $expected,
    ): void {
        $schedule = $this->createSchedule($frequency, $weekday, $dayOfMonth);

        $result = $this->sut->nextRunAfter($schedule, new DateTime($from));

        self::assertSame($expected.' 00:00', $result->format('Y-m-d H:i'));
    }

    public function testNextRunAfterDoesNotModifyTheGivenDate(): void
    {
        $from = new DateTime('2026-11-04 14:00');

        $this->sut->nextRunAfter($this->createSchedule(ExportSchedule::FREQUENCY_WEEKLY, 3, null), $from);

        self::assertSame('2026-11-04 14:00', $from->format('Y-m-d H:i'));
    }

    public function testWeeklyScheduleWithoutWeekdayIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sut->nextRunAfter($this->createSchedule(ExportSchedule::FREQUENCY_WEEKLY, null, null), new DateTime());
    }

    public function testMonthlyScheduleWithoutDayOfMonthIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sut->nextRunAfter($this->createSchedule(ExportSchedule::FREQUENCY_MONTHLY, null, null), new DateTime());
    }

    public function testUnknownFrequencyIsRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sut->nextRunAfter($this->createSchedule('yearly', null, null), new DateTime());
    }

    private function createSchedule(string $frequency, ?int $weekday, ?int $dayOfMonth): ExportSchedule
    {
        $schedule = new ExportSchedule();
        $schedule->setFrequency($frequency);
        $schedule->setWeekday($weekday);
        $schedule->setDayOfMonth($dayOfMonth);

        return $schedule;
    }
}
