<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomFieldResourceType;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use EDT\JsonApi\RequestHandling\Body\CreationRequestBody;
use EDT\Wrapping\EntityData;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\FunctionalTestCase;

class CustomFieldResourceTypeRequiredPermissionTest extends FunctionalTestCase
{
    private const STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION = 'feature_statements_custom_fields_required';

    protected ?CustomFieldResourceType $sut = null;

    private $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldResourceType::class);
        $this->procedure = ProcedureFactory::createOne();
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    #[DataProvider('fieldTypeProvider')]
    public function testCreateRequiredStatementFieldWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->createField($fieldType, CustomFieldSupportedEntity::statement, true);
    }

    #[DataProvider('fieldTypeProvider')]
    public function testCreateRequiredStatementFieldWithPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields', self::STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION]);

        // Act
        $result = $this->createField($fieldType, CustomFieldSupportedEntity::statement, true);

        // Assert
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testCreateNotRequiredStatementFieldWithoutPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);

        // Act
        $result = $this->createField($fieldType, CustomFieldSupportedEntity::statement, false);

        // Assert
        static::assertFalse($result->getRequired());
    }

    public function testCreateRequiredSegmentFieldWithoutPermission(): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);

        // Act
        $result = $this->createField('multiSelect', CustomFieldSupportedEntity::segment, true);

        // Assert
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldRequiredWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);
        $configuration = $this->createStatementFieldConfiguration($fieldType, false);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->updateField($configuration, ['isRequired' => true]);
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldRequiredWithPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields', self::STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, false);

        // Act
        $result = $this->updateField($configuration, ['isRequired' => true]);

        // Assert
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testEditAlreadyRequiredStatementFieldWithoutPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act
        $result = $this->updateField($configuration, ['name' => 'Renamed', 'isRequired' => true]);

        // Assert
        static::assertSame('Renamed', $result->getName());
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldNotRequiredWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->updateField($configuration, ['isRequired' => false]);
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldNotRequiredWithPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields', self::STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act
        $result = $this->updateField($configuration, ['isRequired' => false]);

        // Assert
        static::assertFalse($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testEditStatementFieldWithoutIsRequiredWithoutPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act
        $result = $this->updateField($configuration, ['name' => 'Renamed']);

        // Assert
        static::assertSame('Renamed', $result->getName());
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testSendingIsRequiredAsStringWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions(['area_admin_custom_fields']);
        $configuration = $this->createStatementFieldConfiguration($fieldType, false);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->updateField($configuration, ['isRequired' => 'true']);
    }

    public static function fieldTypeProvider(): array
    {
        return [
            'multiSelect'  => ['multiSelect'],
            'singleSelect' => ['singleSelect'],
        ];
    }

    private function createField(string $fieldType, CustomFieldSupportedEntity $targetEntity, bool $isRequired): CustomFieldInterface
    {
        $attributes = [
            'fieldType'      => $fieldType,
            'name'           => 'Decision',
            'description'    => 'Decision on the statement',
            'isRequired'     => $isRequired,
            'options'        => [['label' => 'Yes'], ['label' => 'No']],
            'sourceEntity'   => CustomFieldSupportedEntity::procedure->value,
            'sourceEntityId' => $this->procedure->getId(),
            'targetEntity'   => $targetEntity->value,
        ];

        return $this->sut
            ->createEntity(new CreationRequestBody(null, 'CustomField', $attributes, [], []))
            ->getEntity();
    }

    private function createStatementFieldConfiguration(string $fieldType, bool $isRequired): CustomFieldConfiguration
    {
        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity(CustomFieldSupportedEntity::statement->value);

        $factory = 'multiSelect' === $fieldType
            ? $factory->asMultiSelect('Decision', options: ['Yes', 'No'], isRequired: $isRequired)
            : $factory->asRadioButton('Decision', options: ['Yes', 'No'], isRequired: $isRequired);

        return $factory->create()->_real();
    }

    private function updateField(CustomFieldConfiguration $configuration, array $attributes): CustomFieldInterface
    {
        return $this->sut
            ->updateEntity($configuration->getId(), new EntityData('CustomField', $attributes, [], []))
            ->getEntity();
    }
}
