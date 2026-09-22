<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Unit;

use DateTimeImmutable;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\Entity\MailSend;
use demosplan\DemosPlanCoreBundle\Entity\User\AccountDeletionTracking;
use demosplan\DemosPlanCoreBundle\Entity\User\AiApiUser;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logger\PiiAwareLogger;
use demosplan\DemosPlanCoreBundle\Logic\User\AccountDeletionStep;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Tests\Base\UnitTestCase;

/**
 * Unit tests for the inactivity-deletion decision logic on
 * LastLoginActivityChecker. Throughout these tests the configured cadence is
 * firstWarningDays = 30, warningStepDays = 30, so the derived thresholds are
 * secondWarningDays = 60 and deletionAfterDays = 90.
 */
class AccountDeletionStepsTest extends UnitTestCase
{
    /** Past the deletion threshold (90 days). */
    private const DAYS_PAST_DELETION_THRESHOLD = '-91 days';

    /** Far enough in the past for any inactivity threshold; used by tests that
     *  only care whether protected/system users are excluded. */
    private const DAYS_LONG_INACTIVE = '-365 days';

    protected $sut;
    protected $piiLogger;

    protected function setUp(): void
    {
        parent::setUp();
        $this->piiLogger = $this->createMock(PiiAwareLogger::class);
        $this->sut = new LastLoginActivityChecker(
            new ParameterBag([
                'account_deletion.first_warning_days'            => 30,
                'account_deletion.warning_step_days'             => 30,
                'account_deletion.additional_protected_user_ids' => [],
            ]),
            $this->piiLogger,
            180
        );
    }

    public function testActiveUserReturnsNull(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-10 days'));

        $this->assertNull($this->sut->evaluateInactivityStep($user, null));
    }

    public function testNullLastLoginRecentlyCreatedReturnsNull(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(null);
        $user->method('getCreatedDate')->willReturn(new DateTimeImmutable('-10 days'));

        $this->assertNull($this->sut->evaluateInactivityStep($user, null));
    }

    public function testNullLastLoginPastDeletionWindowReturnsDeleteWithoutWarnings(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(null);
        $user->method('getCreatedDate')->willReturn(new DateTimeImmutable(self::DAYS_PAST_DELETION_THRESHOLD));

        $this->assertSame(
            AccountDeletionStep::DeleteWithoutWarnings,
            $this->sut->evaluateInactivityStep($user, null)
        );
    }

    public function testFirstWarningFiresAtThreshold(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-31 days'));

        $this->assertSame(
            AccountDeletionStep::SendFirstWarning,
            $this->sut->evaluateInactivityStep($user, null)
        );
    }

    public function testSecondWarningFiresWhenFirstMailAttached(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-61 days'));

        $tracking = new AccountDeletionTracking($this->createMock(User::class));
        $tracking->setFirstWarningMail($this->createMock(MailSend::class));

        $this->assertSame(
            AccountDeletionStep::SendSecondWarning,
            $this->sut->evaluateInactivityStep($user, $tracking)
        );
    }

    public function testJumpsStraightToSecondWarningWhenPastSecondWindowWithoutTracking(): void
    {
        // Rollout-era user inactive past W2 with no prior tracking row: emits
        // SendSecondWarning only (W1 is skipped — the user already missed that
        // window, sending W1 today alongside W2 would be confusing).
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-61 days'));

        $this->assertSame(
            AccountDeletionStep::SendSecondWarning,
            $this->sut->evaluateInactivityStep($user, null)
        );
    }

    public function testDoesNotRegressToFirstWarningAfterSecondWarningWasSentDirectly(): void
    {
        // Reproduces the day-66 scenario: on day 65 we jumped straight to W2
        // (no prior tracking), setting secondWarningMail. On day 66 we must NOT
        // fall back to W1 just because firstWarningMail is still null —
        // the user has already passed that stage.
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-66 days'));

        $tracking = new AccountDeletionTracking($this->createMock(User::class));
        $tracking->setSecondWarningMail($this->createMock(MailSend::class));

        $this->assertNull($this->sut->evaluateInactivityStep($user, $tracking));
    }

    public function testDeleteFiresWhenBothMailsAttached(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_PAST_DELETION_THRESHOLD));

        $tracking = new AccountDeletionTracking($this->createMock(User::class));
        $tracking->setFirstWarningMail($this->createMock(MailSend::class));
        $tracking->setSecondWarningMail($this->createMock(MailSend::class));

        $this->assertSame(
            AccountDeletionStep::Delete,
            $this->sut->evaluateInactivityStep($user, $tracking)
        );
    }

    public function testRolloutUserPastDeletionWindowGetsSilentDeletion(): void
    {
        // Rollout-era user already past D with no prior tracking — they
        // never received a warning, so we silently soft-delete them rather
        // than sending a confusing "your account has been deleted"
        // notification out of nowhere.
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_PAST_DELETION_THRESHOLD));

        $this->assertSame(
            AccountDeletionStep::DeleteWithoutWarnings,
            $this->sut->evaluateInactivityStep($user, null)
        );
    }

    public function testAnonymousUserReturnsNull(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('isDefaultGuestUser')->willReturn(true);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertNull($this->sut->evaluateInactivityStep($user, null));
    }

    public function testAiApiUserReturnsNull(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLogin')->willReturn(AiApiUser::AI_API_USER_LOGIN);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertNull($this->sut->evaluateInactivityStep($user, null));
    }

    public function testAdditionalProtectedUserIdReturnsNull(): void
    {
        $protectedId = '11111111-1111-1111-1111-111111111111';
        $sut = new LastLoginActivityChecker(
            new ParameterBag([
                'account_deletion.first_warning_days'            => 30,
                'account_deletion.warning_step_days'             => 30,
                'account_deletion.additional_protected_user_ids' => [$protectedId],
            ]),
            $this->piiLogger,
            180
        );

        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn($protectedId);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertNull($sut->evaluateInactivityStep($user, null));
    }

    public function testFeatureDisabledReturnsNull(): void
    {
        $sut = new LastLoginActivityChecker(
            new ParameterBag([
                // first_warning_days intentionally not set — feature disabled
                'account_deletion.warning_step_days' => 30,
            ]),
            $this->piiLogger,
            180
        );

        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertNull($sut->evaluateInactivityStep($user, null));
    }
}
