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
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * MaintenanceCommand - DEPRECATED.
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
class MaintenanceCommand extends CoreCommand
{
    protected static $defaultDescription = 'DemosPlan Maintenance daemon (DEPRECATED - use Symfony Scheduler)';

    /**
     * @deprecated This command is deprecated. Use Symfony Scheduler instead.
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('<comment>DEPRECATION NOTICE: dplan:maintenance is deprecated!</comment>');
        $output->writeln('');
        $output->writeln('All maintenance tasks have been migrated to Symfony Scheduler + Messenger.');
        $output->writeln('Starting messenger:consume scheduler_maintenance scheduler_daily_maintenance for backwards compatibility...');
        $output->writeln('');
        $output->writeln('Please update your scripts to use:');
        $output->writeln('  <info>php bin/console messenger:consume scheduler_maintenance scheduler_daily_maintenance -e prod --no-debug</info>');
        $output->writeln('');

        // For backwards compatibility, start the messenger consumer using
        // Process component properly handles terminal signals (SIGINT/SIGTERM)
        // Pass --env and --no-debug as command arguments to ensure they take precedence
        $command = [
            'php',
            'bin/console',
            'messenger:consume',
            'scheduler_daily_maintenance',
            'scheduler_maintenance',
            '--env='.$this->parameterBag->get('kernel.environment'),
        ];

        if (!$this->parameterBag->get('kernel.debug')) {
            $command[] = '--no-debug';
        }

        $process = new Process($command);
        $process->setTimeout(null);
        $process->setTty(Process::isTtySupported());
        $process->setEnv([
            'ACTIVE_PROJECT' => $this->parameterBag->get('demosplan.project_name'),
        ]);

        return $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });
    }

    /*
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
