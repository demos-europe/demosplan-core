<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Command;

use demosplan\DemosPlanCoreBundle\Application\ConsoleApplication;
use demosplan\DemosPlanCoreBundle\Command\SyncStatementStatusCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class SyncStatementStatusCommandTest extends FunctionalTestCase
{
    private function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $command = new SyncStatementStatusCommand(
            $this->getContainer()->get(EntityManagerInterface::class),
            $this->getContainer()->get(StatementService::class),
            $this->getContainer()->get(ParameterBagInterface::class),
        );

        $application->add($command);

        return new CommandTester($application->find('dplan:statement:sync-status'));
    }

    public function testCommandIsRegistered(): void
    {
        $commandTester = $this->createCommandTester();
        self::assertInstanceOf(CommandTester::class, $commandTester);
    }

    public function testDryRunShowsChanges(): void
    {
        // Arrange — statement with solved segments but wrong status
        $procedure = ProcedureFactory::createOne();
        $statement = StatementFactory::createOne([
            'procedure' => $procedure,
            'status'    => 'new',
        ]);
        $solvedPlace = PlaceFactory::createOne([
            'procedure' => $procedure,
            'solved'    => true,
            'sortIndex' => 0,
        ]);
        SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => $statement,
            'place'                    => $solvedPlace,
        ]);

        // Disable the listener's auto-update by directly setting a wrong status
        $stmt = $this->getEntityManager()->find(Statement::class, $statement->getId());
        $stmt->setStatus('new');
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute(['--dry-run' => true]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Would update', $output);
        self::assertStringContainsString('DRY-RUN', $output);

        // Verify no actual change was persisted
        $this->getEntityManager()->clear();
        $freshStatement = $this->getEntityManager()->find(Statement::class, $statement->getId());
        self::assertSame('new', $freshStatement->getStatus());
    }

    public function testSyncUpdatesIncorrectStatuses(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statement = StatementFactory::createOne([
            'procedure' => $procedure,
            'status'    => 'new',
        ]);
        $unsolvedPlace = PlaceFactory::createOne([
            'procedure' => $procedure,
            'solved'    => false,
            'sortIndex' => 0,
        ]);
        SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => $statement,
            'place'                    => $unsolvedPlace,
        ]);

        // Force incorrect status (bypass listener)
        $stmt = $this->getEntityManager()->find(Statement::class, $statement->getId());
        $stmt->setStatus('new');
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Updated', $output);

        $this->getEntityManager()->clear();
        $freshStatement = $this->getEntityManager()->find(Statement::class, $statement->getId());
        self::assertSame('processing', $freshStatement->getStatus());
    }

    public function testProcedureFilter(): void
    {
        // Arrange — two procedures, each with a segment
        $procedure1 = ProcedureFactory::createOne();
        $procedure2 = ProcedureFactory::createOne();

        $solvedPlace1 = PlaceFactory::createOne(['procedure' => $procedure1, 'solved' => true, 'sortIndex' => 0]);
        $solvedPlace2 = PlaceFactory::createOne(['procedure' => $procedure2, 'solved' => true, 'sortIndex' => 0]);

        $statement1 = StatementFactory::createOne(['procedure' => $procedure1, 'status' => 'new']);
        $statement2 = StatementFactory::createOne(['procedure' => $procedure2, 'status' => 'new']);

        SegmentFactory::createOne(['procedure' => $procedure1, 'parentStatementOfSegment' => $statement1, 'place' => $solvedPlace1]);
        SegmentFactory::createOne(['procedure' => $procedure2, 'parentStatementOfSegment' => $statement2, 'place' => $solvedPlace2]);

        // Force both to wrong status
        $s1 = $this->getEntityManager()->find(Statement::class, $statement1->getId());
        $s2 = $this->getEntityManager()->find(Statement::class, $statement2->getId());
        $s1->setStatus('new');
        $s2->setStatus('new');
        $this->getEntityManager()->flush();
        $this->getEntityManager()->clear();

        $commandTester = $this->createCommandTester();

        // Act — only sync procedure1
        $exitCode = $commandTester->execute(['--procedure' => $procedure1->getId()]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $this->getEntityManager()->clear();
        $fresh1 = $this->getEntityManager()->find(Statement::class, $statement1->getId());
        $fresh2 = $this->getEntityManager()->find(Statement::class, $statement2->getId());

        self::assertSame('completed', $fresh1->getStatus());
        self::assertSame('new', $fresh2->getStatus(), 'Statement in other procedure should not be affected');
    }

    public function testSkipsAlreadyCorrectStatuses(): void
    {
        // Arrange — statement with correct status
        $procedure = ProcedureFactory::createOne();
        $unsolvedPlace = PlaceFactory::createOne(['procedure' => $procedure, 'solved' => false, 'sortIndex' => 0]);
        $statement = StatementFactory::createOne(['procedure' => $procedure, 'status' => 'processing']);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $statement, 'place' => $unsolvedPlace]);

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('skipped', $output);
    }
}
