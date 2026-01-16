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
}
