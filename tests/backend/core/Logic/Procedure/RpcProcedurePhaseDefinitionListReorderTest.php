<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Procedure;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedurePhaseDefinitionFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Tests\Base\RpcApiTest;

/**
 * Exercises the real HTTP -> RPC -> EDT ResourceType -> Doctrine flush path, unlike
 * ProcedurePhaseDefinitionAudienceReordererTest which only covers the extracted index math
 * with manually-built collections. This is the layer where a real bug slipped through: the
 * `orderInAudience` filter condition threw because the property was never marked filterable
 * on the ResourceType, and the extracted-reorderer test couldn't have caught that.
 */
class RpcProcedurePhaseDefinitionListReorderTest extends RpcApiTest
{
    public function testReorderPersistsNewOrderToDatabase(): void
    {
        $customer = $this->getCustomerReference(LoadCustomerData::SCHLESWIGHOLSTEIN);
        $user = $this->getUserReference(LoadUserData::TEST_USER_CITIZEN);

        $e1 = ProcedurePhaseDefinitionFactory::createOne(['audience' => 'internal', 'orderInAudience' => 1, 'customer' => $customer]);
        $e2 = ProcedurePhaseDefinitionFactory::createOne(['audience' => 'internal', 'orderInAudience' => 2, 'customer' => $customer]);
        $e3 = ProcedurePhaseDefinitionFactory::createOne(['audience' => 'internal', 'orderInAudience' => 3, 'customer' => $customer]);

        $this->enablePermissions(['area_customer_procedure_phase_definitions']);

        $responseBody = $this->executeRpcRequest(
            'procedurePhaseDefinitionList.reorder',
            'test-request-id',
            $user,
            [
                'phaseDefinitionId' => $e1->getId(),
                'newIndex'          => 2,
            ],
        );

        self::assertSame([], $responseBody, 'a successful reorder returns an empty result array');

        $this->entityManager->clear();
        $reloaded = $this->entityManager->getRepository(ProcedurePhaseDefinition::class)->findBy(
            ['customer' => $customer->getId(), 'audience' => 'internal'],
            ['orderInAudience' => 'ASC'],
        );
        $reloadedIds = array_map(static fn (ProcedurePhaseDefinition $phase) => $phase->getId(), $reloaded);

        // e1 moved from front to back of [e1, e2, e3] => [e2, e3, e1]
        self::assertSame([$e2->getId(), $e3->getId(), $e1->getId()], $reloadedIds);
    }

    public function testReorderRejectsMovingConfigurationPhase(): void
    {
        $customer = $this->getCustomerReference(LoadCustomerData::SCHLESWIGHOLSTEIN);
        $user = $this->getUserReference(LoadUserData::TEST_USER_CITIZEN);

        $configPhase = ProcedurePhaseDefinitionFactory::createOne(['audience' => 'external', 'orderInAudience' => 0, 'customer' => $customer]);
        ProcedurePhaseDefinitionFactory::createOne(['audience' => 'external', 'orderInAudience' => 1, 'customer' => $customer]);

        $this->enablePermissions(['area_customer_procedure_phase_definitions']);

        $responseBody = $this->executeRpcRequest(
            'procedurePhaseDefinitionList.reorder',
            'test-request-id',
            $user,
            [
                'phaseDefinitionId' => $configPhase->getId(),
                'newIndex'          => 0,
            ],
        );

        self::assertArrayHasKey('error', (array) $responseBody[0]);

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(ProcedurePhaseDefinition::class, $configPhase->getId());
        self::assertSame(0, $reloaded->getOrderInAudience());
    }
}
