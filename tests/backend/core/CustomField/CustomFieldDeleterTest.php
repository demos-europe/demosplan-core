<?php

declare(strict_types=1);

namespace Tests\Core\CustomField;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Repository\CustomFieldConfigurationRepository;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldDeleter;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldUpdater;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Factory\EntityCustomFieldUsageStrategyFactory;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Strategy\EntityCustomFieldUsageRemovalStrategyInterface;
use PHPUnit\Framework\MockObject\MockObject;
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
}
