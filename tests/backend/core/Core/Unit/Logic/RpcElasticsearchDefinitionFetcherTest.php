<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Project\Core\Unit\Logic;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use Tests\Base\RpcApiTest;

class RpcElasticsearchDefinitionFetcherTest extends RpcApiTest
{
    public function testExecute(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);

        $responseBody = $this->executeRpcRequest(
            'elasticsearchFieldDefinition.provide',
            'someId',
            $user,
            [
                'entity'      => 'statementSegment',
                'function'    => 'search',
                'accessGroup' => 'planner',
            ],
            $procedure
        );

        self::assertIsArray($responseBody);
        self::assertArrayHasKey('result', $responseBody[0]);
        self::assertGreaterThan(0, count($responseBody[0]['result']));
    }
}
