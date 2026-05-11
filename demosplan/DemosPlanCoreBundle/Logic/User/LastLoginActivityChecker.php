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
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Logger\PiiAwareLogger;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class LastLoginActivityChecker implements UserActivityInterface
{
    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly PiiAwareLogger $piiLogger,
        private int $dayThreshold = 180,
    ) {
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
        // Any of these flags indicates the user got past initial setup.
        if (
            !$user->isNewUser()
            || $user->isProfileCompleted()
            || $user->isAccessConfirmed()
        ) {
            return true;
        }

        // Fallback: the user record was modified after creation.
        return $user->getCreatedDate() != $user->getModifiedDate();
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

    /**
     * Returns the deletion-workflow steps the cron should perform for this user on this
     * run. Empty list means no action; multiple entries may fire in order (rollout-era
     * catch-up where the first-warning, second-warning, and deletion thresholds are all
     * crossed at once).
     *
     * Returns an empty list when the feature isn't configured
     * (`account_deletion.first_warning_days` unset), so this is a safe no-op for projects
     * that haven't opted in. Progression gates on whether a MailSend FK is non-null on the
     * tracking row, NOT on its delivery status — a daily cron with an async mailer would
     * otherwise stall on mails stuck at 'queued'/'new'.
     *
     * @return list<AccountDeletionStep>
     */
    public function evaluateInactivitySteps(UserInterface $user, ?AccountDeletionTracking $tracking): array
    {
        $firstWarningDays = $this->readIntParam('account_deletion.first_warning_days');

        if (null === $firstWarningDays) {
            return [];
        }

        if ($this->isProtectedSystemUser($user)) {
            return [];
        }

        $steps = $this->evaluateAgainstThresholds($user, $tracking, $firstWarningDays);
        $this->logIdentifiedSteps($user, $steps);

        return $steps;
    }

    /**
     * @return list<AccountDeletionStep>
     */
    private function evaluateAgainstThresholds(
        UserInterface $user,
        ?AccountDeletionTracking $tracking,
        int $firstWarningDays,
    ): array {
        $stepDays = $this->readIntParam('account_deletion.warning_step_days') ?? 30;
        $secondWarningDays = $firstWarningDays + $stepDays;
        $deletionAfterDays = $firstWarningDays + 2 * $stepDays;

        $lastLogin = $user->getLastLogin();

        if (!$lastLogin instanceof DateTimeInterface) {
            return $this->daysSince($user->getCreatedDate()) >= $deletionAfterDays
                ? [AccountDeletionStep::DeleteWithoutWarnings]
                : [];
        }

        $daysInactive = $this->daysSince($lastLogin);
        $firstWarningSent = null !== $tracking?->getFirstWarningMail();
        $secondWarningSent = null !== $tracking?->getSecondWarningMail();

        $steps = [];

        if (!$firstWarningSent && $daysInactive >= $firstWarningDays) {
            $steps[] = AccountDeletionStep::SendFirstWarning;
        }

        if (!$secondWarningSent && $daysInactive >= $secondWarningDays) {
            $steps[] = AccountDeletionStep::SendSecondWarning;
        }

        if ($daysInactive >= $deletionAfterDays) {
            $steps[] = AccountDeletionStep::Delete;
        }

        return $steps;
    }

    private function isProtectedSystemUser(UserInterface $user): bool
    {
        if (UserInterface::ANONYMOUS_USER_ID === $user->getId()) {
            return true;
        }

        if (AiApiUser::AI_API_USER_LOGIN === $user->getLogin()) {
            return true;
        }

        $additionalIds = (array) $this->parameterBag->get('account_deletion.additional_protected_user_ids');

        return in_array($user->getId(), $additionalIds, true);
    }

    private function readIntParam(string $name): ?int
    {
        if (!$this->parameterBag->has($name)) {
            return null;
        }

        $value = $this->parameterBag->get($name);

        return null === $value ? null : (int) $value;
    }

    private function daysSince(DateTimeInterface $point): int
    {
        return (int) $point->diff(new DateTimeImmutable())->days;
    }

    /**
     * @param list<AccountDeletionStep> $steps
     */
    private function logIdentifiedSteps(UserInterface $user, array $steps): void
    {
        if ([] === $steps) {
            return;
        }

        $this->piiLogger->info(
            'Account deletion: identified steps for user',
            [
                'pii' => [
                    'userId' => $user->getId(),
                    'login'  => $user->getLogin(),
                ],
                'orgaId' => $user->getOrganisationId(),
                'steps'  => array_map(static fn (AccountDeletionStep $step) => $step->value, $steps),
            ]
        );
    }
}
