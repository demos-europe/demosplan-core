<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Command;

use DateTimeImmutable;
use ReflectionClass;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Throwable;

/**
 * Trigger scheduled maintenance tasks on demand.
 *
 * Auto-discovers all tasks registered in ScheduleProviderInterface implementations
 * and allows dispatching them directly on the message bus, bypassing the cron schedule.
 * Useful for development and debugging.
 */
#[AsCommand(
    name: 'dplan:scheduler:trigger',
    description: 'Trigger scheduled maintenance tasks on demand',
)]
class SchedulerTriggerCommand extends Command
{
    /**
     * @var array<string, array{class: class-string, scheduler: string}>|null
     */
    private ?array $discoveredTasks = null;

    /**
     * @param iterable<ScheduleProviderInterface> $schedulers
     */
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        #[TaggedIterator('scheduler.schedule_provider')]
        private readonly iterable $schedulers,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('task', InputArgument::OPTIONAL, 'Task name to trigger (use --list to see available tasks)')
            ->addOption('list', 'l', InputOption::VALUE_NONE, 'List all available tasks')
            ->addOption('all-daily', null, InputOption::VALUE_NONE, 'Trigger all daily maintenance tasks');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $tasks = $this->discoverTasks();

        if ($input->getOption('list')) {
            return $this->listTasks($io, $tasks);
        }

        if ($input->getOption('all-daily')) {
            return $this->triggerByScheduler($io, $tasks, 'daily_maintenance');
        }

        $taskName = $input->getArgument('task');
        if (null === $taskName) {
            $taskName = $io->choice('Which task do you want to trigger?', array_keys($tasks));
        }

        if (!isset($tasks[$taskName])) {
            $io->error(sprintf('Unknown task "%s". Use --list to see available tasks.', $taskName));

            return Command::INVALID;
        }

        return $this->dispatchTask($io, $taskName, $tasks[$taskName]['class']);
    }

    /**
     * Discovers all tasks from registered schedulers.
     *
     * @return array<string, array{class: class-string, scheduler: string}>
     */
    private function discoverTasks(): array
    {
        if (null !== $this->discoveredTasks) {
            return $this->discoveredTasks;
        }

        $this->discoveredTasks = [];

        foreach ($this->schedulers as $scheduler) {
            $schedulerName = $this->getSchedulerName($scheduler);
            $schedule = $scheduler->getSchedule();

            foreach ($schedule->getRecurringMessages() as $recurringMessage) {
                $provider = $recurringMessage->getProvider();
                $dummyContext = new MessageContext(
                    $schedulerName,
                    $recurringMessage->getId(),
                    $recurringMessage->getTrigger(),
                    new DateTimeImmutable(),
                );

                foreach ($provider->getMessages($dummyContext) as $message) {
                    $taskName = self::classToTaskName($message::class);
                    $this->discoveredTasks[$taskName] = [
                        'class'     => $message::class,
                        'scheduler' => $schedulerName,
                    ];
                }
            }
        }

        ksort($this->discoveredTasks);

        return $this->discoveredTasks;
    }

    /**
     * @param array<string, array{class: class-string, scheduler: string}> $tasks
     */
    private function listTasks(SymfonyStyle $io, array $tasks): int
    {
        $io->title('Available Scheduled Tasks');

        $grouped = [];
        foreach ($tasks as $taskName => $taskInfo) {
            $grouped[$taskInfo['scheduler']][] = $taskName;
        }

        foreach ($grouped as $schedulerName => $taskNames) {
            $io->section($schedulerName);
            $io->listing($taskNames);
        }

        $io->info('Usage: dplan:scheduler:trigger <task-name>');

        return Command::SUCCESS;
    }

    /**
     * @param array<string, array{class: class-string, scheduler: string}> $tasks
     */
    private function triggerByScheduler(SymfonyStyle $io, array $tasks, string $schedulerName): int
    {
        $matchingTasks = array_filter($tasks, static fn (array $info) => $info['scheduler'] === $schedulerName);

        if ([] === $matchingTasks) {
            $io->error(sprintf('No tasks found for scheduler "%s".', $schedulerName));

            return Command::FAILURE;
        }

        $io->title(sprintf('Triggering all %s tasks', $schedulerName));

        $failed = 0;
        foreach ($matchingTasks as $taskName => $taskInfo) {
            if (Command::SUCCESS !== $this->dispatchTask($io, $taskName, $taskInfo['class'])) {
                ++$failed;
            }
        }

        if ($failed > 0) {
            $io->warning(sprintf('%d task(s) failed.', $failed));

            return Command::FAILURE;
        }

        $io->success(sprintf('All %s tasks triggered.', $schedulerName));

        return Command::SUCCESS;
    }

    /**
     * @param class-string $messageClass
     */
    private function dispatchTask(SymfonyStyle $io, string $taskName, string $messageClass): int
    {
        $io->write(sprintf('Dispatching <info>%s</info>... ', $taskName));

        try {
            $envelope = $this->messageBus->dispatch(new $messageClass());
            $handledStamps = $envelope->all(HandledStamp::class);

            if ([] !== $handledStamps) {
                $io->writeln('<fg=green>handled</>');
            } else {
                $io->writeln('<fg=yellow>dispatched (async)</>');
            }
        } catch (Throwable $e) {
            $io->writeln('<fg=red>failed</>');
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Converts a message FQCN to a kebab-case task name.
     *
     * Example: AutoSwitchProcedurePhasesMessage → auto-switch-procedure-phases
     */
    public static function classToTaskName(string $className): string
    {
        $shortName = substr($className, strrpos($className, '\\') + 1);
        $shortName = preg_replace('/Message$/', '', $shortName);

        return strtolower(preg_replace('/(?<!^)[A-Z]/', '-$0', $shortName));
    }

    private function getSchedulerName(ScheduleProviderInterface $scheduler): string
    {
        $reflection = new ReflectionClass($scheduler);
        $attributes = $reflection->getAttributes(AsSchedule::class);

        if ([] !== $attributes) {
            return $attributes[0]->newInstance()->name;
        }

        return $reflection->getShortName();
    }
}
