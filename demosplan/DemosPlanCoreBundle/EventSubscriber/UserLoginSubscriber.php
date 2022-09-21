<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\InteractiveLoginEvent;

class UserLoginSubscriber extends BaseEventSubscriber
{
    /**
     * @var UserService
     */
    private $userService;

    /**
     * @var TokenStorageInterface
     */
    private $tokenStorage;

    public function __construct(TokenStorageInterface $tokenStorage, UserService $userService)
    {
        $this->userService = $userService;
        $this->tokenStorage = $tokenStorage;
    }

    public function onLogin(): void
    {
        $token = $this->tokenStorage->getToken();
        if ($token instanceof TokenInterface && !$token instanceof JWTUserToken) {
            $user = $token->getUser();
            if ($user instanceof User && !$user instanceof FunctionalUser) {
                $user->setLastLogin(new DateTime());
                $this->userService->updateUserObject($user);
            }
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            InteractiveLoginEvent::class => 'onLogin',
        ];
    }
}
