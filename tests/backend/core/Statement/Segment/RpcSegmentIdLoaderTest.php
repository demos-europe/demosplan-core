<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use stdClass;
use Tests\Base\RpcApiTest;

/**
 * Integration test for the segment.load.id RPC method used by the SegmentsList
 * frontend to resolve the full (unpaginated) segment ID set for bulk editing.
 *
 * Guards against filter paths referencing resource type properties that are
 * not registered in getProperties(): listEntityIdentifiers() validates every
 * condition path against that list and rejects unknown ones, so an invalid
 * procedure condition breaks the whole method with a JSON-RPC server error
 * instead of returning segment IDs.
 */
class RpcSegmentIdLoaderTest extends RpcApiTest
{
    private const REQUIRED_PERMISSIONS = [
        'feature_json_api_statement_segment',
        'area_admin_statement_list',
    ];

    public function testLoadSegmentIdsReturnsAllSegmentsOfProcedure(): void
    {
        // Arrange
        $user = $this->createPlanner();
        $procedure = $this->createProcedureFor($user);
        $segment1 = $this->createSegmentIn($procedure);
        $segment2 = $this->createSegmentIn($procedure);
        $this->enablePermissions(self::REQUIRED_PERMISSIONS);

        // Act
        $segmentIds = $this->loadSegmentIds($user, $procedure, new stdClass());

        // Assert
        self::assertEqualsCanonicalizing(
            [$segment1->getId(), $segment2->getId()],
            $segmentIds
        );
    }

    public function testLoadSegmentIdsAppliesFilterAndProcedureConditionPath(): void
    {
        // Arrange
        $user = $this->createPlanner();
        $procedure = $this->createProcedureFor($user);
        $wanted = $this->createSegmentIn($procedure);
        $this->createSegmentIn($procedure);
        $this->enablePermissions(self::REQUIRED_PERMISSIONS);

        $filter = (object) [
            'externIdCondition' => (object) [
                'condition' => (object) [
                    'path'     => 'externId',
                    'value'    => $wanted->getExternId(),
                    'operator' => '=',
                ],
            ],
        ];

        // Act
        $segmentIds = $this->loadSegmentIds($user, $procedure, $filter);

        // Assert
        self::assertSame([$wanted->getId()], $segmentIds);
    }

    public function testLoadSegmentIdsExcludesSegmentsOfOtherProcedures(): void
    {
        // Arrange
        $user = $this->createPlanner();
        $procedure = $this->createProcedureFor($user);
        $ownSegment = $this->createSegmentIn($procedure);
        $foreignSegment = $this->createSegmentIn($this->createProcedureFor($user));
        $this->enablePermissions(self::REQUIRED_PERMISSIONS);

        // Act
        $segmentIds = $this->loadSegmentIds($user, $procedure, new stdClass());

        // Assert
        self::assertSame([$ownSegment->getId()], $segmentIds);
        self::assertNotContains($foreignSegment->getId(), $segmentIds);
    }

    private function createPlanner(): User
    {
        $orga = OrgaFactory::createOne();
        $user = UserFactory::createOne(['orga' => $orga, 'deleted' => false]);
        $orga->_real()->addUser($user->_real());
        $orga->_save();

        return $user->_real();
    }

    private function createProcedureFor(User $user): Procedure
    {
        $customer = self::getContainer()->get(CustomerService::class)->getCurrentCustomer();
        $procedure = ProcedureFactory::createOne([
            'orga'     => $user->getOrga(),
            'customer' => $customer,
        ]);
        ProcedureSettingsFactory::createOne(['procedure' => $procedure]);

        return $procedure->_real();
    }

    private function createSegmentIn(Procedure $procedure): Segment
    {
        return SegmentFactory::createOne(['procedure' => $procedure])->_real();
    }

    /**
     * @return list<string>
     */
    private function loadSegmentIds(User $user, Procedure $procedure, stdClass $filter): array
    {
        $responseBody = $this->executeRpcRequest(
            'segment.load.id',
            'segment-id-request',
            $user,
            ['filter' => $filter],
            $procedure
        );

        self::assertArrayNotHasKey('error', $responseBody[0]);

        return $responseBody[0]['result'];
    }
}
