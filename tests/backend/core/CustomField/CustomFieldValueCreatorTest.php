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
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\FunctionalTestCase;

/**
 * Tests the updateOrAddCustomFieldValues method which stores custom field values.
 * This tests VALUE validation and storage, not field configuration.
 *
 * Pattern: Similar to CustomFieldCreatorTest but for storing VALUES instead of creating DEFINITIONS.
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

    // ==========================================
    // SINGLE SELECT - SUCCESS CASES
    // ==========================================

    /**
     * Test adding a new single select value to an empty list.
     *
     * Tests: CustomFieldValueCreator.php line 31-86 (full flow)
     * Validates: SingleSelectFieldValueValidationStrategy.php line 35-51
     */
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
        static::assertInstanceOf(CustomFieldValuesList::class, $result);
        static::assertCount(1, $result->getCustomFieldsValues());

        $storedValue = $result->findById($customField->getId());
        static::assertNotNull($storedValue);
        static::assertEquals($selectedOptionId, $storedValue->getValue());
    }

    #[DataProvider('singleSelectValidationErrorDataProvider')]
    public function testUpdateOrAddCustomFieldValuesSingleSelectValidationErrors(
        array $testData,
        string $expectedErrorMessage,
    ): void {
        // Arrange
        $sourceEntity = 'PROCEDURE';
        $targetEntity = 'SEGMENT';

        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity($targetEntity);

        $customField = $factory->asRadioButton(
            $testData['fieldName'],
            options: $testData['fieldOptions']
        )->create();

        // Use actual field ID in test data if placeholder is present
        $value = $testData['value'];

        $newCustomFieldValuesData = [
            [
                'id'    => $customField->getId(),
                'value' => $value,
            ],
        ];

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        $this->sut->updateOrAddCustomFieldValues(
            new CustomFieldValuesList(),
            $newCustomFieldValuesData,
            $this->procedure->getId(),
            $sourceEntity,
            $targetEntity
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

    /**
     * Test various validation errors using data provider (like CustomFieldCreatorTest).
     *
     * Tests: CustomFieldValueCreator.php line 66 (validation call)
     * Tests: SingleSelectFieldValueValidationStrategy.php line 36-51
     * Tests: MultiSelectFieldValueValidationStrategy.php line 35-64
     */
    #[DataProvider('multiSelectValidationErrorDataProvider')]
    public function testUpdateOrAddCustomFieldValuesMultiSelectValidationErrors(
        array $testData,
        string $expectedErrorMessage
    ): void {
        // Arrange
        $fieldType = $testData['fieldType'];
        $targetEntity = 'STATEMENT';

        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity($targetEntity);

        $customField = $factory->asMultiSelect(
            $testData['fieldName'],
            options: $testData['fieldOptions'],
            isRequired: $testData['isRequired']
        )->create();

        // Use actual field ID in test data if placeholder is present
        $value = $testData['value'];

        $newCustomFieldValuesData = [
            [
                'id'    => $customField->getId(),
                'value' => $value,
            ],
        ];

        // Assert & Act
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

    public static function multiSelectValidationErrorDataProvider(): array
    {
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
                    'value'        => [123, 'valid-string'], // 123 is not a string
                ],
                'expectedErrorMessage' => 'Each element must be a string',
            ],

            'arrayWithBooleanElement' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Flags',
                    'fieldOptions' => ['Flag1', 'Flag2'],
                    'isRequired'   => false,
                    'value'        => [true, 'valid-string'], // true is not a string
                ],
                'expectedErrorMessage' => 'Each element must be a string',
            ],

            'arrayWithNullElement' => [
                'testData' => [
                    'fieldType'    => 'multiSelect',
                    'fieldName'    => 'Options',
                    'fieldOptions' => ['Opt1', 'Opt2'],
                    'isRequired'   => false,
                    'value'        => [null, 'valid-string'], // null is not a string
                ],
                'expectedErrorMessage' => 'Each element must be a string',
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
        ];
    }
}
