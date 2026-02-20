<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Command;

use demosplan\DemosPlanCoreBundle\Command\SchedulerTriggerCommand;
use demosplan\DemosPlanCoreBundle\Message\AutoSwitchProcedurePhasesMessage;
use demosplan\DemosPlanCoreBundle\Message\CleanupFilesMessage;
use demosplan\DemosPlanCoreBundle\Message\SendEmailsMessage;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\HandledStamp;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;

#[AsSchedule('daily_maintenance')]
class TestDailyScheduler implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('1 day', new AutoSwitchProcedurePhasesMessage()))
            ->add(RecurringMessage::every('1 day', new CleanupFilesMessage()));
    }
}

#[AsSchedule('maintenance')]
class TestMainScheduler implements ScheduleProviderInterface
{
    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->add(RecurringMessage::every('5 seconds', new SendEmailsMessage()));
    }
}

class SchedulerTriggerCommandTest extends TestCase
{
    private ?MessageBusInterface $messageBus = null;
    private ?CommandTester $commandTester = null;

    protected function setUp(): void
    {
        $this->messageBus = $this->createMock(MessageBusInterface::class);

        $command = new SchedulerTriggerCommand(
            $this->messageBus,
            [new TestDailyScheduler(), new TestMainScheduler()]
        );
        $this->commandTester = new CommandTester($command);
    }

    public function testListShowsAllDiscoveredTasks(): void
    {
        $exitCode = $this->commandTester->execute(['--list' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();

        self::assertStringContainsString('daily_maintenance', $output);
        self::assertStringContainsString('maintenance', $output);
        self::assertStringContainsString('auto-switch-procedure-phases', $output);
        self::assertStringContainsString('cleanup-files', $output);
        self::assertStringContainsString('send-emails', $output);
    }

    public function testTriggerSingleTaskDispatches(): void
    {
        $handledStamp = new HandledStamp(null, 'TestHandler::__invoke');
        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(AutoSwitchProcedurePhasesMessage::class))
            ->willReturn(new Envelope(new AutoSwitchProcedurePhasesMessage(), [$handledStamp]));

        $exitCode = $this->commandTester->execute(['task' => 'auto-switch-procedure-phases']);

        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('auto-switch-procedure-phases', $output);
        self::assertStringContainsString('handled', $output);
    }

    public function testTriggerSingleTaskShowsAsyncWhenNotHandledSynchronously(): void
    {
        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(SendEmailsMessage::class))
            ->willReturn(new Envelope(new SendEmailsMessage()));

        $exitCode = $this->commandTester->execute(['task' => 'send-emails']);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('dispatched (async)', $this->commandTester->getDisplay());
    }

    public function testTriggerUnknownTaskFails(): void
    {
        $exitCode = $this->commandTester->execute(['task' => 'nonexistent-task']);

        self::assertSame(Command::INVALID, $exitCode);
        self::assertStringContainsString('Unknown task "nonexistent-task"', $this->commandTester->getDisplay());
    }

    public function testNoArgumentPromptsInteractiveSelection(): void
    {
        $handledStamp = new HandledStamp(null, 'TestHandler::__invoke');
        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->with(self::isInstanceOf(CleanupFilesMessage::class))
            ->willReturn(new Envelope(new CleanupFilesMessage(), [$handledStamp]));

        // Simulate selecting the second option (cleanup-files, index 1)
        $this->commandTester->setInputs(['1']);
        $exitCode = $this->commandTester->execute([]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('handled', $this->commandTester->getDisplay());
    }

    public function testTriggerTaskHandlesDispatchFailure(): void
    {
        $this->messageBus
            ->expects(self::once())
            ->method('dispatch')
            ->willThrowException(new \RuntimeException('Bus error'));

        $exitCode = $this->commandTester->execute(['task' => 'auto-switch-procedure-phases']);

        self::assertSame(Command::FAILURE, $exitCode);
        $output = $this->commandTester->getDisplay();
        self::assertStringContainsString('failed', $output);
        self::assertStringContainsString('Bus error', $output);
    }

    public function testAllDailyDispatchesOnlyDailyTasks(): void
    {
        $handledStamp = new HandledStamp(null, 'TestHandler::__invoke');
        $dispatchedClasses = [];

        $this->messageBus
            ->expects(self::exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use ($handledStamp, &$dispatchedClasses) {
                $dispatchedClasses[] = $message::class;

                return new Envelope($message, [$handledStamp]);
            });

        $exitCode = $this->commandTester->execute(['--all-daily' => true]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertContains(AutoSwitchProcedurePhasesMessage::class, $dispatchedClasses);
        self::assertContains(CleanupFilesMessage::class, $dispatchedClasses);
        self::assertNotContains(SendEmailsMessage::class, $dispatchedClasses);
    }

    public function testAllDailyReportsWarningOnPartialFailure(): void
    {
        $handledStamp = new HandledStamp(null, 'TestHandler::__invoke');
        $callCount = 0;

        $this->messageBus
            ->method('dispatch')
            ->willReturnCallback(function (object $message) use ($handledStamp, &$callCount) {
                ++$callCount;
                if (1 === $callCount) {
                    throw new \RuntimeException('Task failed');
                }

                return new Envelope($message, [$handledStamp]);
            });

        $exitCode = $this->commandTester->execute(['--all-daily' => true]);

        self::assertSame(Command::FAILURE, $exitCode);
        self::assertStringContainsString('1 task(s) failed', $this->commandTester->getDisplay());
    }

    public function testListOptionTakesPriorityOverTaskArgument(): void
    {
        $this->messageBus
            ->expects(self::never())
            ->method('dispatch');

        $exitCode = $this->commandTester->execute([
            'task'   => 'auto-switch-procedure-phases',
            '--list' => true,
        ]);

        self::assertSame(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Available Scheduled Tasks', $this->commandTester->getDisplay());
    }

    public function testClassToTaskNameConversion(): void
    {
        self::assertSame(
            'auto-switch-procedure-phases',
            SchedulerTriggerCommand::classToTaskName(AutoSwitchProcedurePhasesMessage::class)
        );
        self::assertSame(
            'send-emails',
            SchedulerTriggerCommand::classToTaskName(SendEmailsMessage::class)
        );
        self::assertSame(
            'cleanup-files',
            SchedulerTriggerCommand::classToTaskName(CleanupFilesMessage::class)
        );
    }
}
