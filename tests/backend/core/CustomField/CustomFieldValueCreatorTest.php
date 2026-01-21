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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

/**
 * Tests the updateOrAddCustomFieldValues method which stores custom field values.
 * This tests VALUE validation and storage, not field configuration.
 */
class CustomFieldValueCreatorTest extends FunctionalTestCase
{
    /**
     * @var CustomFieldValueCreator|null
     */
    private $sut;
    private $procedure;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldValueCreator::class);
        $this->procedure = ProcedureFactory::createOne();
    }

    public function testAddNewSingleSelectValueSuccessfully(): void
    {
        // Arrange
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity('SEGMENT')
            ->asRadioButton('Priority', options: ['High', 'Medium', 'Low'])
            ->create();

        $selectedOptionId = $customField->getConfiguration()->getOptions()[0]->getId(); // 'High'

        $newCustomFieldValuesData = [
            [
                'id'    => $customField->getId(),
                'value' => $selectedOptionId,
            ],
        ];

        // Act
        $result = $this->sut->updateOrAddCustomFieldValues(
            new CustomFieldValuesList(), // Empty current list
            $newCustomFieldValuesData,
            $this->procedure->getId(),
            'PROCEDURE',
            'SEGMENT'
        );

        // Assert
        $storedValue = $result->findById($customField->getId());
        static::assertIsString($storedValue->getValue());
        static::assertEquals($selectedOptionId, $storedValue->getValue());
        $this->commonCustomFieldValueAssertions($customField, $result);
    }

    /**
     * Test adding a single selection to a multi select value.
     */
    public function testAddNewMultiSelectValueWithOneSelectionSuccessfully(): void
    {
        // Arrange
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity('STATEMENT')
            ->asMultiSelect('Tags', options: ['Environment', 'Traffic', 'Housing'], isRequired: false)
            ->create();

        $selectedOptionId = $customField->getConfiguration()->getOptions()[0]->getId(); // 'Environment'

        $newCustomFieldValuesData = [
            [
                'id'    => $customField->getId(),
                'value' => [$selectedOptionId], // Array with one element
            ],
        ];

        // Act
        $result = $this->sut->updateOrAddCustomFieldValues(
            new CustomFieldValuesList(), // Empty current list
            $newCustomFieldValuesData,
            $this->procedure->getId(),
            'PROCEDURE',
            'STATEMENT'
        );

        // Assert

        $storedValue = $result->findById($customField->getId());
        static::assertIsArray($storedValue->getValue());
        static::assertCount(1, $storedValue->getValue());
        static::assertContains($selectedOptionId, $storedValue->getValue());
        $this->commonCustomFieldValueAssertions($customField, $result);
    }

    private function commonCustomFieldValueAssertions(CustomFieldConfiguration|Proxy $customField, CustomFieldValuesList $result)
    {
        // Assert
        static::assertInstanceOf(CustomFieldValuesList::class, $result);
        static::assertCount(1, $result->getCustomFieldsValues());

        $storedValue = $result->findById($customField->getId());
        static::assertNotNull($storedValue);
    }

    /**
     * Test adding multiple selections to a multi select value.
     */
    public function testAddNewMultiSelectValueWithTwoSelectionsSuccessfully(): void
    {
        // Arrange
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity('STATEMENT')
            ->asMultiSelect('Favourite pets', options: ['Cat', 'Dog', 'Hamster', 'Parrot'], isRequired: false)
            ->create();

        $selectedOption1Id = $customField->getConfiguration()->getOptions()[0]->getId(); // 'Cat'
        $selectedOption2Id = $customField->getConfiguration()->getOptions()[2]->getId(); // 'Hamster'

        $newCustomFieldValuesData = [
            [
                'id'    => $customField->getId(),
                'value' => [$selectedOption1Id, $selectedOption2Id], // Array with two elements
            ],
        ];

        // Act
        $result = $this->sut->updateOrAddCustomFieldValues(
            new CustomFieldValuesList(), // Empty current list
            $newCustomFieldValuesData,
            $this->procedure->getId(),
            'PROCEDURE',
            'STATEMENT'
        );

        // Assert
        static::assertInstanceOf(CustomFieldValuesList::class, $result);
        static::assertCount(1, $result->getCustomFieldsValues());

        $storedValue = $result->findById($customField->getId());
        static::assertNotNull($storedValue);
        static::assertIsArray($storedValue->getValue());
        static::assertCount(2, $storedValue->getValue());
        static::assertContains($selectedOption1Id, $storedValue->getValue());
        static::assertContains($selectedOption2Id, $storedValue->getValue());
    }

    /**
     * Helper method to test validation errors for custom field values.
     */
    private function assertValidationError(
        array $testData,
        string $expectedErrorMessage,
        string $targetEntity,
        callable $fieldCreator,
    ): void {
        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity($targetEntity);

        $customField = $fieldCreator($factory, $testData);

        $newCustomFieldValuesData = [
            [
                'id'    => $customField->getId(),
                'value' => $testData['value'],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        $this->sut->updateOrAddCustomFieldValues(
            new CustomFieldValuesList(),
            $newCustomFieldValuesData,
            $this->procedure->getId(),
            'PROCEDURE',
            $targetEntity
        );
    }

    #[DataProvider('singleSelectValidationErrorDataProvider')]
    public function testUpdateOrAddCustomFieldValuesSingleSelectValidationErrors(
        array $testData,
        string $expectedErrorMessage,
    ): void {
        $this->assertValidationError(
            $testData,
            $expectedErrorMessage,
            'SEGMENT',
            fn ($factory, $data) => $factory->asRadioButton(
                $data['fieldName'],
                options: $data['fieldOptions']
            )->create()
        );
    }

    public static function singleSelectValidationErrorDataProvider(): array
    {
        return [
            'arrayInsteadOfString' => [
                'testData' => [
                    'fieldType'    => 'singleSelect',
                    'fieldName'    => 'Priority',
                    'fieldOptions' => ['High', 'Medium', 'Low'],
                    'value'        => ['invalid-array'], // Should be string, not array
                ],
                'expectedErrorMessage' => 'SingleSelect must be a string for CustomFieldId',
            ],

            'numericValue' => [
                'testData' => [
                    'fieldType'    => 'singleSelect',
                    'fieldName'    => 'Status',
                    'fieldOptions' => ['Active', 'Inactive'],
                    'value'        => 123, // Should be string, not integer
                ],
                'expectedErrorMessage' => 'SingleSelect must be a string for CustomFieldId',
            ],

            'invalidOptionId' => [
                'testData' => [
                    'fieldType'    => 'singleSelect',
                    'fieldName'    => 'Category',
                    'fieldOptions' => ['Cat1', 'Cat2', 'Cat3'],
                    'value'        => 'non-existent-option-uuid-12345',
                ],
                'expectedErrorMessage' => 'SingleSelect invalid option id "non-existent-option-uuid-12345" for CustomFieldId',
            ],
        ];
    }

    #[DataProvider('multiSelectValidationErrorDataProvider')]
    public function testUpdateOrAddCustomFieldValuesMultiSelectValidationErrors(
        array $testData,
        string $expectedErrorMessage,
    ): void {
        $this->assertValidationError(
            $testData,
            $expectedErrorMessage,
            'STATEMENT',
            fn ($factory, $data) => $factory->asMultiSelect(
                $data['fieldName'],
                options: $data['fieldOptions'],
                isRequired: $data['isRequired']
            )->create()
        );
    }

    public static function multiSelectValidationErrorDataProvider(): array
    {
        $expectedErrorMessageString = 'Each element must be a string';

        return [
            'emptyArrayWhenRequired' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'RequiredTags',
                    'fieldOptions' => ['Tag1', 'Tag2', 'Tag3'],
                    'isRequired'   => true,
                    'value'        => [], // Empty array not allowed for required
                ],
                'expectedErrorMessage' => 'Required fields must have at least one selection',
            ],

            'arrayWithIntegerElement' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Items',
                    'fieldOptions' => ['Item1', 'Item2'],
                    'isRequired'   => false,
                    'value'        => [123, '1234'], // 123 is not a string
                ],
                'expectedErrorMessage' => $expectedErrorMessageString,
            ],

            'arrayWithBooleanElement' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Flags',
                    'fieldOptions' => ['Flag1', 'Flag2'],
                    'isRequired'   => false,
                    'value'        => [true, '5678'], // true is not a string
                ],
                'expectedErrorMessage' => $expectedErrorMessageString,
            ],

            'arrayWithNullElement' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Options',
                    'fieldOptions' => ['Opt1', 'Opt2'],
                    'isRequired'   => false,
                    'value'        => [null, 'valid-string'], // null is not a string
                ],
                'expectedErrorMessage' => $expectedErrorMessageString,
            ],

            'invalidOptionIdInArray' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Topics',
                    'fieldOptions' => ['Topic1', 'Topic2', 'Topic3'],
                    'isRequired'   => false,
                    'value'        => ['non-existent-uuid-abc123'], // Invalid UUID
                ],
                'expectedErrorMessage' => 'Each element must be a valid option ID',
            ],

            'mixedValidAndInvalidOptionIds' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Categories',
                    'fieldOptions' => ['CatA', 'CatB'],
                    'isRequired'   => false,
                    'value'        => ['PLACEHOLDER_VALID_ID', 'invalid-uuid-xyz'], // Need to handle valid ID
                ],
                'expectedErrorMessage' => 'Each element must be a valid option ID',
            ],
            'notArrayAsValue' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Categories',
                    'fieldOptions' => ['CatA', 'CatB'],
                    'isRequired'   => false,
                    'value'        => 'My own value',
                ],
                'expectedErrorMessage' => 'Value must be an array',
            ],
        ];
    }
}
