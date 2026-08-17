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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePairingCode;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\PersonalAccessToken;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushOutcome;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushResult;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureIntegration\RecommendationPushService;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentUserService;
use demosplan\DemosPlanCoreBundle\Permissions\Permissions;
use demosplan\DemosPlanCoreBundle\Security\ApiToken\ApiTokenContextInterface;
use demosplan\DemosPlanCoreBundle\Security\PersonalAccessToken\PersonalAccessTokenContext;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenContext;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedureIntegrationTokenCreationResult;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedurePairingCodeIssueResult;
use demosplan\DemosPlanCoreBundle\Security\ProcedureIntegrationToken\ProcedurePairingCodeService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
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
            $this->limiter(true),
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
            $this->limiter(true),
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
            $this->limiter(true),
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
            $this->limiter(true),
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
            $this->limiter(true),
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
    }

    /**
     * Checked before the body is parsed.
     */
    public function testRefusesAPushOverTheRateLimit(): void
    {
        $response = $this->sut->pushRecommendations(
            $this->requestWith([['sourceStatementId' => 's1', 'recommendation' => '<p>x</p>']]),
            $this->permissionsReturning($this->integrationContext($this->procedure('p1'))),
            $this->pushServiceExpectingNoCall(),
            $this->limiter(false),
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
    }

    public function testIssuesAPairingCodeForTheProcedureInThePath(): void
    {
        $procedure = $this->procedureWithCustomer('p1');
        $issued = new ProcedurePairingCodeIssueResult(
            $this->pairingCode($procedure, 'EWM'),
            'abcd-efgh-jkmn'
        );
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->expects(self::once())
            ->method('issue')
            ->with($procedure, self::anything(), 'EWM', self::anything(), self::anything(), self::anything())
            ->willReturn($issued);

        $response = $this->sut->issuePairingCode(
            new Request([], [], [], [], [], [], json_encode(['name' => 'EWM'], JSON_THROW_ON_ERROR)),
            $this->currentProcedureReturning($procedure),
            $this->currentUser(),
            $pairingCodeService,
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame('abcd-efgh-jkmn', $payload['data']['pairingCode']);
    }

    /**
     * The resolver has already refused unknown ids by the time the action runs, but it must not
     * assume that.
     */
    public function testRefusesToIssueWithoutAResolvedProcedure(): void
    {
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->expects(self::never())->method('issue');

        $response = $this->sut->issuePairingCode(
            new Request(),
            $this->currentProcedureReturning(null),
            $this->currentUser(),
            $pairingCodeService,
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * A token is bound to a customer too, so a procedure without one cannot be paired.
     */
    public function testRefusesToIssueForAProcedureWithoutACustomer(): void
    {
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->expects(self::never())->method('issue');

        $response = $this->sut->issuePairingCode(
            new Request(),
            $this->currentProcedureReturning($this->procedure('p1')),
            $this->currentUser(),
            $pairingCodeService,
        );

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
    }

    public function testExchangeReturnsTheDurableTokenAndTheProcedureItIsPinnedTo(): void
    {
        $procedure = $this->procedure('p1');
        $procedure->method('getName')->willReturn('Testverfahren');
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->method('redeem')->willReturn(
            new ProcedureIntegrationTokenCreationResult(
                $this->integrationContext($procedure)->token,
                'dplan_int_secret'
            )
        );

        $response = $this->sut->exchangePairingCode(
            $this->exchangeRequest('abcd-efgh-jkmn'),
            $pairingCodeService,
            $this->limiter(true),
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode());
        $payload = json_decode((string) $response->getContent(), true);
        self::assertSame('dplan_int_secret', $payload['data']['token']);
        self::assertSame('p1', $payload['data']['procedure']['id']);
        self::assertSame('Testverfahren', $payload['data']['procedure']['name']);
    }

    public function testExchangeAnswersTheSameWayForEveryKindOfMiss(): void
    {
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->method('redeem')->willReturn(null);

        $response = $this->sut->exchangePairingCode(
            $this->exchangeRequest('abcd-efgh-jkmn'),
            $pairingCodeService,
            $this->limiter(true),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testExchangeWithoutACodeNeverReachesTheService(): void
    {
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->expects(self::never())->method('redeem');

        $response = $this->sut->exchangePairingCode(
            new Request([], [], [], [], [], [], json_encode([], JSON_THROW_ON_ERROR)),
            $pairingCodeService,
            $this->limiter(true),
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    /**
     * The limiter is all that stands between an unauthenticated endpoint and repeated guessing.
     */
    public function testExchangeOverTheRateLimitDoesNotEvenLookAtTheCode(): void
    {
        $pairingCodeService = $this->createMock(ProcedurePairingCodeService::class);
        $pairingCodeService->expects(self::never())->method('redeem');

        $response = $this->sut->exchangePairingCode(
            $this->exchangeRequest('abcd-efgh-jkmn'),
            $pairingCodeService,
            $this->limiter(false),
        );

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $response->getStatusCode());
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

    private function exchangeRequest(string $pairingCode): Request
    {
        return new Request(
            [], [], [], [], [], [],
            json_encode(['pairingCode' => $pairingCode], JSON_THROW_ON_ERROR)
        );
    }

    private function procedure(string $id): Procedure
    {
        $procedure = $this->createMock(Procedure::class);
        $procedure->method('getId')->willReturn($id);

        return $procedure;
    }

    private function procedureWithCustomer(string $id): Procedure
    {
        $procedure = $this->procedure($id);
        $procedure->method('getCustomer')->willReturn($this->createMock(Customer::class));

        return $procedure;
    }

    private function pairingCode(Procedure $procedure, string $name): ProcedurePairingCode
    {
        return new ProcedurePairingCode(
            procedure: $procedure,
            customer: $this->createMock(Customer::class),
            name: $name,
            codeHash: str_repeat('f', 64),
            scopes: ['recommendations:write'],
            expiresAt: new DateTime('+15 minutes'),
        );
    }

    private function currentProcedureReturning(?Procedure $procedure): CurrentProcedureService
    {
        $currentProcedureService = $this->createMock(CurrentProcedureService::class);
        $currentProcedureService->method('getProcedure')->willReturn($procedure);

        return $currentProcedureService;
    }

    private function currentUser(): CurrentUserService
    {
        $currentUser = $this->createMock(CurrentUserService::class);
        $currentUser->method('getUser')->willReturn($this->createMock(User::class));

        return $currentUser;
    }

    /**
     * A real factory over in-memory storage, since {@see RateLimiterFactory} is final. To make it
     * refuse, its single hit is spent up front for every key the actions use.
     */
    private function limiter(bool $accepted): RateLimiterFactory
    {
        $storage = new InMemoryStorage();
        $factory = new RateLimiterFactory(
            ['id' => 'test', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '1 minute'],
            $storage
        );

        if (!$accepted) {
            foreach ([null, str_repeat('b', ProcedureIntegrationToken::TOKEN_PREFIX_LENGTH)] as $key) {
                $factory->create($key)->consume();
            }
        }

        return $factory;
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
