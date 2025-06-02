<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\User;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;

interface UserActivityInterface
{
    /**
     * Check if a user is considered active based on the specific implementation criteria.
     *
     * @param UserInterface $user The user to check activity for
     *
     * @return bool True if the user is considered active, false otherwise
     */
    public function isUserActive(UserInterface $user): bool;

    /**
     * Get a human-readable description of what this activity checker considers as "active".
     *
     * @return string Description of the activity criteria
     */
    public function getActivityDescription(): string;

    /**
     * Get the priority of this activity checker. Higher values mean higher priority.
     * When multiple checkers are used, the one with the highest priority takes precedence.
     *
     * @return int Priority value (default: 0)
     */
    public function getPriority(): int;
}
