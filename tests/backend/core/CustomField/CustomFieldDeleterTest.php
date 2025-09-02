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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldDeleter;
use Tests\Base\UnitTestCase;

class CustomFieldDeleterTest extends UnitTestCase
{
    /**
     * @var CustomFieldDeleter|null
     */
    protected $sut;
    private $repository;
    // private EntityCustomFieldUsageStrategyFactory|MockObject $strategyFactoryMock;

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
}
