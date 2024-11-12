<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 * All rights reserved
 */

namespace backend\core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\Data\DeleteProcedureCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class DeleteProcedureCommandTest extends FunctionalTestCase
{
    private null|Procedure|Proxy $testProcedure;

    /** @var SqlQueriesService */
    protected $queriesService;

    public function setUp(): void
    {
        parent::setUp();

        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testProcedure = ProcedureFactory::createOne();
    }

    public function testExecute(): void
    {
        $id = $this->testProcedure->getId();
        $commandTester = $this->executeCommand($id);
        $output = $commandTester->getDisplay();
        $successString = "procedure(s) with id(s) $id are deleted";

        $this->assertStringContainsString($successString, $output);
    }

    public function testDeleteAllProcedures(): void
    {
        $proceduresToDelete = $this->getEntries(Procedure::class);

        $procedureIds = [];

        foreach ($proceduresToDelete as $procedure) {
            $procedureIds[] = $procedure->getId();
        }

        $commandTester = $this->executeCommand(implode(', ', $procedureIds));
        $output = $commandTester->getDisplay();
        $successString = "procedure(s) with id(s) $procedureIds are deleted";
        $this->assertStringContainsString($successString, $output);
    }

    public function testMissingArgument(): void
    {
        $this->expectException("Symfony\Component\Console\Exception\RuntimeException");
        $this->expectExceptionMessage('Not enough arguments (missing: "procedureIds")');
        $this->executeCommandWithoutArgument();
    }

    private function executeCommand(string $procedureIds): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $procedureDeleter = $this->getMock(ProcedureDeleter::class);
        $procedureDeleter->method('deleteProcedures')->willReturnCallback(function ($param): void {
        });

        $application->add(
            new DeleteProcedureCommand(
                $this->createMock(ParameterBagInterface::class),
                $procedureDeleter,
                $this->queriesService,
                null
            )
        );

        $command = $application->find(DeleteProcedureCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            [
                'command' => $command->getName(),
                'procedureIds' => $procedureIds,
                '--without-repopulate' => true,
                '--dry-run' => true,
            ]
        );

        return $commandTester;
    }

    private function executeCommandWithoutArgument(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $procedureDeleter = $this->getMock(ProcedureDeleter::class);
        $procedureDeleter->method('deleteProcedures')->willReturnCallback(function ($param): void {
        });

        $application->add(
            new DeleteProcedureCommand(
                $this->createMock(ParameterBagInterface::class),
                $procedureDeleter,
                $this->queriesService,
                null
            )
        );

        $command = $application->find(DeleteProcedureCommand::getDefaultName());
        $commandTester = new CommandTester($command);
        $commandTester->execute(
            ['command' => $command->getName(), '--without-repopulate' => true, '--dry-run' => true]
        );

        return $commandTester;
    }
}
