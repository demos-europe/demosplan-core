<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserMapperInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

final class LoginFormAuthenticator extends DplanAuthenticator implements AuthenticationEntryPointInterface
{
    public const LOGIN_ROUTE = 'DemosPlan_user_login';

    /**
     * @param list<string> $idpOnlySubdomains
     */
    public function __construct(
        UserMapperInterface $authenticator,
        LoggerInterface $logger,
        MessageBagInterface $messageBag,
        TraceableEventDispatcher $eventDispatcher,
        UrlGeneratorInterface $urlGenerator,
        UserService $userService,
        private readonly CustomerService $customerService,
        #[Autowire('%idp_only_subdomains%')]
        private readonly array $idpOnlySubdomains,
    ) {
        parent::__construct($authenticator, $logger, $messageBag, $eventDispatcher, $urlGenerator, $userService);
    }

    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    protected function getCredentials(Request $request): Credentials
    {
        // check Honeypotfields
        try {
            $event = new RequestValidationWeakEvent($request);
            $this->eventDispatcher->post($event);
        } catch (Exception $e) {
            $this->logger->warning('Could not successfully verify Authentication form ', [$e]);

            throw new AuthenticationException('Error during authentication', 0, $e);
        }

        $login = trim($request->request->get('r_useremail', ''));
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $login);
        $credentialsVO = new Credentials();
        $credentialsVO->setLogin($login);
        $credentialsVO->setPassword(trim($request->request->get('password', '')));
        $credentialsVO->setToken($request->request->get('_csrf_token'));
        $credentialsVO->lock();

        return $credentialsVO;
    }

    protected function getPassport(Credentials $credentials): Passport
    {
        $user = $this->userMapper->getValidUser($credentials);

        if ($user instanceof User
            && $user->isProvidedByIdentityProvider()
            && $this->isIdpOnlySubdomain()
        ) {
            $this->logger->info('Password login rejected for identity-provider user on IdP-only subdomain', ['login' => $user->getLogin()]);

            throw new AuthenticationException('Password login is disabled for identity-provider users on this customer.');
        }

        return new Passport(
            new UserBadge($user ? $user->getLogin() : ''),
            new PasswordCredentials($credentials->getPassword()),
            [
                new PasswordUpgradeBadge($credentials->getPassword()),
                new WeakPasswordCheckerBadge($credentials->getPassword()),
                new CsrfTokenBadge('authenticate', $credentials->getToken()),
            ]
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $message = 'warning.login.failed';
        $params = [];
        if ($exception instanceof TooManyLoginAttemptsAuthenticationException) {
            $message = 'warning.login.failed.throttle';
            $params = $exception->getMessageData();
        }

        $this->messageBag->add('warning', $message, $params);
        $this->logger->info('Login failed', [$exception]);

        return new RedirectResponse($this->urlGenerator->generate('DemosPlan_user_login_alternative'));
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('DemosPlan_user_login_alternative'));
    }

    private function isIdpOnlySubdomain(): bool
    {
        if ([] === $this->idpOnlySubdomains) {
            return false;
        }

        try {
            $subdomain = $this->customerService->getCurrentCustomer()->getSubdomain();
        } catch (Exception $e) {
            $this->logger->warning('Could not resolve current customer for IdP-only subdomain check', [$e]);

            return false;
        }

        return in_array($subdomain, $this->idpOnlySubdomains, true);
    }
}
