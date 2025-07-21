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

use DemosEurope\DemosplanAddon\Contracts\Entities\StatementFormDefinitionInterface;
use demosplan\DemosPlanCoreBundle\Command\ApplyProcedureTypeTemplateCommand;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureTypeFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFormDefinitionFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Repository\ProcedureTypeRepository;
use ReflectionClass;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Base\FunctionalTestCase;

class ConfigureProcedureTypeCommandTest extends FunctionalTestCase
{
    public const APPLIED_SUCCESSFULLY = 'applied successfully';
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

    private function createEwmProcedureType(string $name = 'Einwendungsmanagement'): ProcedureType
    {
        return ProcedureTypeFactory::createOne([
            'name'                    => $name,
            'statementFormDefinition' => StatementFormDefinitionFactory::createOne()->_real(),
        ])->_real();
    }

    private function findFieldByName(StatementFormDefinition $formDefinition, string $fieldName): ?StatementFieldDefinition
    {
        foreach ($formDefinition->getFieldDefinitions() as $fieldDefinition) {
            if ($fieldDefinition->getName() === $fieldName) {
                return $fieldDefinition;
            }
        }

        return null;
    }

    private function findConfiguredProcedureType(): ?ProcedureType
    {
        $allProcedureTypes = $this->getContainer()->get(ProcedureTypeRepository::class)->findAll();

        foreach ($allProcedureTypes as $procedureType) {
            $formDefinition = $procedureType->getStatementFormDefinition();
            if ($formDefinition) {
                $citizenField = $this->findFieldByName($formDefinition, StatementFormDefinitionInterface::CITIZEN_XOR_ORGA_AND_ORGA_NAME);
                if ($citizenField && !$citizenField->isEnabled()) {
                    return $procedureType;
                }
            }
        }

        return null;
    }

    private function assertEwmFieldConfiguration(StatementFormDefinition $formDefinition): void
    {
        $citizenField = $this->findFieldByName($formDefinition, StatementFormDefinitionInterface::CITIZEN_XOR_ORGA_AND_ORGA_NAME);
        $countyField = $this->findFieldByName($formDefinition, StatementFormDefinitionInterface::COUNTY_REFERENCE);

        self::assertNotNull($citizenField, 'citizenXorOrgaAndOrgaName field should exist');
        self::assertNotNull($countyField, 'countyReference field should exist');
        self::assertFalse($citizenField->isEnabled(), 'citizenXorOrgaAndOrgaName should be disabled');
        self::assertTrue($countyField->isEnabled(), 'countyReference should be enabled');
        self::assertTrue($countyField->isRequired(), 'countyReference should be required');
    }

    public function testCommandIsRegistered(): void
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

    public function testConfigureExistingEwmProcedureType(): void
    {
        // Arrange
        $this->createEwmProcedureType();
        $commandTester = new CommandTester($this->sut);

        // Act
        $exitCode = $commandTester->execute(['template' => 'ewm'], ['interactive' => false]);

        // Assert
        self::assertEquals(Command::SUCCESS, $exitCode);

        $configuredProcedureType = $this->findConfiguredProcedureType();
        self::assertNotNull($configuredProcedureType, 'No procedure type was configured with ewm settings');

        $this->assertEwmFieldConfiguration($configuredProcedureType->getStatementFormDefinition());
        self::assertStringContainsString(self::APPLIED_SUCCESSFULLY, $commandTester->getDisplay());
    }

    public function testConfigureNonExistentProcedureTypeWithoutCreateFlag(): void
    {
        $commandTester = new CommandTester($this->sut);
        $exitCode = $commandTester->execute(['template' => 'nonexistent']);

        self::assertNotEquals(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Unknown template: "nonexistent"', $commandTester->getDisplay());
    }

    public function testInteractiveCanCreateNewProcedureType(): void
    {
        $commandTester = new CommandTester($this->sut);
        $exitCode = $commandTester->execute(['template' => 'ewm'], ['interactive' => false]);

        self::assertEquals(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(self::APPLIED_SUCCESSFULLY, $commandTester->getDisplay());
    }

    public function testDryRunShowsChangesWithoutApplying(): void
    {
        // Arrange
        $procedureType = $this->createEwmProcedureType();
        $commandTester = new CommandTester($this->sut);

        // Act
        $exitCode = $commandTester->execute(['template' => 'ewm', '--dry-run' => true]);

        // Assert
        self::assertEquals(Command::SUCCESS, $exitCode);

        // Verify no actual changes were made
        $this->getEntityManager()->clear();
        $unchangedProcedureType = $this->getEntityManager()->find(ProcedureType::class, $procedureType->getId());
        $formDefinition = $unchangedProcedureType->getStatementFormDefinition();

        $citizenField = $this->findFieldByName($formDefinition, StatementFormDefinitionInterface::CITIZEN_XOR_ORGA_AND_ORGA_NAME);
        $countyField = $this->findFieldByName($formDefinition, StatementFormDefinitionInterface::COUNTY_REFERENCE);

        self::assertTrue($citizenField->isEnabled(), 'citizenXorOrgaAndOrgaName should remain enabled in dry-run');
        self::assertFalse($countyField->isEnabled(), 'countyReference should remain disabled in dry-run');

        $output = $commandTester->getDisplay();
        self::assertStringContainsString('[DRY RUN]', $output);
        self::assertStringContainsString('citizenXorOrgaAndOrgaName: enabled=false (was: enabled=true', $output);
        self::assertStringContainsString('countyReference: enabled=true, required=true (was: enabled=false)', $output);
        self::assertStringContainsString('No changes applied due to dry-run mode', $output);
    }

    public function testInvalidProcedureTypeShowsError(): void
    {
        $commandTester = new CommandTester($this->sut);
        $exitCode = $commandTester->execute(['template' => 'invalid']);

        self::assertNotEquals(Command::SUCCESS, $exitCode);
        self::assertStringContainsString('Unknown template: "invalid"', $commandTester->getDisplay());
    }

    public function testFieldCreationWorksWithCorrectConstructorArguments(): void
    {
        // Clear all existing procedure types to force field creation
        $this->getEntityManager()->createQuery('DELETE FROM demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType')->execute();

        // Create a procedure type with empty field definitions
        $formDefinition = new StatementFormDefinition();
        $reflection = new ReflectionClass($formDefinition);
        $fieldDefinitionsProperty = $reflection->getProperty('fieldDefinitions');
        $fieldDefinitionsProperty->setAccessible(true);
        $fieldDefinitionsProperty->setValue($formDefinition, new \Doctrine\Common\Collections\ArrayCollection());

        ProcedureTypeFactory::createOne([
            'name'                    => 'TestProcedureType',
            'statementFormDefinition' => $formDefinition,
        ]);

        $commandTester = new CommandTester($this->sut);
        $exitCode = $commandTester->execute(['template' => 'ewm'], ['interactive' => false]);

        self::assertEquals(Command::SUCCESS, $exitCode);
        self::assertStringContainsString(self::APPLIED_SUCCESSFULLY, $commandTester->getDisplay());
    }
}
