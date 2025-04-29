<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\HelloWorldCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Core\Core\Functional\CommandTesterTrait;

class HelloWorldCommandTest extends FunctionalTestCase
{
    use CommandTesterTrait;

    public function testExecuteWithoutName(): void
    {
        // Arrange
        $command = $this->createCommandTester();
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([
            'command' => $command->getName(),
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Hello, World!', $output);
        $this->assertStringContainsString(date('Y-m-d'), $output);
    }

    public function testExecuteWithName(): void
    {
        // Arrange
        $command = $this->createCommandTester();
        $commandTester = new CommandTester($command);
        $name = 'DemosPlan';

        // Act
        $exitCode = $commandTester->execute([
            'command' => $command->getName(),
            'name' => $name,
        ]);
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString("Hello, $name!", $output);
        $this->assertStringContainsString(date('Y-m-d'), $output);
    }

    public function testExecuteWithQuietOption(): void
    {
        // Arrange
        $command = $this->createCommandTester();
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(
            [
                'command' => $command->getName(),
            ],
            ['verbosity' => OutputInterface::VERBOSITY_QUIET]
        );
        $output = $commandTester->getDisplay();

        // Assert
        $this->assertEquals(Command::SUCCESS, $exitCode);
        $this->assertEmpty($output);
    }

    private function createCommandTester(): Command
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $parameterBag = $this->createMock(ParameterBagInterface::class);
        
        $application->add(
            new HelloWorldCommand($parameterBag)
        );

        return $application->find(HelloWorldCommand::getDefaultName());
    }
}