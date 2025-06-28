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
use demosplan\DemosPlanCoreBundle\Command\InitializeWorkflowPlacesCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Repository\Workflow\PlaceRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class InitializeWorkflowPlacesCommandTest extends FunctionalTestCase
{
    protected InitializeWorkflowPlacesCommand $sut;
    protected PlaceRepository $placeRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(InitializeWorkflowPlacesCommand::class);
        $this->placeRepository = $this->getContainer()->get(PlaceRepository::class);
    }

    public function testCommandIsRegistered(): void
    {
        // Arrange
        $application = new ConsoleApplication($this->getKernel());

        // Act & Assert
        self::assertTrue($application->has('dplan:workflow:init-places'));
    }

    public function testAddDefaultPlacesToProcedureWithoutPlaces(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Verify procedure has no places initially
        $initialPlaces = $this->placeRepository->findBy(['procedure' => $procedure->getId()]);
        self::assertEmpty($initialPlaces);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Successfully processed 1 procedure(s)', $output);

        // Verify the 5 default places were created
        $createdPlaces = $this->placeRepository->findBy(['procedure' => $procedure->getId()], ['sortIndex' => 'ASC']);
        self::assertCount(5, $createdPlaces);

        // Verify each place has correct name and sort index
        $expectedPlaces = [
            ['name' => 'Erwiderung verfassen', 'sortIndex' => 0],
            ['name' => 'Fachtechnische Prüfung', 'sortIndex' => 1],
            ['name' => 'Juristische Prüfung', 'sortIndex' => 2],
            ['name' => 'Lektorat', 'sortIndex' => 3],
            ['name' => 'Abgeschlossen', 'sortIndex' => 4],
        ];

        foreach ($expectedPlaces as $index => $expectedPlace) {
            self::assertSame($expectedPlace['name'], $createdPlaces[$index]->getName());
            self::assertSame($expectedPlace['sortIndex'], $createdPlaces[$index]->getSortIndex());
            self::assertSame($procedure->getId(), $createdPlaces[$index]->getProcedure()->getId());
        }
    }

    public function testSkipProceduresThatAlreadyHavePlaces(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $existingPlace = PlaceFactory::createOne(['procedure' => $procedure]);
        
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        $initialPlaceCount = count($this->placeRepository->findBy(['procedure' => $procedure->getId()]));
        self::assertSame(1, $initialPlaceCount);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('All procedures already have workflow places', $output);

        // Verify no additional places were created
        $finalPlaces = $this->placeRepository->findBy(['procedure' => $procedure->getId()]);
        self::assertCount(1, $finalPlaces);
        self::assertSame($existingPlace->getId(), $finalPlaces[0]->getId());
    }

    public function testDryRunMode(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Verify procedure has no places initially
        $initialPlaces = $this->placeRepository->findBy(['procedure' => $procedure->getId()]);
        self::assertEmpty($initialPlaces);

        // Act
        $exitCode = $commandTester->execute(['--dry-run' => true]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Running in DRY-RUN mode', $output);
        self::assertStringContainsString('DRY-RUN: Would have processed 1 procedure(s)', $output);

        // Verify no places were actually created
        $finalPlaces = $this->placeRepository->findBy(['procedure' => $procedure->getId()]);
        self::assertEmpty($finalPlaces);
    }

    public function testProcessSpecificProcedureById(): void
    {
        // Arrange
        $targetProcedure = ProcedureFactory::createOne();
        $otherProcedure = ProcedureFactory::createOne();
        
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--procedure-id' => $targetProcedure->getId()]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Successfully processed 1 procedure(s)', $output);

        // Verify only target procedure got places
        $targetPlaces = $this->placeRepository->findBy(['procedure' => $targetProcedure->getId()]);
        $otherPlaces = $this->placeRepository->findBy(['procedure' => $otherProcedure->getId()]);
        
        self::assertCount(5, $targetPlaces);
        self::assertEmpty($otherPlaces);
    }

    public function testProcessSpecificProcedureByIdThatAlreadyHasPlaces(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedure]);
        
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--procedure-id' => $procedure->getId()]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('already has workflow places', $output);
    }

    public function testProcessNonExistentProcedureId(): void
    {
        // Arrange
        $nonExistentId = '00000000-0000-0000-0000-000000000000';
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute(['--procedure-id' => $nonExistentId]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString("doesn't exist", $output);
    }

    public function testSkipMasterTemplates(): void
    {
        // Arrange
        $masterTemplate = ProcedureFactory::createOne(['masterTemplate' => true]);
        $regularProcedure = ProcedureFactory::createOne(['masterTemplate' => false]);
        
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        // Verify master template was skipped
        $masterPlaces = $this->placeRepository->findBy(['procedure' => $masterTemplate->getId()]);
        $regularPlaces = $this->placeRepository->findBy(['procedure' => $regularProcedure->getId()]);
        
        self::assertEmpty($masterPlaces, 'Master template should not get default places');
        self::assertCount(5, $regularPlaces, 'Regular procedure should get default places');
    }

    public function testProcessMultipleProceduresWithoutPlaces(): void
    {
        // Arrange
        $procedure1 = ProcedureFactory::createOne();
        $procedure2 = ProcedureFactory::createOne();
        $procedureWithPlaces = ProcedureFactory::createOne();
        PlaceFactory::createOne(['procedure' => $procedureWithPlaces]);
        
        $application = new ConsoleApplication($this->getKernel());
        $command = $application->find('dplan:workflow:init-places');
        $commandTester = new CommandTester($command);

        // Act
        $exitCode = $commandTester->execute([]);

        // Assert
        self::assertSame(Command::SUCCESS, $exitCode);
        
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Successfully processed 2 procedure(s)', $output);

        // Verify both procedures got places
        $places1 = $this->placeRepository->findBy(['procedure' => $procedure1->getId()]);
        $places2 = $this->placeRepository->findBy(['procedure' => $procedure2->getId()]);
        $placesExisting = $this->placeRepository->findBy(['procedure' => $procedureWithPlaces->getId()]);
        
        self::assertCount(5, $places1);
        self::assertCount(5, $places2);
        self::assertCount(1, $placesExisting, 'Procedure with existing places should remain unchanged');
    }
}