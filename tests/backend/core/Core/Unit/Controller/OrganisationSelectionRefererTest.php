<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Controller;

use demosplan\DemosPlanCoreBundle\Controller\User\OrganisationSelectionController;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CurrentOrganisationService;
use demosplan\DemosPlanCoreBundle\Logic\ViewRenderer;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Tests the referer sanitization and session-based return URL handling
 * in OrganisationSelectionController.
 *
 * Verifies that the controller no longer round-trips the raw Referer header
 * through a hidden form field (which was vulnerable to XSS/open redirect),
 * and instead stores only the validated path in the session.
 */
class OrganisationSelectionRefererTest extends TestCase
{
    private OrganisationSelectionController $sut;
    private MockObject&CurrentOrganisationService $currentOrganisationService;
    private MockObject&User $user;
    private MockObject&Orga $orga;
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentOrganisationService = $this->createMock(CurrentOrganisationService::class);
        $this->sut = new OrganisationSelectionController($this->currentOrganisationService);

        // Set up a multi-org user so selectOrganisation() renders (doesn't auto-redirect)
        $this->orga = $this->createMock(Orga::class);
        $this->orga->method('getId')->willReturn('orga-1');

        $orgaB = $this->createMock(Orga::class);
        $orgaB->method('getId')->willReturn('orga-2');

        $organisations = new ArrayCollection([$this->orga, $orgaB]);

        $this->user = $this->createMock(User::class);
        $this->user->method('getOrganisations')->willReturn($organisations);
        $this->user->method('getCurrentOrganisation')->willReturn($this->orga);

        // Wire up the security token so getUser() returns our mock
        $token = $this->createMock(TokenInterface::class);
        $token->method('getUser')->willReturn($this->user);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);
        $tokenStorage->method('getToken')->willReturn($token);

        // Wire up router for redirectToRoute() calls
        $router = $this->createMock(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => '/'.$name
        );

        // Wire up CSRF token manager — always valid
        $csrfTokenManager = $this->createMock(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken('switch_organisation', 'valid'));
        $csrfTokenManager->method('isTokenValid')->willReturn(true);

        // Minimal container with services needed by AbstractController
        $container = new Container();
        $container->set('security.token_storage', $tokenStorage);
        $container->set('router', $router);
        $container->set('security.csrf.token_manager', $csrfTokenManager);
        $container->set('twig', $this->createTwigMock());

        $this->sut->setContainer($container);
        $this->sut->setLogger(new NullLogger());
        $this->sut->setMessageBag($this->createMock(\DemosEurope\DemosplanAddon\Contracts\MessageBagInterface::class));
        $this->sut->setViewRenderer($this->createMock(ViewRenderer::class));
        $this->sut->setGlobalConfig($this->createMock(GlobalConfig::class));

        $this->session = new Session(new MockArraySessionStorage());
    }

    private function createTwigMock(): MockObject
    {
        $twig = $this->createMock(\Twig\Environment::class);
        $twig->method('render')->willReturn('<html></html>');

        return $twig;
    }

    private function createSelectRequest(string $referer): Request
    {
        $request = Request::create('/organisation/select');
        $request->setSession($this->session);
        $request->headers->set('referer', $referer);

        return $request;
    }

    private function createSwitchRequest(): Request
    {
        $request = Request::create('/organisation/switch-responsibility', 'POST', [
            '_token'          => 'valid',
            'organisation_id' => 'orga-1',
        ]);
        $request->setSession($this->session);

        return $request;
    }

    public function testValidRefererPathIsStoredInSession(): void
    {
        $this->sut->selectOrganisation(
            $this->createSelectRequest('https://example.com/procedure/list?page=2')
        );

        self::assertSame('/procedure/list', $this->session->get('organisation_selection_return_url'));
    }

    public function testSwitchRedirectsToSessionReturnUrl(): void
    {
        // Simulate: selectOrganisation stored the path
        $this->sut->selectOrganisation(
            $this->createSelectRequest('https://example.com/procedure/list')
        );

        $this->currentOrganisationService->method('setCurrentOrganisation');

        $response = $this->sut->switchOrganisation($this->createSwitchRequest());

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/procedure/list', $response->getTargetUrl());
    }

    public function testReturnUrlIsClearedAfterSwitch(): void
    {
        $this->sut->selectOrganisation(
            $this->createSelectRequest('https://example.com/dashboard')
        );

        $this->currentOrganisationService->method('setCurrentOrganisation');

        // First switch uses the stored URL
        $this->sut->switchOrganisation($this->createSwitchRequest());

        // Session key should be cleared
        self::assertNull($this->session->get('organisation_selection_return_url'));
    }

    public function testXssPayloadInRefererIsNotStored(): void
    {
        $this->sut->selectOrganisation(
            $this->createSelectRequest('" onfocus="alert(1)" autofocus="')
        );

        self::assertNull($this->session->get('organisation_selection_return_url'));
    }

    public function testProtocolRelativeRefererStoresOnlyPath(): void
    {
        // //evil.com/steal — parse_url extracts host=evil.com, path=/steal
        // The path /steal passes the regex, but crucially it's just a local path
        $this->sut->selectOrganisation(
            $this->createSelectRequest('//evil.com/steal')
        );

        $stored = $this->session->get('organisation_selection_return_url');
        // Only the path component is stored, never the full URL
        self::assertSame('/steal', $stored);
    }

    public function testExternalUrlRefererStoresOnlyLocalPath(): void
    {
        $this->sut->selectOrganisation(
            $this->createSelectRequest('https://evil.com/phish?token=secret')
        );

        // Only the path, no host or query string
        self::assertSame('/phish', $this->session->get('organisation_selection_return_url'));
    }

    public function testDoubleSlashPathIsRejected(): void
    {
        // Path "//evil.com" fails regex #^/[^/]#
        $this->sut->selectOrganisation(
            $this->createSelectRequest('https://example.com//evil.com')
        );

        self::assertNull($this->session->get('organisation_selection_return_url'));
    }

    public function testRootOnlyPathIsRejected(): void
    {
        // "/" fails regex #^/[^/]# — needs a char after the slash
        $this->sut->selectOrganisation(
            $this->createSelectRequest('https://example.com/')
        );

        self::assertNull($this->session->get('organisation_selection_return_url'));
    }

    public function testJavascriptSchemeIsRejected(): void
    {
        $this->sut->selectOrganisation(
            $this->createSelectRequest('javascript:alert(document.cookie)')
        );

        self::assertNull($this->session->get('organisation_selection_return_url'));
    }

    public function testEmptyRefererStoresNothing(): void
    {
        $request = Request::create('/organisation/select');
        $request->setSession($this->session);
        // No referer header set

        $this->sut->selectOrganisation($request);

        self::assertNull($this->session->get('organisation_selection_return_url'));
    }

    public function testSwitchFallsBackToHomeWhenNoReturnUrl(): void
    {
        // No selectOrganisation call — session is empty
        $this->currentOrganisationService->method('setCurrentOrganisation');

        $response = $this->sut->switchOrganisation($this->createSwitchRequest());

        self::assertInstanceOf(RedirectResponse::class, $response);
        // Falls back to the generated route for core_home_loggedin
        self::assertSame('/core_home_loggedin', $response->getTargetUrl());
    }
}
