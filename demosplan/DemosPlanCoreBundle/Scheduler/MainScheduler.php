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

use demosplan\DemosPlanCoreBundle\Message\AddonMaintenanceMessage;
use demosplan\DemosPlanCoreBundle\Message\CheckMailBouncesMessage;
use demosplan\DemosPlanCoreBundle\Message\FetchStatementGeoDataMessage;
use demosplan\DemosPlanCoreBundle\Message\PurgeDeletedProceduresMessage;
use demosplan\DemosPlanCoreBundle\Message\SendEmailsMessage;
use demosplan\DemosPlanCoreBundle\Message\SwitchElementStatesMessage;
use demosplan\DemosPlanCoreBundle\Message\SwitchProcedurePhasesMessage;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('main')]
class MainScheduler implements ScheduleProviderInterface
{
    private const MAINTENANCE_OFFSET = '5 seconds';

    public function __construct(private readonly LockFactory $lockFactory)
    {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new SendEmailsMessage()))
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new CheckMailBouncesMessage()))
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new FetchStatementGeoDataMessage()))
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new PurgeDeletedProceduresMessage()))
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new AddonMaintenanceMessage()))
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new SwitchElementStatesMessage()))
            ->add(RecurringMessage::every(self::MAINTENANCE_OFFSET, new SwitchProcedurePhasesMessage()))
            ->lock($this->lockFactory->createLock('demosplan_main_scheduler_lock'))
        ;
    }
}
