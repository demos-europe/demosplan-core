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
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;

/**
 * MaintenanceCommand.
 *
 * It supervises two consumers, because the background exports must not share a worker with the
 * five-second scheduler tick:
 * ```
 * php bin/console messenger:consume scheduler_daily_maintenance scheduler_maintenance
 * php bin/console messenger:consume async --limit=1 --memory-limit=1536M --time-limit=3600
 * ```
 *
 * @see \demosplan\DemosPlanCoreBundle\Scheduler\MainScheduler
 * @see \demosplan\DemosPlanCoreBundle\MessageHandler\
 */
#[AsCommand(name: 'dplan:maintenance', aliases: ['demos:maintenance'])]
class MaintenanceCommand extends CoreCommand implements SignalableCommandInterface
{
    protected static $defaultDescription = 'DemosPlan Maintenance daemon (DEPRECATED - use Symfony Scheduler)';

    /**
     * Exports get their own consumer rather than sharing the scheduler one: the scheduler transport
     * carries tasks on a five second tick, while a single large export runs for minutes and peaks
     * above a gigabyte. Sharing one worker would stall mail sending and phase switching for the
     * duration, and leave that peak resident in a process systemd keeps alive for twelve hours.
     */
    private const SCHEDULER_TRANSPORTS = ['scheduler_daily_maintenance', 'scheduler_maintenance'];
    private const EXPORT_TRANSPORT = 'async';

    private const SUPERVISION_INTERVAL_MICROSECONDS = 500_000;

    /**
     * A consumer that dies this quickly did not process anything - it failed to start (a missing
     * transport package, an unreachable database). Restarting it immediately would spin, so back off.
     */
    private const CRASH_THRESHOLD_SECONDS = 5;
    private const CRASH_BACKOFF_SECONDS = 30;

    private ?Process $schedulerConsumer = null;
    private ?Process $exportConsumer = null;
    private ?float $exportConsumerStartedAt = null;

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $output->writeln('Instead of running maintenance command you can run the messenger workers directly if preferred:');
        $output->writeln('  <info>php bin/console messenger:consume '.implode(' ', self::SCHEDULER_TRANSPORTS).' -e prod --no-debug</info>');
        $output->writeln('  <info>php bin/console messenger:consume '.self::EXPORT_TRANSPORT.' '.implode(' ', $this->getExportConsumerOptions()).' -e prod --no-debug</info>');
        $output->writeln('');

        $this->schedulerConsumer = $this->startConsumer($output, 'scheduler', self::SCHEDULER_TRANSPORTS);
        $this->startExportConsumer($output);

        try {
            while ($this->schedulerConsumer->isRunning()) {
                // --limit=1 makes the export consumer exit after every job; bring it back up. It
                // blocks while the queue is empty, so this is not a busy loop.
                if (!$this->exportConsumer->isRunning()) {
                    $this->backOffIfExportConsumerCrashed($output);
                    $this->startExportConsumer($output);
                }
                usleep(self::SUPERVISION_INTERVAL_MICROSECONDS);
            }

            return $this->schedulerConsumer->getExitCode() ?? Command::SUCCESS;
        } finally {
            $this->stopConsumers();
        }
    }

    private function startExportConsumer(OutputInterface $output): void
    {
        $this->exportConsumer = $this->startConsumer(
            $output,
            'export',
            [self::EXPORT_TRANSPORT],
            $this->getExportConsumerOptions()
        );
        $this->exportConsumerStartedAt = microtime(true);
    }

    private function backOffIfExportConsumerCrashed(OutputInterface $output): void
    {
        $ranFor = microtime(true) - (float) $this->exportConsumerStartedAt;
        if (0 === $this->exportConsumer?->getExitCode() || $ranFor >= self::CRASH_THRESHOLD_SECONDS) {
            return;
        }

        $output->writeln(sprintf(
            '<comment>[export] consumer exited after %.1fs with code %s; retrying in %ds</comment>',
            $ranFor,
            var_export($this->exportConsumer?->getExitCode(), true),
            self::CRASH_BACKOFF_SECONDS
        ));
        sleep(self::CRASH_BACKOFF_SECONDS);
    }

    /**
     * Exit after each export so the memory PhpWord and Elastica allocated is returned to the OS, and
     * only ever run one export at a time. The supervision loop restarts the process.
     *
     * @return string[]
     */
    private function getExportConsumerOptions(): array
    {
        return [
            '--limit=1',
            '--memory-limit='.$this->parameterBag->get('async_export_memory_limit'),
            '--time-limit='.$this->parameterBag->get('async_export_time_limit'),
        ];
    }

    /**
     * @param string[] $transports
     * @param string[] $options
     */
    private function startConsumer(OutputInterface $output, string $label, array $transports, array $options = []): Process
    {
        // Pass --env and --no-debug as command arguments to ensure they take precedence
        $command = ['php', 'bin/console', 'messenger:consume', ...$transports, ...$options];
        $command[] = '--env='.$this->parameterBag->get('kernel.environment');

        if (!$this->parameterBag->get('kernel.debug')) {
            $command[] = '--no-debug';
        }

        $process = new Process($command);
        $process->setTimeout(null);
        $process->setEnv([
            'ACTIVE_PROJECT' => $this->parameterBag->get('demosplan.project_name'),
        ]);
        // No TTY: it would suppress the output callback, and two children need their output
        // labelled to stay readable in one stream.
        $process->start(static function ($type, $buffer) use ($output, $label): void {
            foreach (preg_split('/\R/', rtrim($buffer, "\r\n")) as $line) {
                $output->writeln('['.$label.'] '.$line);
            }
        });

        return $process;
    }

    private function stopConsumers(): void
    {
        $this->exportConsumer?->stop();
        $this->schedulerConsumer?->stop();
    }

    /**
     * `systemctl stop dplanMaintenance@<project>` sends SIGTERM to this process only; without
     * forwarding it, both consumers would be left orphaned.
     */
    public function getSubscribedSignals(): array
    {
        return [SIGINT, SIGTERM];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): int|false
    {
        $this->stopConsumers();

        return Command::SUCCESS;
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
