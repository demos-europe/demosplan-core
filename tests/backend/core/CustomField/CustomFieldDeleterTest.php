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

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldDeleter;
use Tests\Base\UnitTestCase;

class CustomFieldDeleterTest extends UnitTestCase
{
    /**
     * @var CustomFieldDeleter|null
     */
    protected $sut;
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldDeleter::class);
        $this->repository = $this->getContainer()->get(CustomFieldConfigurationRepository::class);
    }

    public function testDeleteCustomFieldSuccessfully(): void
    {
        // Arrange
        $procedure = ProcedureFactory::createOne();
        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->asRadioButton('Color1')->create();

        $entityId = $customField1->getId();

        // Verify entity exists before deletion
        $entityBeforeDeletion = $this->repository->find($entityId);
        self::assertNotNull($entityBeforeDeletion);

        // Act
        $this->sut->deleteCustomField($entityId);

        // Assert: Verify entity no longer exists in database
        $entityAfterDeletion = $this->repository->find($entityId);
        self::assertNull($entityAfterDeletion, 'CustomFieldConfiguration should be deleted from database');
    }

    public function testDeleteCustomFieldThrowsExceptionWhenNotFound(): void
    {
        // Arrange
        $entityId = 'non-existent-id';

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("CustomFieldConfiguration with ID 'non-existent-id' not found");

        $this->sut->deleteCustomField($entityId);
    }

    public function testDeleteCustomFieldHandlesStrategyFactoryException(): void
    {
        // Arrange
        $targetEntityClass = 'UnsupportedEntity';
        $procedure = ProcedureFactory::createOne();
        $customField1 = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity($targetEntityClass)
            ->asRadioButton('Color1')->create();

        $entityId = $customField1->getId();

        // Act & Assert
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No entity usage removal strategy found for target entity class: UnsupportedEntity');

        $this->sut->deleteCustomField($entityId);
    }

    public function testDeleteCustomFieldCallsRemoveUsagesWithCorrectEntityId(): void
    {
        // Arrange - Create custom field for SEGMENT target entity
        $targetEntityClass = 'SEGMENT';
        $procedure = ProcedureFactory::createOne();
        $customField = CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($procedure->_real())
            ->withRelatedTargetEntity($targetEntityClass) // Target entity is SEGMENT
            ->asRadioButton('TestField', options: ['Option1', 'Option2'])
            ->create();

        $customFieldId = $customField->getId();
        $option1 = $customField->getConfiguration()->getOptions()[0];
        $option2 = $customField->getConfiguration()->getOptions()[1];

        // Create segments that use this custom field
        $segment1 = SegmentFactory::createOne();
        $segment2 = SegmentFactory::createOne();

        // Add custom field values to segments (simulating actual usage)
        $customFieldValue1 = new CustomFieldValue();
        $customFieldValue1->setId($customFieldId);
        $customFieldValue1->setValue($option1->getId());

        $customFieldValue2 = new CustomFieldValue();
        $customFieldValue2->setId($customFieldId);
        $customFieldValue2->setValue($option2->getId());

        $customFieldsList1 = new CustomFieldValuesList();
        $customFieldsList1->addCustomFieldValue($customFieldValue1);
        $segment1->_real()->setCustomFields($customFieldsList1);
        $segment1->_save();

        $customFieldsList2 = new CustomFieldValuesList();
        $customFieldsList2->addCustomFieldValue($customFieldValue2);
        $segment2->_real()->setCustomFields($customFieldsList2);

        $segment2->_save();

        // Verify segments have the custom field values before deletion
        $segmentRepo = $this->getContainer()->get(SegmentRepository::class);
        $segmentsWithField = $segmentRepo->findSegmentsWithCustomField($customFieldId);
        self::assertCount(2, $segmentsWithField, 'Should have 2 segments with custom field before deletion');

        // Act
        $this->sut->deleteCustomField($customFieldId);

        // Assert: Verify custom field usages were removed from segments

        $segmentsWithFieldAfterDeletion = $segmentRepo->findSegmentsWithCustomField($customFieldId);
        self::assertCount(0, $segmentsWithFieldAfterDeletion, 'No segments should have the custom field after deletion');

        // Additional verification: Check that specific segments no longer have the field
        $refreshedSegment1 = $segmentRepo->find($segment1->getId());
        $refreshedSegment2 = $segmentRepo->find($segment2->getId());

        $customFields1 = $refreshedSegment1->getCustomFields();
        $customFields2 = $refreshedSegment2->getCustomFields();

        self::assertNull(
            $customFields1 ? $customFields1->findById($customFieldId) : null,
            'Segment 1 should no longer have the custom field value'
        );
        self::assertNull(
            $customFields2 ? $customFields2->findById($customFieldId) : null,
            'Segment 2 should no longer have the custom field value'
        );
    }
}
