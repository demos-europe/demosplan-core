<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentLockEnforcementService;
use PHPUnit\Framework\TestCase;

/**
 * Pure unit test for the decision logic in SegmentLockEnforcementService.
 * The sole collaborator (CurrentUserInterface) is mocked so each of the four
 * truth-table rows can be exercised independently.
 */
class SegmentLockEnforcementServiceTest extends TestCase
{
    protected ?SegmentLockEnforcementService $sut = null;

    public function testReturnsFalseWhenFeatureDisabled(): void
    {
        $this->sut = $this->buildSut(featureEnabled: false, hasPermission: false);

        self::assertFalse(
            $this->sut->isPlaceLockedForCurrentUser($this->place(locked: true))
        );
    }

    public function testReturnsFalseWhenPlaceIsNotLocked(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: false);

        self::assertFalse(
            $this->sut->isPlaceLockedForCurrentUser($this->place(locked: false))
        );
    }

    public function testReturnsFalseWhenPlaceIsNull(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: false);

        self::assertFalse($this->sut->isPlaceLockedForCurrentUser(null));
    }

    public function testReturnsFalseWhenCurrentUserHasAdministratePermission(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: true);

        self::assertFalse(
            $this->sut->isPlaceLockedForCurrentUser($this->place(locked: true))
        );
    }

    public function testReturnsTrueWhenFeatureOnPlaceLockedAndPermissionMissing(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: false);

        self::assertTrue(
            $this->sut->isPlaceLockedForCurrentUser($this->place(locked: true))
        );
    }

    public function testIsFeatureEnabledMirrorsFeaturePermission(): void
    {
        self::assertTrue($this->buildSut(featureEnabled: true, hasPermission: false)->isFeatureEnabled());
        self::assertFalse($this->buildSut(featureEnabled: false, hasPermission: false)->isFeatureEnabled());
    }

    public function testIsEnforcementApplicableComposesFeatureFlagAndAdminPermission(): void
    {
        self::assertFalse($this->buildSut(featureEnabled: false, hasPermission: false)->isEnforcementApplicable());
        self::assertFalse($this->buildSut(featureEnabled: false, hasPermission: true)->isEnforcementApplicable());
        self::assertFalse($this->buildSut(featureEnabled: true, hasPermission: true)->isEnforcementApplicable());
        self::assertTrue($this->buildSut(featureEnabled: true, hasPermission: false)->isEnforcementApplicable());
    }

    private function buildSut(bool $featureEnabled, bool $hasPermission): SegmentLockEnforcementService
    {
        $currentUser = $this->createMock(CurrentUserInterface::class);
        $currentUser->method('hasPermission')
            ->willReturnMap([
                [SegmentLockEnforcementService::PERMISSION_FEATURE_ENABLED, $featureEnabled],
                [SegmentLockEnforcementService::PERMISSION_ADMINISTRATE, $hasPermission],
            ]);

        return new SegmentLockEnforcementService($currentUser);
    }

    private function place(bool $locked): Place
    {
        $def = new ProcedurePhaseDefinition();
        $place = new Place(new Procedure($def, $def), 'test-place', 0);
        $place->setLocked($locked);

        return $place;
    }
}
