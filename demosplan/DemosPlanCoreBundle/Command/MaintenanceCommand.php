<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * MaintenanceCommand - DEPRECATED
 *
 * This command has been refactored to use Symfony Scheduler + Messenger.
 * All maintenance tasks are now scheduled via MainScheduler and executed
 * asynchronously through message handlers.
 *
 * To run the scheduler:
 * ```
 * php bin/console messenger:consume scheduler_default
 * ```
 *
 * @deprecated Use Symfony Scheduler (MainScheduler) instead
 * @see \demosplan\DemosPlanCoreBundle\Scheduler\MainScheduler
 * @see \demosplan\DemosPlanCoreBundle\MessageHandler\
 */
#[AsCommand(name: 'dplan:maintenance', aliases: ['demos:maintenance'])]
class MaintenanceCommand extends Command
{
    protected static $defaultDescription = 'DemosPlan Maintenance daemon (DEPRECATED - use Symfony Scheduler)';

    /**
     * @deprecated This command is deprecated. Use Symfony Scheduler instead.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<error>DEPRECATION: dplan:maintenance is deprecated!</error>');
        $output->writeln('');
        $output->writeln('All maintenance tasks have been migrated to Symfony Scheduler + Messenger.');
        $output->writeln('');
        $output->writeln('To run maintenance tasks, use:');
        $output->writeln('  <info>php bin/console messenger:consume scheduler_default</info>');
        $output->writeln('');
        $output->writeln('For more information, see:');
        $output->writeln('  - MainScheduler: demosplan/DemosPlanCoreBundle/Scheduler/MainScheduler.php');
        $output->writeln('  - MessageHandlers: demosplan/DemosPlanCoreBundle/MessageHandler/');
        $output->writeln('');

        return Command::FAILURE;
    }

    /**
     * All maintenance task methods have been moved to MessageHandlers.
     * @deprecated
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\SendEmailsMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\CheckMailBouncesMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\FetchStatementGeoDataMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\PurgeDeletedProceduresMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\AddonMaintenanceMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\SwitchElementStatesMessageHandler
     * @see \demosplan\DemosPlanCoreBundle\MessageHandler\SwitchProcedurePhasesMessageHandler
     */
}
