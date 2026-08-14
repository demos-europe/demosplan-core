<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Security\PersonalAccessToken;

use DateTime;
use demosplan\DemosPlanCoreBundle\Controller\ProcedureIntegrationApiController;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureIntegrationToken;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushOutcome;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushResult;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\UnitTestCase;

/**
 * The endpoint's own guard, separate from the permission gate: the permission it requires is enabled
 * for a role the AI integration also holds, and that integration authenticates by JWT and so carries
 * no token context at all. Without this guard the permission alone would open the endpoint.
 */
class ProcedureIntegrationApiControllerTest extends UnitTestCase
{
    private ?ProcedureIntegrationApiController $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ProcedureIntegrationApiController::class);
    }

    public function testRefusesWithoutAnyTokenContext(): void
    {
        $response = $this->sut->pushRecommendations(
            $this->requestWith([['sourceStatementId' => 's1', 'recommendation' => '<p>x</p>']]),
            $this->permissionsReturning(null),
            $this->pushServiceExpectingNoCall(),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    /**
     * A PAT is a legitimate API credential, but not for this endpoint: it is not pinned to a procedure
     * the way an integration token is.
     */
    public function testRefusesAPersonalAccessTokenContext(): void
    {
        $response = $this->sut->pushRecommendations(
            $this->requestWith([['sourceStatementId' => 's1', 'recommendation' => '<p>x</p>']]),
            $this->permissionsReturning($this->patContext()),
            $this->pushServiceExpectingNoCall(),
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public function testPushesUsingTheProcedurePinnedByTheToken(): void
    {
        $pinned = $this->procedure('pinned-procedure');
        $pushService = $this->createMock(RecommendationPushService::class);
        $pushService->expects(self::once())
            ->method('push')
            ->with($pinned, self::anything())
            ->willReturn([RecommendationPushResult::pushed('s1', 'M1')]);

        $response = $this->sut->pushRecommendations(
            $this->requestWith([['sourceStatementId' => 's1', 'recommendation' => '<p>x</p>']]),
            $this->permissionsReturning($this->integrationContext($pinned)),
            $pushService,
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame(
            [['sourceStatementId' => 's1', 'externId' => 'M1', 'outcome' => RecommendationPushOutcome::PUSHED->value]],
            $payload['data']
        );
    }

    public function testRejectsABodyWithNoUsableEntry(): void
    {
        $response = $this->sut->pushRecommendations(
            $this->requestWith([['recommendation' => 'missing the id']]),
            $this->permissionsReturning($this->integrationContext($this->procedure('p1'))),
            $this->pushServiceExpectingNoCall(),
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    public function testRejectsAMalformedBody(): void
    {
        $request = new Request([], [], [], [], [], [], 'not json');

        $response = $this->sut->pushRecommendations(
            $request,
            $this->permissionsReturning($this->integrationContext($this->procedure('p1'))),
            $this->pushServiceExpectingNoCall(),
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * @param list<array<string, string>> $entries
     */
    private function requestWith(array $entries): Request
    {
        return new Request([], [], [], [], [], [], json_encode(['data' => $entries], JSON_THROW_ON_ERROR));
    }

    private function permissionsReturning(?ApiTokenContextInterface $context): Permissions
    {
        $permissions = $this->createMock(Permissions::class);
        $permissions->method('getApiTokenContext')->willReturn($context);

        return $permissions;
    }

    private function pushServiceExpectingNoCall(): RecommendationPushService
    {
        $pushService = $this->createMock(RecommendationPushService::class);
        $pushService->expects(self::never())->method('push');

        return $pushService;
    }

    private function procedure(string $id): Procedure
    {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn($id);

        return $procedure;
    }

    private function integrationContext(Procedure $procedure): ProcedureIntegrationTokenContext
    {
        return ProcedureIntegrationTokenContext::fromToken(new ProcedureIntegrationToken(
            procedure: $procedure,
            customer: $this->createMock(Customer::class),
            name: 'integration',
            tokenPrefix: str_repeat('b', ProcedureIntegrationToken::TOKEN_PREFIX_LENGTH),
            tokenHash: 'hashed:secret',
            scopes: ['recommendations:write'],
        ));
    }

    private function patContext(): PersonalAccessTokenContext
    {
        return PersonalAccessTokenContext::fromToken(new PersonalAccessToken(
            user: $this->createMock(User::class),
            customer: $this->createMock(Customer::class),
            name: 'pat',
            tokenPrefix: str_repeat('a', PersonalAccessToken::TOKEN_PREFIX_LENGTH),
            tokenHash: 'hashed:secret',
            scopes: ['statements:write'],
            expiresAt: new DateTime('+30 days'),
        ));
    }
}
