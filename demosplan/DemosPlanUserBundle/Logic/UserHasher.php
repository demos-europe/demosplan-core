<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanUserBundle\Logic;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfigInterface;

class UserHasher
{
    private GlobalConfigInterface $globalConfig;

    public function __construct(GlobalConfigInterface $globalConfig)
    {
        $this->globalConfig = $globalConfig;
    }

    public function getPasswordEditHash(User $user): string
    {
        $hashString = $this->globalConfig->getSalt().$user->getLogin();
        // use last login date to automatically invalidate Hash when user logs in
        $hashString .= $user->getLastLogin() instanceof DateTime ? $user->getLastLogin()->getTimestamp() : '';
        $hash = hash('sha512', $hashString);

        return substr($hash, 0, 10);
    }

    public function isValidPasswordEditHash(User $user, string $hash): bool
    {
        $expected = $this->getPasswordEditHash($user);

        return $expected === $hash;
    }

    public function getChangeEmailHash(User $user, string $newEmail): string
    {
        $hashString = $this->globalConfig->getSalt().$user->getLogin().$newEmail;
        $hash = hash('sha512', $hashString);

        return substr($hash, 0, 10);
    }

    public function isValidChangeEmailHash(User $user, string $newEmail, string $hash): bool
    {
        $expected = $this->getChangeEmailHash($user, $newEmail);

        return $expected === $hash;
    }
}
