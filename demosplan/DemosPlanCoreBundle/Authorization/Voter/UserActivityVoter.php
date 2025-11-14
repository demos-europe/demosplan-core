<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Authorization\Voter;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\UserActivityInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserActivityVoter extends Voter
{
    public const IS_ACTIVE_USER = 'IS_ACTIVE_USER';

    /**
     * @var UserActivityInterface[]
     */
    private array $activityCheckers = [];

    /**
     * @param iterable<UserActivityInterface> $activityCheckers
     */
    public function __construct(iterable $activityCheckers = [])
    {
        foreach ($activityCheckers as $checker) {
            $this->addActivityChecker($checker);
        }
    }

    public function addActivityChecker(UserActivityInterface $checker): void
    {
        $this->activityCheckers[] = $checker;
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::IS_ACTIVE_USER === $attribute && $subject instanceof UserInterface;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        if (!$subject instanceof UserInterface) {
            return false;
        }

        // If no activity checkers are configured, consider all users as active
        if ([] === $this->activityCheckers) {
            return true;
        }

        // Sort checkers by priority (highest first)
        $sortedCheckers = $this->activityCheckers;
        usort($sortedCheckers, static fn (UserActivityInterface $a, UserActivityInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        // Check activity with each checker, returning true if any checker considers the user active
        foreach ($sortedCheckers as $checker) {
            if ($checker->isUserActive($subject)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all registered activity checkers, sorted by priority.
     *
     * @return UserActivityInterface[]
     */
    public function getActivityCheckers(): array
    {
        $checkers = $this->activityCheckers;
        usort($checkers, static fn (UserActivityInterface $a, UserActivityInterface $b): int => $b->getPriority() <=> $a->getPriority()
        );

        return $checkers;
    }
}
