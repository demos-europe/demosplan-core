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

use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Decides whether a user has to use a second factor, based on the organisations the user belongs to.
 */
class TwoFactorRequirementResolver
{
    public const SESSION_KEY_SETUP_PENDING = 'two_factor_setup_pending';

    /**
     * A user is covered as soon as any of the user's organisations requires 2FA.
     */
    public function isRequiredForUser(User $user): bool
    {
        return [] !== $this->getRequiringOrgas($user);
    }

    public function hasSecondFactor(User $user): bool
    {
        return $user->isTotpEnabled() || $user->isEmailAuthEnabled();
    }

    /**
     * @return list<string>
     */
    public function getRequiringOrgaNames(User $user): array
    {
        return array_map(
            static fn (Orga $orga): string => (string) $orga->getName(),
            $this->getRequiringOrgas($user)
        );
    }

    public function markSetupPending(SessionInterface $session): void
    {
        $session->set(self::SESSION_KEY_SETUP_PENDING, true);
    }

    public function isSetupPending(SessionInterface $session): bool
    {
        return true === $session->get(self::SESSION_KEY_SETUP_PENDING, false);
    }

    public function clearSetupPending(SessionInterface $session): void
    {
        $session->remove(self::SESSION_KEY_SETUP_PENDING);
    }

    /**
     * @return list<Orga>
     */
    private function getRequiringOrgas(User $user): array
    {
        return array_values(array_filter(
            $user->getOrganisations()->toArray(),
            static fn ($orga): bool => $orga instanceof Orga && $orga->isTwoFactorRequired()
        ));
    }
}
