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
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
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

        return $this->twoFactorRequirementResolver->isSetupPending($session)
            && !$this->twoFactorRequirementResolver->hasSecondFactor($user)
            && $this->isRequirementActive();
    }

    /**
     * Whether an organisation of the current user requires a second factor in this project.
     *
     * Independent of the session flag, so it also holds for users who set up a factor voluntarily long ago.
     */
    public function isRequirementActive(): bool
    {
        $user = $this->getUser();
        if (null === $user) {
            return false;
        }

        return $this->currentUser->hasPermission('feature_orga_two_factor_required')
            && $this->currentUser->hasPermission('feature_2fa')
            && $this->twoFactorRequirementResolver->isRequiredForUser($user);
    }

    /**
     * Whether disabling the given factor of the target user would remove the last one while the requirement is active.
     *
     * Only applies to the current user editing their own data, so administrative changes of other users are not
     * affected.
     *
     * @param string $method 'totp' or 'email'
     */
    public function isDisableLockedFor(UserInterface $target, string $method): bool
    {
        $user = $this->getUser();
        if (null === $user || $target->getId() !== $user->getId() || !$this->isRequirementActive()) {
            return false;
        }

        $subject = $target instanceof User ? $target : $user;
        $totp = $subject->isTotpEnabled();
        $email = $subject->isEmailAuthEnabled();

        return 'totp' === $method ? $totp && !$email : $email && !$totp;
    }

    /**
     * State for the profile page.
     *
     * "disableLocked" means the requirement is active and exactly one factor is set up, so it must not be disabled.
     *
     * @return array{required: bool, orgaNames: list<string>, disableLocked: bool}
     */
    public function getSetupState(): array
    {
        $user = $this->getUser();
        $required = null !== $user && $this->isEnforced();

        return [
            'required'      => $required,
            'orgaNames'     => $required ? $this->twoFactorRequirementResolver->getRequiringOrgaNames($user) : [],
            'disableLocked' => null !== $user
                && $this->isRequirementActive()
                && $user->isTotpEnabled() !== $user->isEmailAuthEnabled(),
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
