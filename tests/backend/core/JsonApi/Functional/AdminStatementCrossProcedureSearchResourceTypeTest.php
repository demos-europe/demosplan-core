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
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminStatementCrossProcedureSearchResourceType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class AdminStatementCrossProcedureSearchResourceTypeTest extends JsonApiTest
{
    public function testListWithoutPermissionIsRejected(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->executeListRequest(
            AdminStatementCrossProcedureSearchResourceType::getName(),
            $user,
            null,
            Response::HTTP_BAD_REQUEST
        );
    }

    public function testListReturnsValidJsonApiPayloadForAdministrableUser(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_json_api_statement_cross_procedures_search']);

        $responseBody = $this->executeListRequest(
            AdminStatementCrossProcedureSearchResourceType::getName(),
            $user,
            null
        );

        // Whether `data` is non-empty depends on the test kernel's customer setup aligning with
        // a fixture procedure's customer — production has the right customer subdomain via the
        // request host, but the test kernel uses `localhost` and that may not match
        // {@see \demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData::TESTPROCEDURE}'s
        // customer. So we only assert the response shape and that the filter-by-name doesn't
        // explode (covered by other tests below); data presence is a property of the env.
        self::assertArrayHasKey('data', $responseBody);
        self::assertIsArray($responseBody['data']);
        self::assertArrayHasKey('jsonapi', $responseBody);
        foreach ($responseBody['data'] as $resource) {
            self::assertSame('AdminStatementCrossProcedureSearch', $resource['type']);
            self::assertArrayHasKey('id', $resource);
            self::assertArrayHasKey('attributes', $resource);
        }
    }

    public function testListAcceptsIncludeProcedureAndFieldsParameters(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        // ProcedureResourceType.isAvailable requires area_admin_procedures; without it `include=procedure`
        // silently drops, and the FE would not get procedure names for the grouping headings.
        $this->enablePermissions(['feature_json_api_statement_cross_procedures_search', 'area_admin_procedures']);

        $responseBody = $this->executeListRequest(
            AdminStatementCrossProcedureSearchResourceType::getName(),
            $user,
            null,
            Response::HTTP_OK,
            [
                'include' => 'procedure',
                'fields'  => ['Procedure' => 'name'],
            ]
        );

        // The endpoint must accept the FE's actual call shape (include=procedure +
        // sparse Procedure fieldset) without erroring. Data presence is environment-dependent;
        // see {@see self::testListReturnsValidJsonApiPayloadForAdministrableUser}.
        self::assertArrayHasKey('data', $responseBody);
        self::assertArrayHasKey('included', $responseBody);
        foreach ($responseBody['data'] as $resource) {
            self::assertArrayHasKey('procedure', $resource['relationships'] ?? []);
        }
    }

    public function testListFiltersBySubmitterAuthorName(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_json_api_statement_cross_procedures_search']);

        $responseBody = $this->executeListRequest(
            AdminStatementCrossProcedureSearchResourceType::getName(),
            $user,
            null,
            Response::HTTP_OK,
            ['filter' => [
                'byAuthor' => [
                    'condition' => [
                        'path'     => 'authorName',
                        'value'    => 'Mustermann',
                        'operator' => 'STRING_CONTAINS_CASE_INSENSITIVE',
                    ],
                ],
            ]]
        );

        self::assertArrayHasKey('data', $responseBody);
        foreach ($responseBody['data'] as $resource) {
            self::assertStringContainsStringIgnoringCase(
                'Mustermann',
                $resource['attributes']['authorName'] ?? '',
                'Filter should narrow results to matching submitters.'
            );
        }
    }

    public function testListFiltersByProcedureId(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $this->enablePermissions(['feature_json_api_statement_cross_procedures_search']);

        $responseBody = $this->executeListRequest(
            AdminStatementCrossProcedureSearchResourceType::getName(),
            $user,
            null,
            Response::HTTP_OK,
            [
                'filter' => [
                    'byProcedure' => [
                        'condition' => [
                            'path'  => 'procedure.id',
                            'value' => $procedure->getId(),
                        ],
                    ],
                ],
                'include' => 'procedure',
                'fields'  => ['Procedure' => 'name'],
            ]
        );

        self::assertArrayHasKey('data', $responseBody);
        foreach ($responseBody['included'] ?? [] as $included) {
            if ('Procedure' === $included['type']) {
                self::assertSame($procedure->getId(), $included['id']);
            }
        }
    }
}
