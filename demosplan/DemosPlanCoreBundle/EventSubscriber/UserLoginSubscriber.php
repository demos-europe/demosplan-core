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
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Event\AuthenticationSuccessEvent;

class UserLoginSubscriber extends BaseEventSubscriber
{
    /**
     * @var UserService
     */
    private $userService;

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
    }

    public function onLogin(AuthenticationSuccessEvent $event): void
    {
        $token = $event->getAuthenticationToken();
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
            AuthenticationSuccessEvent::class => 'onLogin',
        ];
    }
}
