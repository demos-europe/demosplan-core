<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Security\Firewall\Listener;

use demosplan\DemosPlanCoreBundle\Entity\User\AnonymousUser;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Security\Authentication\Token\DemosToken;
use Exception;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\Security\Core\Authentication\Token\AbstractToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class DemosAuthenticationListener
{
    /**
     * @var TokenStorageInterface
     */
    protected $tokenStorage;

    /**
     * @var UserService
     */
    private $userService;

    public function __construct(
        TokenStorageInterface $tokenStorage,
        UserService $userService
    ) {
        $this->tokenStorage = $tokenStorage;
        $this->userService = $userService;
    }

    /**
     * This method ties the authenticated user to the symfony authenticationManager
     * so that it is possible to use AuthorizationChecker and SecurityVoters.
     */
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();
        $session = $request->getSession();
        if (null === $session) {
            return;
        }

        // default: Get user from session
        $user = null;
        try {
            $user = $this->userService->getSingleUser($session->get('userId'));
        } catch (Exception $exception) {
            // when this really fails just try other options
            // in any case AnonymousUser is set later on
        }

        $currentToken = $this->tokenStorage->getToken();

        // Get user from token if we have a JWT or SAML token
        if ($currentToken instanceof AbstractToken) {
            $user = $currentToken->getUser();
        }

        // when no current user is provided (as in api requests)
        // always provide at least logged out anonymous user
        // Symfony 4.4 yet uses weird 'anon.' string for anonymous user
        if (null === $user || 'anon.' === $user) {
            $user = new AnonymousUser();
        }

        $authToken = new DemosToken($user);
        $authToken->setUser($user);
        $this->tokenStorage->setToken($authToken);
    }
}
