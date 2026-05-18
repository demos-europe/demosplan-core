<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHasher;
use Tests\Base\FunctionalTestCase;

class UserHasherTest extends FunctionalTestCase
{
    /**
     * @var UserHasher
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(UserHasher::class);
    }

    public function testPasswordEditHash()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $expected = '41f7b2fc9d';
        $hash = $this->sut->getPasswordEditHash($user);
        self::assertEquals($expected, $hash);
    }

    public function testisValidPasswordEditHash()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $expected = '41f7b2fc9d';
        $isValid = $this->sut->isValidPasswordEditHash($user, $expected);
        self::assertTrue($isValid);

        $expected = 'notValid';
        $isValid = $this->sut->isValidPasswordEditHash($user, $expected);
        self::assertFalse($isValid);
    }

    public function testChangeEmailHash()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $newEmail = 'new@email.com';
        $expected = 'aa20926720';
        $hash = $this->sut->getChangeEmailHash($user, $newEmail);
        self::assertEquals($expected, $hash);
    }

    public function testisValidChangeEmailHash()
    {
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $newEmail = 'new@email.com';
        $expected = 'aa20926720';
        $isValid = $this->sut->isValidChangeEmailHash($user, $newEmail, $expected);
        self::assertTrue($isValid);

        $expected = 'notValid';
        $isValid = $this->sut->isValidChangeEmailHash($user, $newEmail, $expected);
        self::assertFalse($isValid);
    }
}
