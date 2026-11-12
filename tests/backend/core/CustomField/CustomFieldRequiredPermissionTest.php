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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Exception\AccessDeniedException;
use demosplan\DemosPlanCoreBundle\ResourceTypes\CustomFieldResourceType;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Creation\CustomFieldCreator;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldUpdater;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Enum\CustomFieldSupportedEntity;
use EDT\JsonApi\RequestHandling\Body\CreationRequestBody;
use EDT\Wrapping\EntityData;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\FunctionalTestCase;

class CustomFieldRequiredPermissionTest extends FunctionalTestCase
{
    private const FIELD_TYPE_MULTI_SELECT = 'multiSelect';
    private const FIELD_TYPE_SINGLE_SELECT = 'singleSelect';
    private const ATTRIBUTE_IS_REQUIRED = 'isRequired';
    private const ATTRIBUTE_NAME = 'name';
    private const FIELD_NAME = 'Decision';
    private const RENAMED_FIELD_NAME = 'Renamed';
    private const OPTION_LABEL_YES = 'Yes';
    private const OPTION_LABEL_NO = 'No';
    private const NON_BOOLEAN_IS_REQUIRED_VALUE = 'true';
    private const RESOURCE_TYPE_NAME = 'CustomField';
    private const STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION = 'feature_statements_custom_fields_required';

    protected ?CustomFieldCreator $creator = null;

    protected ?CustomFieldUpdater $updater = null;

