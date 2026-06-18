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
     * Returns the single deletion-workflow step the cron should perform for this user
     * on this run, or `null` if no action is due. "Latest applicable step wins": for a
     * rollout-era user whose lastLogin is already past the deletion threshold, this
     * returns `Delete` straight away (not a cascade of three emails in one run);
     * a user crossing W2 without ever having seen W1 gets `SendSecondWarning` only.
     *
     * Returns `null` when the feature isn't configured
     * (`account_deletion.first_warning_days` unset), so this is a safe no-op for projects
     * that haven't opted in. Progression gates on whether a MailSend FK is non-null on the
     * tracking row, NOT on its delivery status — a daily cron with an async mailer would
     * otherwise stall on mails stuck at 'queued'/'new'.
     */
    public function evaluateInactivityStep(UserInterface $user, ?AccountDeletionTracking $tracking): ?AccountDeletionStep
    {
        $firstWarningDays = $this->readIntParam('account_deletion.first_warning_days');

        if (null === $firstWarningDays || $this->isProtectedSystemUser($user)) {
            return null;
        }

        $step = $this->evaluateAgainstThresholds($user, $tracking, $firstWarningDays);
        $this->logIdentifiedStep($user, $step);

        return $step;
    }

    private function evaluateAgainstThresholds(
        UserInterface $user,
        ?AccountDeletionTracking $tracking,
        int $firstWarningDays,
    ): ?AccountDeletionStep {
        $stepDays = $this->readIntParam('account_deletion.warning_step_days') ?? 30;
        $secondWarningDays = $firstWarningDays + $stepDays;
        $deletionAfterDays = $firstWarningDays + 2 * $stepDays;

        $lastLogin = $user->getLastLogin();

        if (!$lastLogin instanceof DateTimeInterface) {
            return $this->daysSince($user->getCreatedDate()) >= $deletionAfterDays
                ? AccountDeletionStep::DeleteWithoutWarnings
                : null;
        }

        $daysInactive = $this->daysSince($lastLogin);
        $firstWarningSent = null !== $tracking?->getFirstWarningMail();
        $secondWarningSent = null !== $tracking?->getSecondWarningMail();

        // Plain ifs in weakest-to-strongest order — the latest assignment wins.
        // The `!secondWarningSent` guard on the W1 branch prevents a user who jumped
        // straight to W2 (no prior tracking) from regressing to W1 on the next cron run.
        $step = null;
        if ($daysInactive >= $firstWarningDays && !$firstWarningSent && !$secondWarningSent) {
            $step = AccountDeletionStep::SendFirstWarning;
        }
        if ($daysInactive >= $secondWarningDays && !$secondWarningSent) {
            $step = AccountDeletionStep::SendSecondWarning;
        }
        if ($daysInactive >= $deletionAfterDays) {
            // Users who reached D without ever having a warning mail attached
            // (rollout-era catch-up, or both warning mails failed to send) are
            // silently deleted — sending the "your account has been deleted"
            // notification out of nowhere would be confusing. Only users who
            // actually got at least one warning receive the courtesy notification.
            $step = !$firstWarningSent && !$secondWarningSent
                ? AccountDeletionStep::DeleteWithoutWarnings
                : AccountDeletionStep::Delete;
        }

        return $step;
    }

    private function isProtectedSystemUser(UserInterface $user): bool
    {
        $additionalIds = (array) $this->parameterBag->get('account_deletion.additional_protected_user_ids');

        return $user->isDefaultGuestUser()
            || AiApiUser::AI_API_USER_LOGIN === $user->getLogin()
            || in_array($user->getId(), $additionalIds, true);
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

    private function logIdentifiedStep(UserInterface $user, ?AccountDeletionStep $step): void
    {
        if (null === $step) {
            return;
        }

        $this->piiLogger->info(
            'Account deletion: identified step for user',
            [
                'pii' => [
                    'userId' => $user->getId(),
                    'login'  => $user->getLogin(),
                ],
                'orgaId' => $user->getOrganisationId(),
                'step'   => $step->value,
            ]
        );
    }
}
