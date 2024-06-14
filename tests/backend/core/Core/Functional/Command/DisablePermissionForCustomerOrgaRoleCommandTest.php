<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Permission\DisablePermissionForCustomerOrgaRoleCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class DisablePermissionForCustomerOrgaRoleCommandTest extends PermissionForCustomerOrgaRoleCommandTest
{
    public function testExecute(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $application->add(new DisablePermissionForCustomerOrgaRoleCommand(
            $this->createMock(ParameterBagInterface::class),
            $this->customerService,
            $this->roleService,
            $this->accessControlService,
        ));

        $command = $application->find(DisablePermissionForCustomerOrgaRoleCommand::getDefaultName());
        $commandTester = new CommandTester($command);

        $this->assertStringsInCommandOutput($commandTester, true, 'This is a dry run. No changes have been made to the database.');
        $this->assertStringsInCommandOutput($commandTester, false, 'Changes have been applied to the database.');

        return $commandTester;
    }
}