    private ?Procedure $procedure = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->creator = self::getContainer()->get(CustomFieldCreator::class);
        $this->updater = self::getContainer()->get(CustomFieldUpdater::class);
        $procedure = ProcedureFactory::createOne()->_real();
        static::assertInstanceOf(Procedure::class, $procedure);
        $this->procedure = $procedure;
        $this->logIn(UserFactory::createOne(['deleted' => false])->_real());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testCreateRequiredStatementFieldWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->createField($fieldType, CustomFieldSupportedEntity::statement, true);
    }

    #[DataProvider('fieldTypeProvider')]
    public function testCreateRequiredStatementFieldWithPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([self::STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION]);

        // Act
        $result = $this->createField($fieldType, CustomFieldSupportedEntity::statement, true);

        // Assert
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testCreateNotRequiredStatementFieldWithoutPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);

        // Act
        $result = $this->createField($fieldType, CustomFieldSupportedEntity::statement, false);

        // Assert
        static::assertFalse($result->getRequired());
    }

    public function testCreateRequiredSegmentFieldWithoutPermission(): void
    {
        // Arrange
        $this->enablePermissions([]);

        // Act
        $result = $this->createField(
            self::FIELD_TYPE_MULTI_SELECT,
            CustomFieldSupportedEntity::segment,
            true
        );

        // Assert
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldRequiredWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, false);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->updateField($configuration, [self::ATTRIBUTE_IS_REQUIRED => true]);
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldRequiredWithPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([self::STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, false);

        // Act
        $result = $this->updateField($configuration, [self::ATTRIBUTE_IS_REQUIRED => true]);

        // Assert
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testEditAlreadyRequiredStatementFieldWithoutPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act
        $result = $this->updateField($configuration, [
            self::ATTRIBUTE_NAME        => self::RENAMED_FIELD_NAME,
            self::ATTRIBUTE_IS_REQUIRED => true,
        ]);

        // Assert
        static::assertSame(self::RENAMED_FIELD_NAME, $result->getName());
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldNotRequiredWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->updateField($configuration, [self::ATTRIBUTE_IS_REQUIRED => false]);
    }

    #[DataProvider('fieldTypeProvider')]
    public function testMakeStatementFieldNotRequiredWithPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([self::STATEMENT_CUSTOM_FIELD_IS_REQUIRED_PERMISSION]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act
        $result = $this->updateField($configuration, [self::ATTRIBUTE_IS_REQUIRED => false]);

        // Assert
        static::assertFalse($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testEditStatementFieldWithoutIsRequiredWithoutPermission(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, true);

        // Act
        $result = $this->updateField($configuration, [self::ATTRIBUTE_NAME => self::RENAMED_FIELD_NAME]);

        // Assert
        static::assertSame(self::RENAMED_FIELD_NAME, $result->getName());
        static::assertTrue($result->getRequired());
    }

    #[DataProvider('fieldTypeProvider')]
    public function testSendingIsRequiredAsStringWithoutPermissionIsDenied(string $fieldType): void
    {
        // Arrange
        $this->enablePermissions([]);
        $configuration = $this->createStatementFieldConfiguration($fieldType, false);

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        $this->updateField($configuration, [self::ATTRIBUTE_IS_REQUIRED => self::NON_BOOLEAN_IS_REQUIRED_VALUE]);
    }

    public function testApiCreateRequiredStatementFieldWithoutPermissionIsDenied(): void
    {
        // Arrange
        $this->enablePermissions([]);
        $attributes = $this->buildCreationAttributes(
            self::FIELD_TYPE_SINGLE_SELECT,
            CustomFieldSupportedEntity::statement,
            true
        );

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        self::getContainer()->get(CustomFieldResourceType::class)
            ->createEntity(new CreationRequestBody(null, self::RESOURCE_TYPE_NAME, $attributes, [], []));
    }

    public function testApiMakeStatementFieldRequiredWithoutPermissionIsDenied(): void
    {
        // Arrange
        $this->enablePermissions([]);
        $configuration = $this->createStatementFieldConfiguration(
            self::FIELD_TYPE_SINGLE_SELECT,
            false
        );

        // Act & Assert
        $this->expectException(AccessDeniedException::class);
        self::getContainer()->get(CustomFieldResourceType::class)
            ->updateEntity(
                $configuration->getId(),
                new EntityData(self::RESOURCE_TYPE_NAME, [self::ATTRIBUTE_IS_REQUIRED => true], [], [])
            );
    }

    public static function fieldTypeProvider(): array
    {
        return [
            self::FIELD_TYPE_MULTI_SELECT  => [self::FIELD_TYPE_MULTI_SELECT],
            self::FIELD_TYPE_SINGLE_SELECT => [self::FIELD_TYPE_SINGLE_SELECT],
        ];
    }

    private function createField(
        string $fieldType,
        CustomFieldSupportedEntity $targetEntity,
        bool $isRequired,
    ): CustomFieldInterface {
        return $this->creator->createCustomField(
            $this->buildCreationAttributes($fieldType, $targetEntity, $isRequired)
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function buildCreationAttributes(
        string $fieldType,
        CustomFieldSupportedEntity $targetEntity,
        bool $isRequired,
    ): array {
        return [
            'fieldType'                 => $fieldType,
            self::ATTRIBUTE_NAME        => self::FIELD_NAME,
            'description'               => 'Decision on the statement',
            self::ATTRIBUTE_IS_REQUIRED => $isRequired,
            'options'                   => [
                ['label' => self::OPTION_LABEL_YES],
                ['label' => self::OPTION_LABEL_NO],
            ],
            'sourceEntity'              => CustomFieldSupportedEntity::procedure->value,
            'sourceEntityId'            => $this->procedure->getId(),
            'targetEntity'              => $targetEntity->value,
        ];
    }

    private function createStatementFieldConfiguration(string $fieldType, bool $isRequired): CustomFieldConfiguration
    {
        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure)
            ->withRelatedTargetEntity(CustomFieldSupportedEntity::statement->value);

        $factory = match ($fieldType) {
            self::FIELD_TYPE_MULTI_SELECT => $factory->asMultiSelect(
                self::FIELD_NAME,
                options: [self::OPTION_LABEL_YES, self::OPTION_LABEL_NO],
                isRequired: $isRequired
            ),
            self::FIELD_TYPE_SINGLE_SELECT => $factory->asRadioButton(
                self::FIELD_NAME,
                options: [self::OPTION_LABEL_YES, self::OPTION_LABEL_NO],
                isRequired: $isRequired
            ),
        };

        $configuration = $factory->create()->_real();
        static::assertInstanceOf(CustomFieldConfiguration::class, $configuration);

        return $configuration;
    }

    private function updateField(CustomFieldConfiguration $configuration, array $attributes): CustomFieldInterface
    {
        return $this->updater->updateCustomField($configuration->getId(), $attributes);
    }
}
