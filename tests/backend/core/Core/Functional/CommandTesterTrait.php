<?php

declare(strict_types=1);

namespace Tests\Core\Core\Functional;

use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

trait CommandTesterTrait
{
    private function getCommandTesterByName($kernel, string $commandName): CommandTester
    {
        $application = new Application($kernel);

        $command = $application->find($commandName);

        return new CommandTester($command);
    }
}
