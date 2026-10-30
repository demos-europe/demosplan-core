<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\EventSubscriber;

use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\TwoFactorRequirementResolver;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Scheb\TwoFactorBundle\Security\Http\Authenticator\TwoFactorAuthenticator;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

/**
 * Remembers in the session that a freshly logged-in user has to set up a second factor,
 * because one of the user's organisations requires it. The enforcement itself happens later.
 *
 * Permissions are not initialised at login time and are therefore not checked here.
 */
class TwoFactorSetupRequirementSubscriber extends BaseEventSubscriber
{
    public function __construct(
        private readonly TwoFactorRequirementResolver $twoFactorRequirementResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LoginSuccessEvent::class => 'onLogin',
        ];
    }

    public function onLogin(LoginSuccessEvent $event): void
    {
        if ($event->getAuthenticatedToken() instanceof JWTUserToken) {
            return;
        }

        // Scheb re-fires LoginSuccessEvent after the second step succeeded. The user has a factor then.
        if ($event->getAuthenticator() instanceof TwoFactorAuthenticator) {
            return;
        }

        $user = $event->getAuthenticatedToken()->getUser();
        if (!$user instanceof User || $user instanceof FunctionalUser) {
            return;
        }

        $request = $event->getRequest();
        if (!$request->hasSession()) {
            return;
        }

        if ($this->twoFactorRequirementResolver->isRequiredForUser($user)
            && !$this->twoFactorRequirementResolver->hasSecondFactor($user)) {
            $this->twoFactorRequirementResolver->markSetupPending($request->getSession());
            $this->logger->info('Two-factor setup required at login', [
                'userId'         => $user->getId(),
                'requiringOrgas' => $this->twoFactorRequirementResolver->getRequiringOrgaNames($user),
            ]);
        }
    }
}
