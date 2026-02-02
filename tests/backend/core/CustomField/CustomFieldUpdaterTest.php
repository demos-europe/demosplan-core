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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldUpdater;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Base\UnitTestCase;

class CustomFieldUpdaterTest extends UnitTestCase
{
    /**
     * @var CustomFieldUpdater|null
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldUpdater::class);
    }

    public function testUpdateCustomFieldWithValidNameUpdate(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1')->create();

        $entityId = $customField1->getId();
        $attributes = ['name' => 'Updated Field Name'];

        // Act
        $result = $this->sut->updateCustomField($entityId, $attributes);

        // Assert
        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertEquals('Updated Field Name', $result->getName());
    }

    public function testUpdateCustomFieldWithValidOptionsUpdate(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton(options: ['yellow', 'green'])->create();

        $updatedOptionId = $customField->getConfiguration()->getOptions()[0]->getId();
        $toBeDeletedOptionId = $customField->getConfiguration()->getOptions()[1]->getId();

        $newOptions = [
            ['id' => $updatedOptionId, 'label' => 'Updated Option 1'],
            ['label' => 'New Option 2'],  // New option without ID
        ];
        $attributes = ['options' => $newOptions];

        // Act
        $result = $this->sut->updateCustomField($customField->getId(), $attributes);

        // Assert
        $newOptionLabels = array_map(static fn ($option) => $option->getLabel(), $result->getOptions());
        $optionIds = array_map(static fn ($option) => $option->getId(), $result->getOptions());

        static::assertInstanceOf(CustomFieldInterface::class, $result);
        static::assertCount(2, $result->getOptions());
        static::assertContains($updatedOptionId, $optionIds);
        static::assertNotContains($toBeDeletedOptionId, $optionIds);
        static::assertContains('Updated Option 1', $newOptionLabels);
        static::assertContains('New Option 2', $newOptionLabels);
    }

    #[DataProvider('updateCustomFieldDataProvider')]
    public function testUpdateCustomFieldWithInvalidOptionsThrowsException($optionId1, $labelOption1, $labelOption2, $expectedErrorMessage): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1', options: ['green', 'yellow'])->create();

        $option1 = $customField->getConfiguration()->getOptions()[0];
        $option2 = $customField->getConfiguration()->getOptions()[1];
        $optionId1 = $optionId1 ?: $option1->getId();

        $entityId = $customField->getId();
        $attributes['options'] = [
            ['id' => $optionId1, 'label' => $labelOption1], // Empty label
            ['id' => $option2->getId(), 'label' => $labelOption2],
        ];

        // Assert & Act
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        $this->sut->updateCustomField($entityId, $attributes);
    }

    public static function updateCustomFieldDataProvider(): array
    {
        return [
            'emptyOptionLabels' => [
                'optionId1'             => null,
                'labelOption1'          => '',
                'labelOption2'          => 'New label',
                'expectedErrorMessage'  => 'All options must have a non-empty label',
            ],
            'duplicateOptionLabels' => [
                'optionId1'             => null,
                'labelOption1'          => 'New label',
                'labelOption2'          => 'New label',
                'expectedErrorMessage'  => 'Option labels must be unique',
            ],
            'invalidOptionId' => [
                'optionId1'             => 'non-existent-id',
                'labelOption1'          => 'Yellow',
                'labelOption2'          => 'Green',
                'expectedErrorMessage'  => 'Invalid option ID: non-existent-id',
            ],
        ];
    }

    public function testDeleteCustomFieldOptionsCallsRemoveOptionUsagesWithMultipleSegments(): void
    {
        // Arrange - Create custom field with 3 options for SEGMENT target entity
        $targetEntityClass = 'SEGMENT';
        $procedure = ProcedureFactory::createOne();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity($targetEntityClass)
            ->asRadioButton('TestField', options: ['Option1', 'Option2', 'Option3', 'Option4'])
            ->create();

        $customFieldId = $customField->getId();
        $option1 = $customField->getConfiguration()->getOptions()[0]; // Will be deleted
        $option2 = $customField->getConfiguration()->getOptions()[1]; // Will remain
        $option3 = $customField->getConfiguration()->getOptions()[2]; // Will be deleted
        $option4 = $customField->getConfiguration()->getOptions()[3]; // Will remain

        // Create 4 segments with different option values
        $segment1 = SegmentFactory::createOne(); // Uses Option1 (to be deleted)
        $segment2 = SegmentFactory::createOne(); // Uses Option2 (to remain)
        $segment3 = SegmentFactory::createOne(); // Uses Option1 (to be deleted)
        $segment4 = SegmentFactory::createOne(); // Uses Option3 (to be deleted)

        // Set up custom field values for each segment
        // Segment1 and Segment3 use Option1, Segment2 uses Option2, Segment4 uses Option3
        $customFieldValue1 = new CustomFieldValue();
        $customFieldValue1->setId($customFieldId);
        $customFieldValue1->setValue($option1->getId());

        $customFieldValue2 = new CustomFieldValue();
        $customFieldValue2->setId($customFieldId);
        $customFieldValue2->setValue($option2->getId());

        $customFieldValue3 = new CustomFieldValue();
        $customFieldValue3->setId($customFieldId);
        $customFieldValue3->setValue($option1->getId()); // Same as segment1

        $customFieldValue4 = new CustomFieldValue();
        $customFieldValue4->setId($customFieldId);
        $customFieldValue4->setValue($option3->getId());

        // Add custom field values to segments
        $customFieldsList1 = new CustomFieldValuesList();
        $customFieldsList1->addCustomFieldValue($customFieldValue1);
        $segment1->_real()->setCustomFields($customFieldsList1);
        $segment1->_save();

        $customFieldsList2 = new CustomFieldValuesList();
        $customFieldsList2->addCustomFieldValue($customFieldValue2);
        $segment2->_real()->setCustomFields($customFieldsList2);
        $segment2->_save();

        $customFieldsList3 = new CustomFieldValuesList();
        $customFieldsList3->addCustomFieldValue($customFieldValue3);
        $segment3->_real()->setCustomFields($customFieldsList3);
        $segment3->_save();

        $customFieldsList4 = new CustomFieldValuesList();
        $customFieldsList4->addCustomFieldValue($customFieldValue4);
        $segment4->_real()->setCustomFields($customFieldsList4);
        $segment4->_save();

        // Verify all segments have custom field values before deletion
        $segmentRepo = $this->getContainer()->get(SegmentRepository::class);
        $segmentsWithField = $segmentRepo->findSegmentsWithCustomField($customFieldId);
        self::assertCount(4, $segmentsWithField, 'Should have 4 segments with custom field before option deletion');

        // Act - Delete Option1 and Option3, keep Option2 and Option4
        $entityId = $customField->getId();
        $attributes['options'] = [
            ['id' => $option2->getId(), 'label' => $option2->getLabel()], // Empty label
            ['id' => $option4->getId(), 'label' => $option4->getLabel()],
        ];
        $this->sut->updateCustomField($entityId, $attributes);

        // Assert: Verify only segments with deleted options had their values removed
        $refreshedSegment1 = $segmentRepo->find($segment1->getId());
        $refreshedSegment2 = $segmentRepo->find($segment2->getId());
        $refreshedSegment3 = $segmentRepo->find($segment3->getId());
        $refreshedSegment4 = $segmentRepo->find($segment4->getId());

        // Segments 1, 3, and 4 should no longer have custom field values (used deleted options)
        $customFields1 = $refreshedSegment1->getCustomFields();
        $customFields3 = $refreshedSegment3->getCustomFields();
        $customFields4 = $refreshedSegment4->getCustomFields();

        self::assertNull(
            $customFields1 ? $customFields1->findById($customFieldId) : null,
            'Segment 1 should no longer have custom field value (used deleted Option1)'
        );
        self::assertNull(
            $customFields3 ? $customFields3->findById($customFieldId) : null,
            'Segment 3 should no longer have custom field value (used deleted Option1)'
        );
        self::assertNull(
            $customFields4 ? $customFields4->findById($customFieldId) : null,
            'Segment 4 should no longer have custom field value (used deleted Option3)'
        );

        // Segment 2 should still have its custom field value (uses non-deleted Option2)
        $customFields2 = $refreshedSegment2->getCustomFields();
        self::assertNotNull(
            $customFields2 ? $customFields2->findById($customFieldId) : null,
            'Segment 2 should still have custom field value (uses non-deleted Option2)'
        );

        // Verify custom field configuration still exists
        $repository = $this->getContainer()->get(CustomFieldConfigurationRepository::class);
        $customFieldAfterDeletion = $repository->find($customFieldId);
        self::assertNotNull($customFieldAfterDeletion, 'Custom field configuration should still exist');
        self::assertInstanceOf(CustomFieldConfiguration::class, $customFieldAfterDeletion);

        // Assert that only Option2 and Option4 remain in the configuration
        $remainingOptions = $customFieldAfterDeletion->getConfiguration()->getOptions();
        self::assertCount(2, $remainingOptions, 'Should have exactly 2 options remaining after deletion');

        $remainingOptionIds = array_map(fn ($option) => $option->getId(), $remainingOptions);
        self::assertContains($option2->getId(), $remainingOptionIds, 'Option2 should still exist in configuration');
        self::assertContains($option4->getId(), $remainingOptionIds, 'Option4 should still exist in configuration');
        self::assertNotContains($option1->getId(), $remainingOptionIds, 'Option1 should be deleted from configuration');
        self::assertNotContains($option3->getId(), $remainingOptionIds, 'Option3 should be deleted from configuration');
    }
    /**
     * Test validation fails when procedure HAS statements.
     */
    public function testValidationFailsWhenProcedureHasStatements(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $statementOriginal = StatementFactory::createOne(['procedure' => $procedure->_real()]);
        $statement = StatementFactory::createOne(
            [
                'procedure' => $procedure->_real(),
                'original' => $statementOriginal->_real(),
            ]);

        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1')->create();

        $entityId = $customField1->getId();
        $attributes = ['name' => 'Updated Field Name'];


        // Assert & Act
        $expectedErrorMessage = 'CustomField cannot be updated: Procedure with statements';
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage($expectedErrorMessage);

        // Act
        $result = $this->sut->updateCustomField($entityId, $attributes);

    }


}
