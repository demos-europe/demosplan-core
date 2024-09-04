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

use demosplan\DemosPlanCoreBundle\Addon\AddonInfo;
use demosplan\DemosPlanCoreBundle\Addon\AddonRegistry;
use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Addon\AddonAutoinstallCommand;
use demosplan\DemosPlanCoreBundle\Command\Addon\AddonInstallFromZipCommand;
use demosplan\DemosPlanCoreBundle\Command\Addon\AddonUninstallCommand;
use demosplan\DemosPlanCoreBundle\Command\CacheClearCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class AddonAutoinstallCommandTest extends FunctionalTestCase
{
    private ?AddonInstallFromZipCommand $addonInstallCommand;
    private ?AddonUninstallCommand $addonUninstallCommand;
    private ?AddonRegistry $registry;
    private ?CacheClearCommand $cacheClearCommand;
    private ?ParameterBagInterface $parameterBag;

    public function setUp(): void
    {
        parent::setUp();

        $this->addonInstallCommand = $this->createMock(AddonInstallFromZipCommand::class);
        $this->addonUninstallCommand = $this->createMock(AddonUninstallCommand::class);
        $this->registry = $this->createMock(AddonRegistry::class);
        $this->cacheClearCommand = $this->createMock(CacheClearCommand::class);
        $this->parameterBag = $this->createMock(ParameterBagInterface::class);
    }

    public function testInstallsAddonsDefinedInConfiguration(): void
    {
        $addons = [
            ['name' => 'addon1', 'version' => '1.0.0'],
            ['name' => 'addon2', 'version' => '2.0.0'],
        ];

        $this->parameterBag->method('get')->with('dplan_addons')->willReturn($addons);
        $this->registry->method('getEnabledAddons')->willReturn([]);

        $this->cacheClearCommand->expects($this->exactly(2))->method('run')->willReturn(Command::SUCCESS);
        $this->addonInstallCommand->expects($this->exactly(2))->method('run')->willReturn(Command::SUCCESS);

        $this->executeCommand();
    }

    public function testSkipsInstallationIfAddonAlreadyInstalled(): void
    {
        $addons = [
            ['name' => 'addon1', 'version' => '1.0.0'],
        ];

        $enabledAddons = [
            'demos-europe/addon1' => $this->createMock(AddonInfo::class),
        ];

        $enabledAddons['demos-europe/addon1']->method('getVersion')->willReturn('1.0.0');

        $this->parameterBag->method('get')->with('dplan_addons')->willReturn($addons);
        $this->registry->method('getEnabledAddons')->willReturn($enabledAddons);

        $this->cacheClearCommand->expects($this->never())->method('run');
        $this->addonInstallCommand->expects($this->never())->method('run');

        $this->executeCommand();
    }

    public function testUninstallsOutdatedAddonAndInstallsNewVersion(): void
    {
        $addons = [
            ['name' => 'addon1', 'version' => '2.0.0'],
        ];

        $enabledAddons = [
            'demos-europe/addon1' => $this->createMock(AddonInfo::class),
        ];

        $enabledAddons['demos-europe/addon1']->method('getVersion')->willReturn('1.0.0');

        $this->parameterBag->method('get')->with('dplan_addons')->willReturn($addons);
        $this->registry->method('getEnabledAddons')->willReturn($enabledAddons);

        $this->cacheClearCommand->expects($this->once())->method('run')->willReturn(Command::SUCCESS);
        $this->addonUninstallCommand->expects($this->once())->method('run')->willReturn(Command::SUCCESS);
        $this->addonInstallCommand->expects($this->once())->method('run')->willReturn(Command::SUCCESS);

        $this->executeCommand();
    }

    public function testUninstallsAddonsNotInConfiguration(): void
    {
        $addons = [];

        $enabledAddons = [
            'demos-europe/addon1' => $this->createMock(AddonInfo::class),
        ];

        $this->parameterBag->method('get')->with('dplan_addons')->willReturn($addons);
        $this->registry->method('getEnabledAddons')->willReturn($enabledAddons);

        $this->addonUninstallCommand->expects($this->once())->method('run')->willReturn(Command::SUCCESS);

        $this->executeCommand();
    }

    public function testReturnsSuccessIfNoAddonsDefined(): void
    {
        $this->parameterBag->method('get')->with('dplan_addons')->willReturn(null);

        $commandTester = $this->executeCommand();

        $this->assertSame(Command::SUCCESS, $commandTester->getStatusCode());
    }

    private function executeCommand(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $application->add(new AddonAutoinstallCommand(
            $this->addonInstallCommand,
            $this->addonUninstallCommand,
            $this->registry,
            $this->cacheClearCommand,
            $this->parameterBag
        ));

        $command = $application->find(AddonAutoinstallCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName()]);

        return $commandTester;
    }
}
