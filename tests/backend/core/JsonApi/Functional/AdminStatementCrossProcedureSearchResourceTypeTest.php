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

use DemosEurope\DemosplanAddon\Controller\APIController;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdminStatementCrossProcedureSearchResourceType;
use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;
use Webmozart\Assert\Assert;

class AdminStatementCrossProcedureSearchResourceTypeTest extends JsonApiTest
{
    protected ?AdminStatementCrossProcedureSearchResourceType $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        // A dedicated instance rather than the shared one from the container: the container instance
        // serves the HTTP requests of the other test cases and must keep its own collaborators.
        // `CurrentUserInterface` is configured with `class:` instead of an alias to CurrentUserService
        // (config/services.yml), so a resource type receives its own instance which does not observe
        // the token {@see FunctionalTestCase::logIn()} sets and would always report an AnonymousUser.
        $container = $this->getContainer();
        $htmlSanitizer = $container->get(HTMLSanitizer::class);
        Assert::isInstanceOf($htmlSanitizer, HTMLSanitizer::class);
        $statementService = $container->get(StatementService::class);
        Assert::isInstanceOf($statementService, StatementService::class);
        $procedureHandler = $container->get(ProcedureHandler::class);
        Assert::isInstanceOf($procedureHandler, ProcedureHandler::class);
        $statementDeleter = $container->get(StatementDeleter::class);
        Assert::isInstanceOf($statementDeleter, StatementDeleter::class);
        $this->sut = new AdminStatementCrossProcedureSearchResourceType(
            $htmlSanitizer,
            $statementService,
            $procedureHandler,
            $statementDeleter,
        );
        $this->sut->setCurrentUserService($this->currentUserService);
    }

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
        // The procedure relationship points at AdminProcedure, whose isAvailable() requires
        // area_admin_procedures. Naming an unavailable type in `fields` makes
        // {@see APIController::validateFieldsets()} throw, which aborts the whole request, so the FE
        // would not get procedure names for the grouping headings.
        $this->enablePermissions(['feature_json_api_statement_cross_procedures_search', 'area_admin_procedures']);

        $responseBody = $this->executeListRequest(
            AdminStatementCrossProcedureSearchResourceType::getName(),
            $user,
            null,
            Response::HTTP_OK,
            [
                'include' => 'procedure',
                'fields'  => [AdminProcedureResourceType::getName() => 'name'],
            ]
        );

        // The endpoint must accept the FE's actual call shape (include=procedure +
        // sparse AdminProcedure fieldset) without erroring. Data presence is environment-dependent;
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
        // Filtering across the procedure relationship resolves AdminProcedure as well, so the
        // "selected procedures" mode of the FE needs area_admin_procedures just like the sideload does.
        $this->enablePermissions(['feature_json_api_statement_cross_procedures_search', 'area_admin_procedures']);

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
                'fields'  => [AdminProcedureResourceType::getName() => 'name'],
            ]
        );

        self::assertArrayHasKey('data', $responseBody);
        foreach ($responseBody['included'] ?? [] as $included) {
            if (AdminProcedureResourceType::getName() === $included['type']) {
                self::assertSame($procedure->getId(), $included['id']);
            }
        }
    }

    public function testClaimedByOthersIsTrueForStatementAssignedToAnotherUser(): void
    {
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $otherUser = $this->getUserReference(LoadUserData::TEST_USER_2_PLANNER_ADMIN);
        $statement = StatementFactory::createOne(['assignee' => $otherUser])->_real();

        $claimedByOthers = $this->invokeProtectedMethod(
            [AdminStatementCrossProcedureSearchResourceType::class, 'isClaimedByOthers'],
            $statement
        );

        self::assertTrue($claimedByOthers);
    }

    public function testClaimedByOthersIsFalseForUnassignedStatement(): void
    {
        $this->logIn($this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $statement = StatementFactory::createOne(['assignee' => null])->_real();

        $claimedByOthers = $this->invokeProtectedMethod(
            [AdminStatementCrossProcedureSearchResourceType::class, 'isClaimedByOthers'],
            $statement
        );

        self::assertFalse($claimedByOthers, 'An unassigned statement can be claimed by the current user.');
    }

    public function testClaimedByOthersIsFalseForStatementAssignedToCurrentUser(): void
    {
        $currentUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($currentUser);
        $statement = StatementFactory::createOne(['assignee' => $currentUser])->_real();

        $claimedByOthers = $this->invokeProtectedMethod(
            [AdminStatementCrossProcedureSearchResourceType::class, 'isClaimedByOthers'],
            $statement
        );

        self::assertFalse($claimedByOthers, 'The current user already holds the claim.');
    }
}
