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

use demosplan\DemosPlanCoreBundle\Addon\AddonManifestCollectionWrapper;
use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Addon\AddonListCommand;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class AddonListCommandTest extends FunctionalTestCase
{
    public function testEmptyList(): void
    {
        $commandTester = $this->executeCommand([]);

        // Assert the output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Enabled', $output);
    }

    public function testListEnabled(): void
    {
        $addonName = 'demos-europe/demosplan-test-addon';
        $info = [
            $addonName => ['enabled' => true],
        ];
        $commandTester = $this->executeCommand($info);

        // Assert the output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Enabled', $output);
        $this->assertStringContainsString($addonName, $output);
        $this->assertStringContainsString('true', $output);
    }

    public function testListDisabled(): void
    {
        $addonName = 'demos-europe/demosplan-test-addon';
        $info = [
            $addonName => ['enabled' => false],
        ];
        $commandTester = $this->executeCommand($info);

        // Assert the output
        $output = $commandTester->getDisplay();
        $this->assertStringContainsString('Name', $output);
        $this->assertStringContainsString('Enabled', $output);
        $this->assertStringContainsString($addonName, $output);
        $this->assertStringContainsString('false', $output);
    }

    private function executeCommand(array $info): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $tokenMockMethods = [
            new MockMethodDefinition('load', $info),
        ];
        $addonManifestCollectionWrapper = $this->getMock(AddonManifestCollectionWrapper::class, $tokenMockMethods);

        $application->add(
            new AddonListCommand(
                $addonManifestCollectionWrapper,
                $this->createMock(ParameterBagInterface::class),
                null
            )
        );

        $command = $application->find(AddonListCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        // Execute the command
        $commandTester->execute([]);

        return $commandTester;
    }
}
