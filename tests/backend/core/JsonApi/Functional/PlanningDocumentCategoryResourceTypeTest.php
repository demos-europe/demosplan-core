<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class PlanningDocumentCategoryResourceTypeTest extends JsonApiTest
{
    public function testListWithGuestWithoutProcedure(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_GUEST);
        $this->enablePermissions(['area_documents']);
        $this->executeListRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $user,
            null,
            Response::HTTP_OK,
            []
        );
    }

    public function testListWithGuest(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_GUEST);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE_IN_PUBLIC_PARTICIPATION_PHASE);
        $this->enablePermissions(['area_documents']);
        $this->executeListRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $user,
            $procedure,
            Response::HTTP_OK,
            []
        );
    }

    public function testListWithPlanner(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->enablePermissions(['area_documents']);
        $responseBody = $this->executeListRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $user,
            $procedure
        );

        self::assertCount(7, $responseBody['data']);
    }

    public function testListWithPlannerAndFilter(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->enablePermissions(['area_documents']);
        $responseBody = $this->executeListRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $user,
            $procedure,
            Response::HTTP_OK,
            ['filter' => [
                'enabledElements' => [
                    'condition' => [
                        'path'  => 'enabled',
                        'value' => true,
                    ],
                ],
            ]]);

        self::assertCount(7, $responseBody['data']);
    }
}
