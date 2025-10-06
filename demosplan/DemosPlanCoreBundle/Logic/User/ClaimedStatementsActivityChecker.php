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
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Repository\StatementRepository;

class ClaimedStatementsActivityChecker implements UserActivityInterface
{
    public function __construct(private readonly StatementRepository $statementRepository, private int $dayThreshold = 180)
    {
    }

    public function isUserActive(UserInterface $user): bool
    {
        $threshold = new DateTimeImmutable(sprintf('-%d days', $this->dayThreshold));

        // Count statements/segments assigned to the user and modified within the threshold period
        $claimedStatements = $this->statementRepository->findBy([
            'assignee' => $user,
        ]);

        if (empty($claimedStatements)) {
            return false;
        }

        // Check if any of the claimed statements were modified within the threshold
        foreach ($claimedStatements as $statement) {
            $modifyDate = $statement->getModified();
            if ($modifyDate && $modifyDate >= $threshold) {
                return true;
            }
        }

        // If user has claimed statements but none were modified recently, consider them inactive
        return false;
    }

    public function getActivityDescription(): string
    {
        return sprintf('User has claimed statements or segments with activity within the last %d days', $this->dayThreshold);
    }

    public function getPriority(): int
    {
        return 75; // Medium-high priority for work-based activity
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
