<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Platform;

use DemosEurope\DemosplanAddon\Contracts\PermissionsInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Platform\EntryPointDecider;
use Tests\Base\FunctionalTestCase;

class EntryPointDeciderTest extends FunctionalTestCase
{
    /** @var EntryPointDecider */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(EntryPointDecider::class);
    }

    //    /**
    //     * @param User $user
    //     * @param Permissions $permissions
    //     * @param string $path
    //     * @dataProvider entryPointDataProvider
    //     */
    //    public function testDetermineEntryPointForUser(User $user, Permissions $permissions, string $path): void
    public function testDetermineEntryPointForUser(): void
    {
        self::markSkippedForCIIntervention();
        // This test needs to be rewritten with correct autowiring

        $entryPointCombinations = $this->entryPointDataProvider();

        foreach ($entryPointCombinations as $entryPointCombination) {
            [$user, $path] = $entryPointCombination;

            $redirectResponse = $this->sut->determineEntryPointForUser($user);

            $this->assertEquals(
                'http://localhost'.$path,
                $redirectResponse->getTargetUrl(),
                $this->formatUserInfo($user)
            );
        }
    }

    protected function formatUserInfo(User $user): string
    {
        try {
            return sprintf("User %s\nRoles: %s", $user->getName(), implode(',', $user->getDplanRolesArray()));
        } catch (CustomerNotFoundException $e) {
            return "User: {$user->getName()}";
        }
    }

    public function entryPointDataProvider(): array
    {
        $permissions = self::getContainer()->get(PermissionsInterface::class);

        $entryPointCombinations = [];

        /** @var User $guestUser */
        $guestUser = $this->fixtures->getReference(LoadUserData::TEST_USER_GUEST);
        $permissions->initPermissions($guestUser);

        $entryPointCombinations[] = [
            $guestUser,
            $permissions,
            '/',
        ];

        /** @var User $citizenUser */
        $citizenUser = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $permissions->initPermissions($citizenUser);

        $entryPointCombinations[] = [
            $citizenUser,
            $permissions,
            '/',
        ];

        /** @var User $plannerUser */
        $plannerUser = $this->fixtures->getReference(LoadUserData::TEST_USER_FP_ONLY);
        $permissions->initPermissions($plannerUser);

        $entryPointCombinations[] = [
            $plannerUser,
            $permissions,
            '/verfahren/verwalten',
        ];

        return $entryPointCombinations;
    }
}
