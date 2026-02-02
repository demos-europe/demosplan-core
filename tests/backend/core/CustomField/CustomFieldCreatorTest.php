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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
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

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldCreator::class);
        $this->procedure = ProcedureFactory::createOne();
    }

    /**
     * Test SingleSelect field creation with all attributes properly set.
     */
    public function testCreateSingleSelectFieldSuccessfully(): void
    {
        // Arrange
        $attributes = [
            'fieldType'   => 'singleSelect',
            'name'        => 'Priority Level',
            'description' => 'Select priority level for this item',
            'options'     => [
                ['label' => 'High'],
                ['label' => 'Medium'],
                ['label' => 'Low'],
            ],
            'sourceEntity'   => 'PROCEDURE',
            'sourceEntityId' => $this->procedure->getId(),
            'targetEntity'   => 'SEGMENT',
        ];

        // Act
        $result = $this->sut->createCustomField($attributes);

        // Assert - Test the superficial layer behavior
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertInstanceOf(RadioButtonField::class, $result);
        static::assertEquals('Priority Level', $result->getName());
        static::assertEquals('Select priority level for this item', $result->getDescription());
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
        // Arrange
        $attributes = [
            'fieldType'   => 'multiSelect',
            'name'        => 'Categories',
            'description' => 'Select applicable categories',
            'isRequired'  => true,
            'options'     => [
                ['label' => 'Environment'],
                ['label' => 'Traffic'],
                ['label' => 'Housing'],
            ],
            'sourceEntity'   => 'PROCEDURE',
            'sourceEntityId' => $this->procedure->getId(),
            'targetEntity'   => 'STATEMENT',
        ];

        // Act
        $result = $this->sut->createCustomField($attributes);

        // Assert
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertInstanceOf(MultiSelectField::class, $result);
        static::assertEquals('Categories', $result->getName());
        static::assertEquals('Select applicable categories', $result->getDescription());
        static::assertEquals('multiSelect', $result->getFieldType());

        // Test MultiSelect specific behavior - has isRequired
        static::assertTrue($result->getRequired() ?? false);

        $options = $result->getOptions();
        static::assertCount(3, $options);
        static::assertEquals('Environment', $options[0]->getLabel());
        static::assertEquals('Traffic', $options[1]->getLabel());
        static::assertEquals('Housing', $options[2]->getLabel());

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
                    'targetEntity' => 'STATEMENT', // Wrong target for singleSelect
                    'name'         => self::TEST_FIELD_NAME,
                    'description'  => self::TEST_DESCRIPTION,
                    'options'      => self::TEST_OPTIONS_ONLY_ONE_TWO,
                ],
                'expectedErrorMessage' => 'The target entity "STATEMENT" does not match the expected target entity "SEGMENT" for source entity "PROCEDURE".',
            ],
            'multiSelectInvalidSourceTargetEntityCombination' => [
                'attributes' => [
                    'fieldType'    => 'multiSelect',
                    'sourceEntity' => 'PROCEDURE',
                    'targetEntity' => 'SEGMENT', // Wrong target for multiSelect
                    'name'         => self::TEST_FIELD_NAME,
                    'description'  => self::TEST_DESCRIPTION,
                    'options'      => self::TEST_OPTIONS_ONLY_ONE_TWO,
                ],
                'expectedErrorMessage' => 'The target entity "SEGMENT" does not match the expected target entity "STATEMENT" for source entity "PROCEDURE".',
            ],
        ];
    }

    public function testValidationFailsWhenProcedureHasStatements(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statementOriginal = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement = StatementFactory::createOne(
            [
                'procedure' => $procedure->_real(),
                'original'  => $statementOriginal->_real(),
            ]);
        $attributes = [
            'fieldType'   => 'multiSelect',
            'name'        => 'Priority Level',
            'description' => 'Select priority level for this item',
            'isRequired'  => false,
            'options'     => [
                ['label' => 'High'],
                ['label' => 'Medium'],
                ['label' => 'Low'],
            ],
            'sourceEntity'   => 'PROCEDURE',
            'sourceEntityId' => $procedure->getId(),
            'targetEntity'   => 'STATEMENT',
        ];

        // Assert & Act
        $expectedErrorMessage = 'CustomField cannot be updated: Procedure with statements';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        // Act
        $result = $this->sut->createCustomField($attributes);
    }
}
