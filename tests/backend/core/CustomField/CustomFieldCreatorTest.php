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
use demosplan\DemosPlanCoreBundle\CustomField\MultiSelectField;
use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use demosplan\DemosPlanCoreBundle\CustomField\TextField;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldCreator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\UnitTestCase;

class CustomFieldCreatorTest extends UnitTestCase
{
    private const TEST_FIELD_NAME = 'Test Field';
    private const TEST_DESCRIPTION = 'Test';
    private const TEST_OPTIONS_ONE_TWO = [['label' => 'One'], ['label' => 'Two']];
    private const TEST_OPTIONS_ONLY_ONE_TWO = [['label' => 'Only One'], ['label' => 'Two']];

    /**
     * @var CustomFieldCreator|null
     */
    protected $sut;

    protected $procedure;
    protected $attributes;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldCreator::class);
        $this->procedure = ProcedureFactory::createOne();
        $this->attributes = [
            'fieldType'   => '',
            'name'        => self::TEST_FIELD_NAME,
            'description' => self::TEST_DESCRIPTION,
            'options'     => [
                ['label' => 'High'],
                ['label' => 'Medium'],
                ['label' => 'Low'],
            ],
            'sourceEntity'   => 'PROCEDURE',
            'sourceEntityId' => $this->procedure->getId(),
            'targetEntity'   => '',
        ];
    }

    /**
     * Test SingleSelect field creation with all attributes properly set.
     */
    public function testCreateSingleSelectFieldSuccessfully(): void
    {
        // Arrange: $attributes already populated in contructor
        $this->attributes['fieldType'] = 'singleSelect';
        $this->attributes['targetEntity'] = 'SEGMENT';

        // Act
        $result = $this->sut->createCustomField($this->attributes);

        // Assert - Test the superficial layer behavior
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertInstanceOf(RadioButtonField::class, $result);
        static::assertEquals(self::TEST_FIELD_NAME, $result->getName());
        static::assertEquals(self::TEST_DESCRIPTION, $result->getDescription());
        static::assertEquals('singleSelect', $result->getFieldType());

        // Verify options are properly created with UUIDs
        $options = $result->getOptions();
        static::assertCount(3, $options);
        static::assertEquals('High', $options[0]->getLabel());
        static::assertEquals('Medium', $options[1]->getLabel());
        static::assertEquals('Low', $options[2]->getLabel());

        $this->verifyAllOptionsHaveUUIDs($options);

        // Verify ID is set from configuration
        static::assertNotEmpty($result->getId());
    }

    private function verifyAllOptionsHaveUUIDs(array $options): void
    {
        foreach ($options as $option) {
            static::assertNotEmpty($option->getId());
            static::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $option->getId());
        }
    }

    /**
     * Test MultiSelect field creation with isRequired attribute.
     */
    public function testCreateMultiSelectFieldSuccessfully(): void
    {
        // Arrange: $attributes already populated in contructor
        $this->attributes['fieldType'] = 'multiSelect';
        $this->attributes['isRequired'] = true;
        $this->attributes['targetEntity'] = 'STATEMENT';

        // Act
        $result = $this->sut->createCustomField($this->attributes);

        // Assert
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertInstanceOf(MultiSelectField::class, $result);
        static::assertEquals(self::TEST_FIELD_NAME, $result->getName());
        static::assertEquals(self::TEST_DESCRIPTION, $result->getDescription());
        static::assertEquals('multiSelect', $result->getFieldType());

        // Test MultiSelect specific behavior - has isRequired
        static::assertTrue($result->getRequired() ?? false);

        $options = $result->getOptions();
        static::assertCount(3, $options);
        static::assertEquals('High', $options[0]->getLabel());
        static::assertEquals('Medium', $options[1]->getLabel());
        static::assertEquals('Low', $options[2]->getLabel());

        $this->verifyAllOptionsHaveUUIDs($options);

        static::assertNotEmpty($result->getId());
    }

    #[DataProvider('validationErrorDataProvider')]
    public function testCreateCustomFieldValidationErrors(array $attributes, string $expectedErrorMessage): void
    {
        // Arrange
        $baseAttributes = [
            'sourceEntityId' => $attributes['sourceEntityId'] ?: $this->procedure->getId(),
        ];
        $fullAttributes = array_merge($baseAttributes, $attributes);

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);
        $this->sut->createCustomField($fullAttributes);
    }

    public function validationErrorDataProvider(): array
    {
        return [
            'invalidFieldType' => [
                'attributes' => [
                    'fieldType'   => 'invalidType',
                    'name'        => self::TEST_FIELD_NAME,
                    'description' => self::TEST_DESCRIPTION,
                    'options'     => self::TEST_OPTIONS_ONE_TWO,
                ],
                'expectedErrorMessage' => 'No validator found for field type: invalidType',
            ],
            'invalidSourceEntityId' => [
                'attributes' => [
                    'fieldType'      => 'singleSelect',
                    'sourceEntityId' => 'invalid-id',
                    'sourceEntity'   => 'PROCEDURE',
                    'targetEntity'   => 'SEGMENT', // Wrong target for singleSelect
                    'name'           => self::TEST_FIELD_NAME,
                    'description'    => self::TEST_DESCRIPTION,
                    'options'        => self::TEST_OPTIONS_ONE_TWO,
                ],
                'expectedErrorMessage' => 'The sourceEntityId "invalid-id" was not found in the sourceEntity "PROCEDURE"',
            ],
            'singleSelectInvalidSourceTargetEntityCombination' => [
                'attributes' => [
                    'fieldType'    => 'singleSelect',
                    'sourceEntity' => 'PROCEDURE',
                    'targetEntity' => 'PROCEDURE_TEMPLATE', // not a valid target in either validator
                    'name'         => self::TEST_FIELD_NAME,
                    'description'  => self::TEST_DESCRIPTION,
                    'options'      => self::TEST_OPTIONS_ONLY_ONE_TWO,
                ],
                'expectedErrorMessage' => 'The target entity "PROCEDURE_TEMPLATE" is not valid for source entity "PROCEDURE". Allowed targets: STATEMENT, SEGMENT.',
            ],
            'multiSelectInvalidSourceTargetEntityCombination' => [
                'attributes' => [
                    'fieldType'    => 'multiSelect',
                    'sourceEntity' => 'PROCEDURE',
                    'targetEntity' => 'PROCEDURE_TEMPLATE', // not a valid target in either validator
                    'name'         => self::TEST_FIELD_NAME,
                    'description'  => self::TEST_DESCRIPTION,
                    'options'      => self::TEST_OPTIONS_ONLY_ONE_TWO,
                ],
                'expectedErrorMessage' => 'The target entity "PROCEDURE_TEMPLATE" is not valid for source entity "PROCEDURE". Allowed targets: STATEMENT, SEGMENT.',
            ],
            'textFieldInvalidTargetEntity' => [
                'attributes' => [
                    'fieldType'    => 'text',
                    'sourceEntity' => 'CUSTOMER',
                    'targetEntity' => 'SEGMENT', // Wrong target for text
                    'name'         => self::TEST_FIELD_NAME,
                    'description'  => self::TEST_DESCRIPTION,
                ],
                'expectedErrorMessage' => 'The target entity "SEGMENT" does not match the expected target entity "ORGA" for source entity "CUSTOMER".',
            ],
        ];
    }

    public function testCreateTextFieldSuccessfully(): void
    {
        // Arrange
        $customer = CustomerFactory::createOne();
        $attributes = [
            'fieldType'      => 'text',
            'name'           => self::TEST_FIELD_NAME,
            'description'    => self::TEST_DESCRIPTION,
            'isRequired'     => false,
            'sourceEntity'   => 'CUSTOMER',
            'sourceEntityId' => $customer->getId(),
            'targetEntity'   => 'ORGA',
        ];

        // Act
        $result = $this->sut->createCustomField($attributes);

        // Assert
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertInstanceOf(TextField::class, $result);
        static::assertEquals(self::TEST_FIELD_NAME, $result->getName());
        static::assertEquals(self::TEST_DESCRIPTION, $result->getDescription());
        static::assertEquals('text', $result->getFieldType());
        static::assertEmpty($result->getOptions());
        static::assertNotEmpty($result->getId());
    }

    public function testCreateRequiredTextFieldSuccessfully(): void
    {
        // Arrange
        $customer = CustomerFactory::createOne();
        $attributes = [
            'fieldType'      => 'text',
            'name'           => self::TEST_FIELD_NAME,
            'description'    => self::TEST_DESCRIPTION,
            'isRequired'     => true,
            'sourceEntity'   => 'CUSTOMER',
            'sourceEntityId' => $customer->getId(),
            'targetEntity'   => 'ORGA',
        ];

        // Act
        $result = $this->sut->createCustomField($attributes);

        // Assert
        static::assertInstanceOf(TextField::class, $result);
        static::assertTrue($result->getRequired());
    }

    public function testValidationFailsWhenProcedureHasStatements(): void
    {
        // Arrange
        $statementOriginal = StatementFactory::createOne(['procedure' => $this->procedure->_real()]);
        StatementFactory::createOne(
            [
                'procedure' => $this->procedure->_real(),
                'original'  => $statementOriginal->_real(),
            ]);

        // Arrange: $attributes already populated in contructor
        $this->attributes['fieldType'] = 'multiSelect';
        $this->attributes['isRequired'] = true;
        $this->attributes['targetEntity'] = 'STATEMENT';

        // Assert & Act
        $expectedErrorMessage = 'CustomField cannot be updated: Procedure with statements';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        // Act
        $this->sut->createCustomField($this->attributes);
    }

    public static function fromJsonProvider(): array
    {
        return [
            'TextField complete' => [
                TextField::class,
                ['fieldType' => 'text', 'name' => 'Notes', 'description' => 'Enter text', 'isRequired' => false],
            ],
            'MultiSelectField complete' => [
                MultiSelectField::class,
                ['fieldType' => 'multiSelect', 'name' => 'Priority', 'description' => 'Pick one', 'isRequired' => true, 'options' => []],
            ],
            'RadioButtonField complete' => [
                RadioButtonField::class,
                ['fieldType' => 'singleSelect', 'name' => 'Status', 'description' => 'Pick status', 'options' => []],
            ],
        ];
    }

    #[DataProvider('fromJsonProvider')]
    public function testFromJsonPopulatesFields(string $fieldClass, array $json): void
    {
        $field = new $fieldClass();
        $field->fromJson($json);

        static::assertSame($json['name'], $field->getName());
        static::assertSame($json['fieldType'], $field->getFieldType());
        static::assertSame($json['description'], $field->getDescription());
    }

    public static function fromJsonMissingKeyProvider(): array
    {
        $fullTextField        = ['fieldType' => 'text', 'name' => 'N', 'description' => 'D', 'isRequired' => false];
        $fullMultiSelectField = ['fieldType' => 'multiSelect', 'name' => 'N', 'description' => 'D', 'isRequired' => false, 'options' => []];
        $fullRadioButtonField = ['fieldType' => 'singleSelect', 'name' => 'N', 'description' => 'D', 'options' => []];

        return [
            'TextField missing fieldType'          => [TextField::class,       array_diff_key($fullTextField, ['fieldType' => ''])],
            'TextField missing name'               => [TextField::class,       array_diff_key($fullTextField, ['name' => ''])],
            'TextField missing description'        => [TextField::class,       array_diff_key($fullTextField, ['description' => ''])],
            'TextField missing isRequired'         => [TextField::class,       array_diff_key($fullTextField, ['isRequired' => ''])],
            'MultiSelectField missing fieldType'   => [MultiSelectField::class, array_diff_key($fullMultiSelectField, ['fieldType' => ''])],
            'MultiSelectField missing name'        => [MultiSelectField::class, array_diff_key($fullMultiSelectField, ['name' => ''])],
            'MultiSelectField missing description' => [MultiSelectField::class, array_diff_key($fullMultiSelectField, ['description' => ''])],
            'MultiSelectField missing isRequired'  => [MultiSelectField::class, array_diff_key($fullMultiSelectField, ['isRequired' => ''])],
            'MultiSelectField missing options'     => [MultiSelectField::class, array_diff_key($fullMultiSelectField, ['options' => ''])],
            'RadioButtonField missing fieldType'   => [RadioButtonField::class, array_diff_key($fullRadioButtonField, ['fieldType' => ''])],
            'RadioButtonField missing name'        => [RadioButtonField::class, array_diff_key($fullRadioButtonField, ['name' => ''])],
            'RadioButtonField missing description' => [RadioButtonField::class, array_diff_key($fullRadioButtonField, ['description' => ''])],
            'RadioButtonField missing options'     => [RadioButtonField::class, array_diff_key($fullRadioButtonField, ['options' => ''])],
        ];
    }

    #[DataProvider('fromJsonMissingKeyProvider')]
    public function testFromJsonThrowsOnMissingRequiredKey(string $fieldClass, array $json): void
    {
        $field = new $fieldClass();
        $this->expectException(\Throwable::class);
        $field->fromJson($json);
    }
}
