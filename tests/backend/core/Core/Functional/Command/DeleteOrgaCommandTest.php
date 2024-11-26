<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Data\DeleteOrgaCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Orga\OrgaDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class DeleteOrgaCommandTest extends FunctionalTestCase
{
    private Orga|Proxy|null $testOrga;

    /** @var SqlQueriesService */
    protected $queriesService;

    public function setUp(): void
    {
        parent::setUp();

        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testOrga = OrgaFactory::createOne();
    }

    public function testExecute()
    {
        $id = $this->testOrga->getId();
        $commandTester = $this->executeCommand($id);
        $output = $commandTester->getDisplay();
        $successString = "orga(s) with id(s) $id are deleted";

        $this->assertStringContainsString($successString, $output);
    }

    public function testNoFoundMatchingOrga()
    {
        $commandTester = $this->executeCommand('');
        $output = $commandTester->getDisplay();
        $warningString = 'Matching organisation(s) not found for id(s)';
        $infoString = 'no organisation(s) found to delete';

        $this->assertStringContainsString($warningString, $output);
        $this->assertStringContainsString($infoString, $output);
    }

    public function testMissingArgument()
    {
        $this->expectException("Symfony\Component\Console\Exception\RuntimeException");
        $this->expectExceptionMessage('Not enough arguments (missing: "orgaIds")');
        $this->executeCommandWithoutArgument();
    }

    private function executeCommand(string $OrgaIds): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel);

        $orgaDeleter = $this->getMock(OrgaDeleter::class);
        $orgaDeleter->method('deleteOrganisations')->willReturnCallback(function ($param): void {});

        $application->add(new DeleteOrgaCommand(
            $this->createMock(ParameterBagInterface::class),
            $orgaDeleter,
            $this->queriesService,
            null
        ));

        $command = $application->find(DeleteOrgaCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), 'orgaIds' => $OrgaIds, '--without-repopulate' => true, '--dry-run' => true]);

        return $commandTester;
    }

    private function executeCommandWithoutArgument(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel);

        $orgaDeleter = $this->getMock(OrgaDeleter::class);
        $orgaDeleter->method('deleteOrganisations')->willReturnCallback(function ($param): void {});

        $application->add(new DeleteOrgaCommand(
            $this->createMock(ParameterBagInterface::class),
            $orgaDeleter,
            $this->queriesService,
            null
        ));

        $command = $application->find(DeleteOrgaCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(['command' => $command->getName(), '--without-repopulate' => true, '--dry-run' => true]);

        return $commandTester;
    }
}
