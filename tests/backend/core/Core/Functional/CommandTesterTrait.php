<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use Symfony\Component\Console\Tester\CommandTester;

trait CommandTesterTrait
{
    private function getCommandTesterByName($kernel, string $commandName): CommandTester
    {
        $application = new ConsoleApplication($kernel);

        $command = $application->find($commandName);

        return new CommandTester($command);
    }
}
