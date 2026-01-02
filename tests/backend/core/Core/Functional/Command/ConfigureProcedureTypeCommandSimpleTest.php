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

use demosplan\DemosPlanCoreBundle\Command\ApplyProcedureTypeTemplateCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class ConfigureProcedureTypeCommandSimpleTest extends FunctionalTestCase
{
    /** @var ApplyProcedureTypeTemplateCommand */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        // Get the command directly from container using the actual command class
        $this->sut = new ApplyProcedureTypeTemplateCommand(
            $this->getContainer()->get('demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureTypeService'),
            $this->getContainer()->get('demosplan\DemosPlanCoreBundle\Repository\ProcedureTypeRepository'),
            $this->getContainer()->get('doctrine.orm.entity_manager')
        );
    }

    public function testCommandName(): void
    {
        // Arrange & Act
        $commandName = $this->sut::getDefaultName();

        // Assert
        self::assertEquals('dplan:procedure-type:apply-template', $commandName);
    }

    public function testCommandRequiresTypeArgument(): void
    {
        // Arrange
        $commandTester = new CommandTester($this->sut);

        // Act & Assert - should throw exception for missing required argument
        $this->expectException(\Symfony\Component\Console\Exception\RuntimeException::class);
        $this->expectExceptionMessage('Not enough arguments (missing: "template")');
        $commandTester->execute([]);
    }

    public function testInvalidProcedureTypeShowsError(): void
    {
        // Arrange
        $commandTester = new CommandTester($this->sut);

        // Act
        $exitCode = $commandTester->execute(['template' => 'invalid']);

        // Assert
        self::assertNotEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Unknown template: "invalid"', $output);
    }

    public function testShowsAvailableTemplatesOnInvalidTemplate(): void
    {
        // Arrange
        $commandTester = new CommandTester($this->sut);

        // Act
        $exitCode = $commandTester->execute(['template' => 'invalid']);

        // Assert
        self::assertNotEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Available templates:', $output);
        self::assertStringContainsString('ewm', $output);
    }

    public function testInteractiveSelectionAppliesConfigurationToExistingType(): void
    {
        // Arrange
        $commandTester = new CommandTester($this->sut);

        // Act - interactive mode will default to first existing procedure type
        $exitCode = $commandTester->execute(['template' => 'ewm'], ['interactive' => false]);

        // Assert - should successfully apply template to an existing procedure type
        self::assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();
        self::assertStringContainsString('Applying Template: ewm', $output);
        self::assertStringContainsString('applied successfully', $output);

        // Should show the field changes
        self::assertStringContainsString('citizenXorOrgaAndOrgaName: enabled=false', $output);
        self::assertStringContainsString('countyReference: enabled=true, required=true', $output);
    }

    public function testInteractiveSelectionWorksWithExistingProcedureTypes(): void
    {
        // Arrange
        $commandTester = new CommandTester($this->sut);

        // Act - should work with existing procedure types
        $exitCode = $commandTester->execute(['template' => 'ewm'], ['interactive' => false]);

        // Assert - command should succeed
        self::assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();

        // Should show procedure type information (the one that was selected/modified)
        self::assertStringContainsString('Name', $output);
        self::assertStringContainsString('Description', $output);
    }

    public function testEwmConfigurationIsAppliedCorrectly(): void
    {
        // Arrange
        $commandTester = new CommandTester($this->sut);

        // Act
        $exitCode = $commandTester->execute(['template' => 'ewm'], ['interactive' => false]);

        // Assert - verify the specific ewm template was applied
        self::assertEquals(Command::SUCCESS, $exitCode);
        $output = $commandTester->getDisplay();

        // Check for the expected field configuration changes
        self::assertStringContainsString('Field Configuration Changes', $output);
        self::assertStringContainsString('citizenXorOrgaAndOrgaName', $output);
        self::assertStringContainsString('countyReference', $output);
    }
}
