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
        string $expectedErrorMessage
    ): void {
        // Arrange
        $sourceEntity = 'PROCEDURE';
        $targetEntity = 'SEGMENT';

        $factory = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure->_real())
            ->withRelatedTargetEntity($targetEntity);

        $customField = $factory->asRadioButton(
            $testData['fieldName'] ,
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
            ]
        ];
    }




}
