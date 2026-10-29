<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EventSubscriber\TwoFactorSetupEnforcementSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorRequirementResolver;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorSetupEnforcer;
use Monolog\Handler\TestHandler;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Tests\Base\FunctionalTestCase;

class TwoFactorSetupEnforcementSubscriberTest extends FunctionalTestCase
{
    private const FEATURE_ORGA_REQUIREMENT = 'feature_orga_two_factor_required';
    private const FEATURE_2FA = 'feature_2fa';
    private const SETUP_ROUTE = 'DemosPlan_user_portal';

    protected ?TwoFactorSetupEnforcementSubscriber $sut = null;
    protected ?TwoFactorRequirementResolver $resolver = null;
    protected ?RequestStack $requestStack = null;
    protected ?Session $session = null;
    protected ?TestHandler $logHandler = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->resolver = new TwoFactorRequirementResolver();
        $this->requestStack = new RequestStack();
        $currentUser = self::getContainer()->get(CurrentUserInterface::class);
        $this->sut = new TwoFactorSetupEnforcementSubscriber(
            $currentUser,
            new TwoFactorSetupEnforcer($currentUser, $this->resolver, $this->requestStack),
            self::getContainer()->get(RouterInterface::class),
        );
        $this->logHandler = new TestHandler();
        $this->sut->setLogger(new Logger('test', [$this->logHandler]));
        $this->session = new Session(new MockArraySessionStorage());
    }

    protected function tearDown(): void
    {
        $this->sut = null;
        $this->resolver = null;
        $this->requestStack = null;
        $this->session = null;
        $this->logHandler = null;

        parent::tearDown();
    }

    public function testSubscribesToKernelControllerWithPriorityBetweenPermissionInitAndCheck(): void
    {
        $subscribed = TwoFactorSetupEnforcementSubscriber::getSubscribedEvents();

        self::assertSame(['onKernelController', 6], $subscribed[KernelEvents::CONTROLLER]);
    }

    public function testEnforcedUserIsRedirectedToSetup(): void
    {
        $this->arrangeEnforcedUser();

        $response = $this->dispatch($this->createRequest('some_regular_route', '/some/page'));

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(
            self::getContainer()->get(RouterInterface::class)->generate(self::SETUP_ROUTE),
            $response->getTargetUrl()
        );
        self::assertTrue($this->logHandler->hasDebugThatContains('Two-factor setup pending'));
    }

    public function testEnforcedXhrRequestGetsForbiddenJson(): void
    {
        $this->arrangeEnforcedUser();
        $request = $this->createRequest('some_regular_route', '/some/page');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        $response = $this->dispatch($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    #[DataProvider('apiRequestProvider')]
    public function testEnforcedApiRequestGetsForbiddenJson(string $path, ?string $accept): void
    {
        $this->arrangeEnforcedUser();
        $request = $this->createRequest('some_api_route', $path);
        if (null !== $accept) {
            $request->headers->set('Accept', $accept);
        }

        $response = $this->dispatch($request);

        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    public static function apiRequestProvider(): array
    {
        return [
            'api path'            => ['/api/2.0/Statement', null],
            'json api accept'     => ['/some/page', 'application/vnd.api+json'],
            'json accept'         => ['/some/page', 'application/json'],
            'json among browsers' => ['/some/page', 'application/json, text/plain, */*'],
        ];
    }

    #[DataProvider('allowedRouteProvider')]
    public function testAllowListedRoutePasses(string $route): void
    {
        $this->arrangeEnforcedUser();

        self::assertNull($this->dispatch($this->createRequest($route, '/some/page')));
    }

    public static function allowedRouteProvider(): array
    {
        return [
            'meine daten'              => ['DemosPlan_user_portal'],
            'meine daten save'         => ['DemosPlan_user_edit'],
            'welcome page'             => ['DemosPlan_user_complete_data'],
            'welcome page save'        => ['user_update_additional_information'],
            'qr code'                  => ['DemosPlan_user_qr_code'],
            'totp enable'              => ['DemosPlan_user_2fa_enable'],
            'email enable'             => ['DemosPlan_user_2fa_email_enable'],
            'email send'               => ['DemosPlan_user_2fa_email_send'],
            'logout'                   => ['DemosPlan_user_logout'],
            'logout success'           => ['DemosPlan_user_logout_success'],
            'scheb login'              => ['2fa_login'],
            'scheb login check'        => ['2fa_login_check'],
            'select orga'              => ['DemosPlan_user_select_organisation'],
            'data protection'          => ['DemosPlan_misccontent_static_dataprotection'],
            'imprint'                  => ['DemosPlan_misccontent_static_imprint'],
            'profiler'                 => ['_profiler'],
            'web debug toolbar'        => ['_wdt'],
        ];
    }

    public function testSessionWithoutFlagPasses(): void
    {
        $this->arrangeEnforcedUser(setupPending: false);

        self::assertNull($this->dispatch($this->createRequest('some_regular_route', '/some/page')));
    }

    public function testOrgaWithoutRequirementPasses(): void
    {
        $this->arrangeEnforcedUser(orgaRequirements: [false]);

        self::assertNull($this->dispatch($this->createRequest('some_regular_route', '/some/page')));
    }

    public function testOnlyOneOfSeveralOrgasRequiringIsEnough(): void
    {
        $this->arrangeEnforcedUser(orgaRequirements: [false, true]);

        $response = $this->dispatch($this->createRequest('some_regular_route', '/some/page'));

        self::assertInstanceOf(RedirectResponse::class, $response);
    }

    #[DataProvider('missingPermissionProvider')]
    public function testMissingPermissionPasses(string $missingPermission): void
    {
        $this->arrangeEnforcedUser(disabledPermissions: [$missingPermission]);

        self::assertNull($this->dispatch($this->createRequest('some_regular_route', '/some/page')));
    }

    public static function missingPermissionProvider(): array
    {
        return [
            'orga requirement feature missing' => [self::FEATURE_ORGA_REQUIREMENT],
            'feature 2fa missing'              => [self::FEATURE_2FA],
        ];
    }

    #[DataProvider('secondFactorProvider')]
    public function testUserWithSecondFactorPassesAndFlagIsCleared(bool $totpEnabled, bool $emailAuthEnabled): void
    {
        $this->arrangeEnforcedUser(totpEnabled: $totpEnabled, emailAuthEnabled: $emailAuthEnabled);

        self::assertNull($this->dispatch($this->createRequest('some_regular_route', '/some/page')));
        self::assertFalse($this->resolver->isSetupPending($this->session));
    }

    public static function secondFactorProvider(): array
    {
        return [
            'totp'  => [true, false],
            'email' => [false, true],
            'both'  => [true, true],
        ];
    }

    public function testSubRequestPasses(): void
    {
        $this->arrangeEnforcedUser();

        $response = $this->dispatch(
            $this->createRequest('some_regular_route', '/some/page'),
            HttpKernelInterface::SUB_REQUEST
        );

        self::assertNull($response);
    }

    public function testRequestWithoutSessionPasses(): void
    {
        $this->arrangeEnforcedUser();

        self::assertNull($this->dispatch($this->createRequest('some_regular_route', '/some/page', false)));
    }

    /**
     * Logs in a user that is, by default, subject to the enforcement and enables the permissions.
     *
     * @param list<bool>   $orgaRequirements
     * @param list<string> $disabledPermissions
     */
    private function arrangeEnforcedUser(
        array $orgaRequirements = [true],
        bool $totpEnabled = false,
        bool $emailAuthEnabled = false,
        bool $setupPending = true,
        array $disabledPermissions = [],
    ): void {
        $user = $this->createUser($orgaRequirements, $totpEnabled, $emailAuthEnabled);
        $this->logIn($user);
        $this->enablePermissions([self::FEATURE_ORGA_REQUIREMENT, self::FEATURE_2FA]);
        if ([] !== $disabledPermissions) {
            $this->disablePermissions($disabledPermissions);
        }
        if ($setupPending) {
            $this->resolver->markSetupPending($this->session);
        }
    }

    /**
     * Persists the user in one organisation per given requirement flag and reloads it,
     * so the organisations are read from the database.
     *
     * @param list<bool> $orgaRequirements
     */
    private function createUser(array $orgaRequirements, bool $totpEnabled, bool $emailAuthEnabled): User
    {
        $user = UserFactory::createOne([
            'totpEnabled'          => $totpEnabled,
            'authCodeEmailEnabled' => $emailAuthEnabled,
        ]);

        foreach ($orgaRequirements as $twoFactorRequired) {
            OrgaFactory::createOne([
                'deleted'           => false,
                'twoFactorRequired' => $twoFactorRequired,
                'users'             => [$user->_real()],
            ]);
        }

        $user->_refresh();

        return $user->_real();
    }

    private function createRequest(string $route, string $path, bool $withSession = true): Request
    {
        $request = Request::create($path);
        $request->attributes->set('_route', $route);
        if ($withSession) {
            $request->setSession($this->session);
        }

        return $request;
    }

    /**
     * Runs the subscriber and returns the response of the controller it may have put in place,
     * or null if the original controller was left untouched.
     */
    private function dispatch(Request $request, int $requestType = HttpKernelInterface::MAIN_REQUEST): ?Response
    {
        $originalController = static fn (): Response => new Response('original');
        $event = new ControllerEvent(self::$kernel, $originalController, $request, $requestType);

        // The enforcer reads the session of the current request
        $this->requestStack->push($request);
        $this->sut->onKernelController($event);
        $this->requestStack->pop();

        if ($event->getController() === $originalController) {
            return null;
        }

        $response = ($event->getController())();
        self::assertInstanceOf(Response::class, $response);

        return $response;
    }
}
