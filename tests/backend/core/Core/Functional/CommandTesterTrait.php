<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

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
