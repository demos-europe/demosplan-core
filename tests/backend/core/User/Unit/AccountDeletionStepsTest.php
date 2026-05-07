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
use demosplan\DemosPlanCoreBundle\Logic\User\AccountDeletionStep;
use demosplan\DemosPlanCoreBundle\Logic\User\LastLoginActivityChecker;
use Psr\Log\NullLogger;
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
    /** Past the deletion threshold (90 days) but close enough that only the
     *  first deletion step is expected. */
    private const DAYS_PAST_DELETION_THRESHOLD = '-91 days';

    /** Far enough in the past that all three steps fire in a single cascade. */
    private const DAYS_LONG_INACTIVE = '-365 days';

    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new LastLoginActivityChecker(
            new ParameterBag([
                'account_deletion.first_warning_days'            => 30,
                'account_deletion.warning_step_days'             => 30,
                'account_deletion.additional_protected_user_ids' => [],
            ]),
            new NullLogger(),
            180
        );
    }

    public function testActiveUserReturnsEmpty(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-10 days'));

        $this->assertSame([], $this->sut->evaluateInactivitySteps($user, null));
    }

    public function testNullLastLoginRecentlyCreatedReturnsEmpty(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(null);
        $user->method('getCreatedDate')->willReturn(new DateTimeImmutable('-10 days'));

        $this->assertSame([], $this->sut->evaluateInactivitySteps($user, null));
    }

    public function testNullLastLoginPastDeletionWindowReturnsDeleteWithoutWarnings(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(null);
        $user->method('getCreatedDate')->willReturn(new DateTimeImmutable(self::DAYS_PAST_DELETION_THRESHOLD));

        $this->assertSame(
            [AccountDeletionStep::DeleteWithoutWarnings],
            $this->sut->evaluateInactivitySteps($user, null)
        );
    }

    public function testFirstWarningFiresAtThreshold(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-31 days'));

        $this->assertSame(
            [AccountDeletionStep::SendFirstWarning],
            $this->sut->evaluateInactivitySteps($user, null)
        );
    }

    public function testSecondWarningFiresWhenFirstMailAttached(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-61 days'));

        $tracking = new AccountDeletionTracking($this->createMock(User::class));
        $tracking->setFirstWarningMail($this->createMock(MailSend::class));

        $this->assertSame(
            [AccountDeletionStep::SendSecondWarning],
            $this->sut->evaluateInactivitySteps($user, $tracking)
        );
    }

    public function testCompressionAtSecondWindowWithoutTrackingReturnsBothWarnings(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable('-61 days'));

        $this->assertSame(
            [AccountDeletionStep::SendFirstWarning, AccountDeletionStep::SendSecondWarning],
            $this->sut->evaluateInactivitySteps($user, null)
        );
    }

    public function testDeleteFiresWhenBothMailsAttached(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_PAST_DELETION_THRESHOLD));

        $tracking = new AccountDeletionTracking($this->createMock(User::class));
        $tracking->setFirstWarningMail($this->createMock(MailSend::class));
        $tracking->setSecondWarningMail($this->createMock(MailSend::class));

        $this->assertSame(
            [AccountDeletionStep::Delete],
            $this->sut->evaluateInactivitySteps($user, $tracking)
        );
    }

    public function testFullCascadeCompressionWithoutTrackingReturnsAllThreeSteps(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_PAST_DELETION_THRESHOLD));

        $this->assertSame(
            [
                AccountDeletionStep::SendFirstWarning,
                AccountDeletionStep::SendSecondWarning,
                AccountDeletionStep::Delete,
            ],
            $this->sut->evaluateInactivitySteps($user, null)
        );
    }

    public function testAnonymousUserReturnsEmpty(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn(UserInterface::ANONYMOUS_USER_ID);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertSame([], $this->sut->evaluateInactivitySteps($user, null));
    }

    public function testAiApiUserReturnsEmpty(): void
    {
        $user = $this->createMock(UserInterface::class);
        $user->method('getLogin')->willReturn(AiApiUser::AI_API_USER_LOGIN);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertSame([], $this->sut->evaluateInactivitySteps($user, null));
    }

    public function testAdditionalProtectedUserIdReturnsEmpty(): void
    {
        $protectedId = '11111111-1111-1111-1111-111111111111';
        $sut = new LastLoginActivityChecker(
            new ParameterBag([
                'account_deletion.first_warning_days'            => 30,
                'account_deletion.warning_step_days'             => 30,
                'account_deletion.additional_protected_user_ids' => [$protectedId],
            ]),
            new NullLogger(),
            180
        );

        $user = $this->createMock(UserInterface::class);
        $user->method('getId')->willReturn($protectedId);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertSame([], $sut->evaluateInactivitySteps($user, null));
    }

    public function testFeatureDisabledReturnsEmpty(): void
    {
        $sut = new LastLoginActivityChecker(
            new ParameterBag([
                // first_warning_days intentionally not set — feature disabled
                'account_deletion.warning_step_days' => 30,
            ]),
            new NullLogger(),
            180
        );

        $user = $this->createMock(UserInterface::class);
        $user->method('getLastLogin')->willReturn(new DateTimeImmutable(self::DAYS_LONG_INACTIVE));

        $this->assertSame([], $sut->evaluateInactivitySteps($user, null));
    }
}
