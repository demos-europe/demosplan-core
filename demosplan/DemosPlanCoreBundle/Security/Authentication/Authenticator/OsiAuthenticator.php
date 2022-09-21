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

use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ValueObject\Credentials;
use demosplan\DemosPlanUserBundle\Logic\UserMapperDataportGateway;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

abstract class OsiAuthenticator extends DplanAuthenticator
{
    protected const LOGIN_ROUTES = ['DemosPlan_user_login_osi_legacy', 'DemosPlan_user_login_gateway'];

    /**
     * @var UserMapperDataportGateway
     */
    protected $userMapper;

    public function authenticate(Request $request): Passport
    {
        $this->verificationRoute = $this->userMapper->getVerificationRoute();

        return parent::authenticate($request);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('core_home'));
    }

    protected function getPassport(Credentials $credentials): Passport {

        $user = $this->userMapper->getValidUser($credentials);

        return new SelfValidatingPassport(new UserBadge($user->getLogin()));
    }


}
