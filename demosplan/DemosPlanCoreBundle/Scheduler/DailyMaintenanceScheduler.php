<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Scheduler;

use demosplan\DemosPlanCoreBundle\Message\AutoSwitchProcedurePhasesMessage;
use demosplan\DemosPlanCoreBundle\Message\CleanupFilesMessage;
use demosplan\DemosPlanCoreBundle\Message\CreateUnsubmittedDraftEmailsMessage;
use demosplan\DemosPlanCoreBundle\Message\DailyMaintenanceEventMessage;
use demosplan\DemosPlanCoreBundle\Message\DeleteOrphanEmailAddressesMessage;
use demosplan\DemosPlanCoreBundle\Message\PurgeSentEmailsMessage;
use demosplan\DemosPlanCoreBundle\Message\SendAssignedTaskNotificationEmailsMessage;
use demosplan\DemosPlanCoreBundle\Message\SendDeadlineNotificationsMessage;
use demosplan\DemosPlanCoreBundle\Message\SwitchNewsStatesMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

/**
 * Scheduler for daily maintenance tasks.
 *
 * All tasks are scheduled to run at 1:00 AM daily using cron expressions.
 * Each task is processed asynchronously through its corresponding MessageHandler.
 *
 * @see DailyMaintenanceEventMessageHandler
 * @see SendDeadlineNotificationsMessageHandler
 * @see CreateUnsubmittedDraftEmailsMessageHandler
 * @see SwitchNewsStatesMessageHandler
 * @see AutoSwitchProcedurePhasesMessageHandler
 * @see SendAssignedTaskNotificationEmailsMessageHandler
 * @see DeleteOrphanEmailAddressesMessageHandler
 * @see PurgeSentEmailsMessageHandler
 * @see CleanupFilesMessageHandler
 */
#[AsSchedule('daily_maintenance')]
class DailyMaintenanceScheduler implements ScheduleProviderInterface
{
    public function __construct(private readonly LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        // Run all daily maintenance tasks at 1:00 AM
        // Tasks are staggered by 5 minutes to prevent resource contention
        return (new Schedule())
            ->add(RecurringMessage::cron('0 1 * * *', new DailyMaintenanceEventMessage()))
            ->add(RecurringMessage::cron('5 1 * * *', new SendDeadlineNotificationsMessage()))
            ->add(RecurringMessage::cron('10 1 * * *', new CreateUnsubmittedDraftEmailsMessage()))
            ->add(RecurringMessage::cron('15 1 * * *', new SwitchNewsStatesMessage()))
            ->add(RecurringMessage::cron('20 1 * * *', new AutoSwitchProcedurePhasesMessage()))
            ->add(RecurringMessage::cron('25 1 * * *', new SendAssignedTaskNotificationEmailsMessage()))
            ->add(RecurringMessage::cron('30 1 * * *', new DeleteOrphanEmailAddressesMessage()))
            ->add(RecurringMessage::cron('35 1 * * *', new PurgeSentEmailsMessage()))
            ->add(RecurringMessage::cron('40 1 * * *', new CleanupFilesMessage()))
            ->lock($this->lockFactory->createLock('demosplan_daily_maintenance_scheduler_lock'))
        ;
    }
}
