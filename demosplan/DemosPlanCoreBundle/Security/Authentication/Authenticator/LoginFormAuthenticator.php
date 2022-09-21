<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Authentication\Authenticator;

use demosplan\DemosPlanCoreBundle\Event\RequestValidationWeakEvent;
use demosplan\DemosPlanCoreBundle\Logic\LinkMessage;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\PasswordUpgradeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;

final class LoginFormAuthenticator extends DplanAuthenticator implements AuthenticationEntryPointInterface
{
    public const LOGIN_ROUTE = 'DemosPlan_user_login';

    public function supports(Request $request): bool
    {
        return self::LOGIN_ROUTE === $request->attributes->get('_route')
            && $request->isMethod('POST');
    }

    public function getCredentials(Request $request): Credentials
    {
        // check Honeypotfields
        try {
            $event = new RequestValidationWeakEvent($request);
            $this->eventDispatcher->post($event);
        } catch (Exception $e) {
            $this->logger->warning('Could not successfully verify Authentication form ', [$e]);

            throw new AuthenticationException('Error during authentication', 0, $e);
        }

        $credentials = [
            'login'    => trim($request->request->get('r_useremail')),
            'password' => trim($request->request->get('password')),
        ];
        $request->getSession()->set(
            Security::LAST_USERNAME,
            $credentials['login']
        );
        $credentialsVO = new Credentials();
        $credentialsVO->setLogin($credentials['login']);
        $credentialsVO->setPassword($credentials['password']);
        $credentialsVO->lock();

        return $credentialsVO;
    }

    public function validateCredentials(Credentials $credentials): void
    {
        // check for password strength and warn if it is too weak
        $violations = $this->passwordValidator->validate($credentials->getPassword());
        if (0 < $violations->count()) {
            $linkChangeText = $this->translator->trans('password.change');
            $this->messageBag->addObject(LinkMessage::createLinkMessage(
                'warning',
                'warning.password.weak',
                [],
                'DemosPlan_user_portal',
                [],
                $linkChangeText)
            );
        }

        parent::validateCredentials($credentials);
    }

    protected function getPassport(Credentials $credentials): Passport
    {
        $user = $this->userMapper->getValidUser($credentials);

        return new Passport(
            new UserBadge($user ? $user->getLogin() : ''),
            new PasswordCredentials($credentials->getPassword()),
            [new PasswordUpgradeBadge($credentials->getPassword())]
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $this->messageBag->add('warning', 'warning.login.failed');

        return new RedirectResponse($this->urlGenerator->generate('DemosPlan_user_login_alternative'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('DemosPlan_user_login_alternative'));
    }
}
