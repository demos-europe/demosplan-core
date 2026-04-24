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

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentLockEnforcementService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Pure unit test for the decision logic in SegmentLockEnforcementService.
 * Collaborators (PermissionsInterface, ParameterBagInterface) are mocked so
 * each of the four truth-table rows can be exercised independently.
 */
class SegmentLockEnforcementServiceTest extends TestCase
{
    protected ?SegmentLockEnforcementService $sut = null;

    public function testReturnsFalseWhenFeatureDisabled(): void
    {
        $this->sut = $this->buildSut(featureEnabled: false, hasPermission: false);

        self::assertFalse(
            $this->sut->isSegmentLockedForCurrentUser($this->segmentOnPlace(locked: true))
        );
    }

    public function testReturnsFalseWhenPlaceIsNotLocked(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: false);

        self::assertFalse(
            $this->sut->isSegmentLockedForCurrentUser($this->segmentOnPlace(locked: false))
        );
    }

    public function testReturnsFalseWhenCurrentUserHasAdministratePermission(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: true);

        self::assertFalse(
            $this->sut->isSegmentLockedForCurrentUser($this->segmentOnPlace(locked: true))
        );
    }

    public function testReturnsTrueWhenFeatureOnPlaceLockedAndPermissionMissing(): void
    {
        $this->sut = $this->buildSut(featureEnabled: true, hasPermission: false);

        self::assertTrue(
            $this->sut->isSegmentLockedForCurrentUser($this->segmentOnPlace(locked: true))
        );
    }

    public function testIsFeatureEnabledMirrorsConfigParameter(): void
    {
        self::assertTrue($this->buildSut(featureEnabled: true, hasPermission: false)->isFeatureEnabled());
        self::assertFalse($this->buildSut(featureEnabled: false, hasPermission: false)->isFeatureEnabled());
    }

    private function buildSut(bool $featureEnabled, bool $hasPermission): SegmentLockEnforcementService
    {
        $parameterBag = $this->createMock(ParameterBagInterface::class);
        $parameterBag->method('get')
            ->with(SegmentLockEnforcementService::CONFIG_PARAM_FEATURE_ENABLED)
            ->willReturn($featureEnabled);

        $permissions = $this->createMock(PermissionsInterface::class);
        $permissions->method('hasPermission')
            ->with(SegmentLockEnforcementService::PERMISSION_ADMINISTRATE)
            ->willReturn($hasPermission);

        return new SegmentLockEnforcementService($parameterBag, $permissions);
    }

    private function segmentOnPlace(bool $locked): Segment
    {
        $place = new Place(new Procedure(), 'test-place', 0);
        $place->setLocked($locked);

        $segment = new Segment();
        $segment->setPlace($place);

        return $segment;
    }
}
