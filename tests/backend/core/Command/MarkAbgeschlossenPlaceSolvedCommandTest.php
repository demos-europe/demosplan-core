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
use demosplan\DemosPlanCoreBundle\Command\MarkAbgeschlossenPlaceSolvedCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Tests\Base\FunctionalTestCase;

class MarkAbgeschlossenPlaceSolvedCommandTest extends FunctionalTestCase
{
    private function createCommandTester(): CommandTester
    {
        $kernel = self::bootKernel();
        $application = new ConsoleApplication($kernel, false);

        $command = new MarkAbgeschlossenPlaceSolvedCommand(
            $this->getContainer()->get(EntityManagerInterface::class),
            $this->getContainer()->get(ParameterBagInterface::class),
        );

        $application->add($command);

        return new CommandTester($application->find('dplan:workflow:mark-abgeschlossen-solved'));
    }

    public function testMarksAbgeschlossenAsSolved(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Erwiderung verfassen', 'sortIndex' => 0, 'solved' => false]);
        PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Abgeschlossen', 'sortIndex' => 4, 'solved' => false]);

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Updated', $output);

        $this->getEntityManager()->clear();
        $places = $this->getEntityManager()->getRepository(Place::class)->findBy(['procedure' => $procedure->getId()], ['sortIndex' => 'ASC']);
        self::assertFalse($places[0]->getSolved(), 'Erwiderung verfassen should remain unsolved');
        self::assertTrue($places[1]->getSolved(), 'Abgeschlossen should now be solved');
    }

    public function testDryRunDoesNotPersist(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Abgeschlossen', 'sortIndex' => 4, 'solved' => false]);

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute(['--dry-run' => true]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Would update', $output);

        $this->getEntityManager()->clear();
        $place = $this->getEntityManager()->getRepository(Place::class)->findOneBy(['procedure' => $procedure->getId(), 'name' => 'Abgeschlossen']);
        self::assertFalse($place->getSolved(), 'Dry run should not persist changes');
    }

    public function testSkipsProceduresWithoutAbgeschlossen(): void
    {
        // Arrange — procedure with only custom places, no "Abgeschlossen"
        $procedure = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Lektorat', 'sortIndex' => 0, 'solved' => false]);
        PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Kommentare', 'sortIndex' => 1, 'solved' => false]);

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute(['--procedure' => $procedure->getId()]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('review manually', $output);

        $this->getEntityManager()->clear();
        $places = $this->getEntityManager()->getRepository(Place::class)->findBy(['procedure' => $procedure->getId()]);
        foreach ($places as $place) {
            self::assertFalse($place->getSolved(), 'No place should be marked solved');
        }
    }

    public function testSkipsProceduresAlreadyHavingSolvedPlace(): void
    {
        // Arrange — procedure already has a solved place
        $procedure = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Abgeschlossen', 'sortIndex' => 4, 'solved' => true]);

        $commandTester = $this->createCommandTester();

        // Act
        $exitCode = $commandTester->execute(['--procedure' => $procedure->getId()]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('All procedures already have at least one solved place', $output);
    }

    public function testProcedureFilter(): void
    {
        // Arrange
        $procedure1 = ProcedureFactory::createOne();
        $procedure2 = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedure1, 'name' => 'Abgeschlossen', 'sortIndex' => 4, 'solved' => false]);
        PlaceFactory::createOne(['procedure' => $procedure2, 'name' => 'Abgeschlossen', 'sortIndex' => 4, 'solved' => false]);

        $commandTester = $this->createCommandTester();

        // Act — only process procedure1
        $exitCode = $commandTester->execute(['--procedure' => $procedure1->getId()]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);

        $this->getEntityManager()->clear();
        $place1 = $this->getEntityManager()->getRepository(Place::class)->findOneBy(['procedure' => $procedure1->getId(), 'name' => 'Abgeschlossen']);
        $place2 = $this->getEntityManager()->getRepository(Place::class)->findOneBy(['procedure' => $procedure2->getId(), 'name' => 'Abgeschlossen']);

        self::assertTrue($place1->getSolved(), 'Filtered procedure should be updated');
        self::assertFalse($place2->getSolved(), 'Other procedure should not be affected');
    }
}
