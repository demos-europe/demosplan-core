<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\CustomField\RadioButtonField;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class ProcedureDeleterTest extends FunctionalTestCase
{
    private null|Procedure|Proxy $testProcedure;

    private ?array $testProcedures;

    /** @var ProcedureDeleter */
    protected $sut;

    /** @var SqlQueriesService */
    protected $queriesService;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ProcedureDeleter::class);
        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testProcedure = ProcedureFactory::createOne();
        $this->testProcedures = ProcedureFactory::createMany(2);
    }

    public function testDeleteProcedure(): void
    {
        $entriesIds = [];
        foreach ($this->getEntries(Procedure::class) as $procedure) {
            $entriesIds[] = $procedure->getId();
        }

        $this->assertEquals(in_array($this->testProcedure->getId(), $entriesIds), 1);

        $totalAmountOfProceduresBeforeDeletion = $this->countEntries(Procedure::class);

        $this->sut->deleteProcedures([$this->testProcedure->getId()], false);

        static::assertSame($totalAmountOfProceduresBeforeDeletion - 1, $this->countEntries(Procedure::class));
    }

    public function testDeleteProcedures(): void
    {
        $ids = [];
        foreach ($this->testProcedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        $entriesIds = [];
        foreach ($this->getEntries(Procedure::class) as $procedure) {
            $entriesIds[] = $procedure->getId();
        }
        $this->assertEquals(count(array_intersect($ids, $entriesIds)), 2);

        $totalAmountOfProceduresBeforeDeletion = $this->countEntries(Procedure::class);

        $this->sut->deleteProcedures($ids, false);

        static::assertSame($totalAmountOfProceduresBeforeDeletion - 2, $this->countEntries(Procedure::class));
    }

    public function testProcedureDeleteCustomFields(): void {
        // Arrange
        $ids = [];
        foreach ($this->testProcedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        // Create custom fields for both procedure and procedure template entity classes
        $customFieldsCount = 4;
        $customFieldIds = [];

        foreach ($this->testProcedures as $procedure) {
            // Create PROCEDURE custom fields
            $customField1 = $this->createCustomField($procedure, 'PROCEDURE', 'Favourite Color', 'Your favourite color', ['Blue', 'Orange', 'Green']);
            $customField2 = $this->createCustomField($procedure, 'PROCEDURE', 'Favourite Food', 'Your favourite food', ['Pizza', 'Sushi', 'Bread']);
        }

        // Verify custom fields were created
        $this->assertEquals($customFieldsCount, $this->countEntries(CustomFieldConfiguration::class));

        // Act
        $this->sut->deleteProcedures($ids, false);

        // Assert
        // Verify custom fields were deleted - we should have 0 custom fields for the deleted procedures
        $remainingCustomFields = $this->getEntityManager()
            ->createQueryBuilder()
            ->select('c')
            ->from(CustomFieldConfiguration::class, 'c')
            ->where('c.sourceEntityId IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();

        $this->assertCount(0, $remainingCustomFields, "Custom fields should have been deleted for both PROCEDURE and PROCEDURE_TEMPLATE entity classes");
    }


    private function createCustomField($procedure, string $sourceEntityClass, string $name, string $description, array $options): CustomFieldConfiguration
    {
        $radioButton = new RadioButtonField();
        $radioButton->setName($name);
        $radioButton->setDescription($description);
        $radioButton->setFieldType('singleSelect');
        $radioButton->setOptions($options);

        return CustomFieldConfigurationFactory::createOne([
            'sourceEntityClass' => $sourceEntityClass,
            'sourceEntityId'    => $procedure->getId(),
            'targetEntityClass' => 'SEGMENT',
            'configuration'     => $radioButton,
        ])->_real();
    }
}
