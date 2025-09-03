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
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldCreator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\UnitTestCase;

class CustomFieldCreatorTest extends UnitTestCase
{
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
        static::assertEquals('singleSelect', $result->getType());

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
        static::assertEquals('multiSelect', $result->getType());

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
            'sourceEntityId' => $attributes['sourceEntityId']?:$this->procedure->getId(),
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
                    'name'        => 'Test Field',
                    'description' => 'Test',
                    'options'     => [['label' => 'One'], ['label' => 'Two']],
                ],
                'expectedErrorMessage' => 'No validator found for field type: invalidType',
            ],
            'invalidSourceEntityId' => [
                'attributes' => [
                    'fieldType'   => 'singleSelect',
                    'sourceEntityId' => 'invalid-id',
                    'sourceEntity' => 'PROCEDURE',
                    'targetEntity' => 'SEGMENT', // Wrong target for singleSelect
                    'name'        => 'Test Field',
                    'description' => 'Test',
                    'options'     => [['label' => 'One'], ['label' => 'Two']],
                ],
                'expectedErrorMessage' => 'The sourceEntityId "invalid-id" was not found in the sourceEntity "PROCEDURE"'
            ],
            'singleSelectInvalidSourceTargetEntityCombination' => [
                'attributes' => [
                    'fieldType'    => 'singleSelect',
                    'sourceEntity' => 'PROCEDURE',
                    'targetEntity' => 'STATEMENT', // Wrong target for singleSelect
                    'name'         => 'Test Field',
                    'description'  => 'Test',
                    'options'      => [['label' => 'Only One'], ['label' => 'Two']],
                ],
                'expectedErrorMessage' => 'The target entity "STATEMENT" does not match the expected target entity "SEGMENT" for source entity "PROCEDURE".',
            ],
            'multiSelectInvalidSourceTargetEntityCombination' => [
                'attributes' => [
                    'fieldType'    => 'multiSelect',
                    'sourceEntity' => 'PROCEDURE',
                    'targetEntity' => 'SEGMENT', // Wrong target for multiSelect
                    'name'         => 'Test Field',
                    'description'  => 'Test',
                    'options'      => [['label' => 'Only One'], ['label' => 'Two']],
                ],
                'expectedErrorMessage' => 'The target entity "SEGMENT" does not match the expected target entity "STATEMENT" for source entity "PROCEDURE".',
            ],
        ];
    }
}
