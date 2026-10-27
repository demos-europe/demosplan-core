<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\User\FunctionalUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Single source of truth for "the current user has to set up a second factor right now".
 *
 * Works on the logged-in user and the session of the current request, so it is meant for the HTTP context only.
 * Without a request, a session or a regular user nothing is enforced. Used by the enforcement subscriber
 * (redirect) and the profile page (hint, cancel button, redirect after saving).
 */
readonly class TwoFactorSetupEnforcer
{
    public function __construct(
        private CurrentUserInterface $currentUser,
        private TwoFactorRequirementResolver $twoFactorRequirementResolver,
        private RequestStack $requestStack,
    ) {
    }

    /**
     * The permission checks are the feature permission for the orga requirement and the 2FA feature itself.
     * The orga requirement is checked live, so switching it off releases the user immediately, while the session flag
     * restricts the enforcement to sessions that started with the requirement in place.
     */
    public function isEnforced(): bool
    {
        $user = $this->getUser();
        $session = $this->getSession();
        if (null === $user || null === $session) {
            return false;
        }

        return $this->currentUser->hasPermission('feature_orga_two_factor_required')
            && $this->currentUser->hasPermission('feature_2fa')
            && $this->twoFactorRequirementResolver->isRequiredForUser($user)
            && $this->twoFactorRequirementResolver->isSetupPending($session)
            && !$this->twoFactorRequirementResolver->hasSecondFactor($user);
    }

    /**
     * State for the profile page.
     *
     * @return array{required: bool, orgaNames: list<string>}
     */
    public function getSetupState(): array
    {
        $user = $this->getUser();
        $required = null !== $user && $this->isEnforced();

        return [
            'required'  => $required,
            'orgaNames' => $required ? $this->twoFactorRequirementResolver->getRequiringOrgaNames($user) : [],
        ];
    }

    /**
     * Ends the pending setup if the user now has a second factor.
     *
     * @return bool true if the setup was pending and is now completed
     */
    public function completeSetupIfDone(): bool
    {
        $user = $this->getUser();
        $session = $this->getSession();
        if (null === $user
            || null === $session
            || !$this->twoFactorRequirementResolver->isSetupPending($session)
            || !$this->twoFactorRequirementResolver->hasSecondFactor($user)) {
            return false;
        }

        $this->twoFactorRequirementResolver->clearSetupPending($session);

        return true;
    }

    private function getUser(): ?User
    {
        $user = $this->currentUser->getUser();

        return $user instanceof User && !$user instanceof FunctionalUser ? $user : null;
    }

    private function getSession(): ?SessionInterface
    {
        try {
            return $this->requestStack->getSession();
        } catch (SessionNotFoundException) {
            return null;
        }
    }
}
