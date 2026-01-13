<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DateTimeImmutable;
use DateTimeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;

class LastLoginActivityChecker implements UserActivityInterface
{
    public function __construct(private int $dayThreshold = 180)
    {
    }

    public function isUserActive(UserInterface $user): bool
    {
        $lastLogin = $user->getLastLogin();

        if (!$lastLogin instanceof DateTimeInterface) {
            // User has never logged in - check other indicators of user activity
            return $this->hasUserEverBeenActive($user);
        }

        $threshold = new DateTimeImmutable(sprintf('-%d days', $this->dayThreshold));

        return $lastLogin >= $threshold;
    }

    private function hasUserEverBeenActive(UserInterface $user): bool
    {
        // If user is no longer marked as "new", they have completed initial setup
        if (!$user->isNewUser()) {
            return true;
        }

        // If profile is completed, user has done some work
        if ($user->isProfileCompleted()) {
            return true;
        }

        // If access is confirmed, user has been administratively activated
        if ($user->isAccessConfirmed()) {
            return true;
        }

        // If the user record has been modified since creation, there has been some activity
        $createdDate = $user->getCreatedDate();
        $modifiedDate = $user->getModifiedDate();

        // User appears to be completely inactive - never logged in and no signs of activity
        return $createdDate && $modifiedDate && $createdDate != $modifiedDate;
    }

    public function getActivityDescription(): string
    {
        return sprintf('User has logged in within the last %d days or shows signs of account activity', $this->dayThreshold);
    }

    public function getPriority(): int
    {
        return 100; // High priority for login-based activity
    }

    public function getDayThreshold(): int
    {
        return $this->dayThreshold;
    }

    public function setDayThreshold(int $dayThreshold): void
    {
        $this->dayThreshold = $dayThreshold;
    }
}
