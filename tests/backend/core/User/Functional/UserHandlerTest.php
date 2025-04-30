<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ReservedSystemNameException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use Tests\Base\FunctionalTestCase;

class UserHandlerTest extends FunctionalTestCase
{
    /**
     * @var UserHandler
     */
    protected $sut;

    /**
     * @var User
     */
    protected $testUser;
    /**
     * @var \demosplan\DemosPlanCoreBundle\Logic\ContentService|object|null
     */
    protected $contentService;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(UserHandler::class);
        // generiere ein Stub vom GlobalConfig
        $stub = $this->getMockBuilder(
            GlobalConfig::class
        )
            ->disableOriginalConstructor()
            ->getMock();
        $this->sut->setDemosplanConfig($stub);

        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->contentService = self::getContainer()->get(ContentService::class);
    }

    public function testWipeUser()
    {
        $this->wipeUserDataFields();
    }

    /**
     * Tests direct Fields of User
     * and also Orga ,Department, Flags, Addresses, Roles.
     */
    private function wipeUserDataFields()
    {
        // todo: check permissions, featruesfields, condition(no user in orga but orga still have a proceudre)
        // multi delete: foreach deleted element, a specific information message
        // before and after checks:
        // statements + versions, draftstatements  versions:

        /** @var User $userToWipe */
        $userToWipe = $this->fixtures->getReference('testUserDelete');

        // check UserFields before:
        static::assertFalse($userToWipe->isDeleted());
        static::assertFalse($userToWipe->isNewUser());

        static::assertTrue($userToWipe->isAccessConfirmed());
        static::assertTrue($userToWipe->isIntranet());
        static::assertTrue($userToWipe->isInvited());
        static::assertTrue($userToWipe->isProfileCompleted());
        static::assertFalse($userToWipe->isLegacy());
        static::assertNotNull($userToWipe->getTitle());
        static::assertNotNull($userToWipe->getGender());
        static::assertNotNull($userToWipe->getLogin());
        static::assertNotNull($userToWipe->getAddress());
        static::assertNotNull($userToWipe->getLanguage());
        static::assertNotNull($userToWipe->getGwId());
        static::assertNotNull($userToWipe->getFirstname());
        static::assertNotNull($userToWipe->getLastname());
        static::assertNotNull($userToWipe->getPassword());

        static::assertEquals('myLoginString', $userToWipe->getUserIdentifier());

        static::assertNotEmpty($userToWipe->getEmail());
        static::assertNotEmpty($userToWipe->getSalt());
        static::assertNotEmpty($userToWipe->getPassword());
        static::assertNotEmpty($userToWipe->getAddresses());

        $settings = $this->contentService->getSettingsOfUser($userToWipe->getId());
        static::assertNotEmpty($settings);

        static::assertNotEmpty($userToWipe->getAddresses());

        $departmentId = $userToWipe->getDepartment()->getId();
        $orgaId = $userToWipe->getOrga()->getId();

        $user = $this->sut->wipeUserData($userToWipe->getId());
        static::assertNotFalse($user);

        /** @var User $userToWipe */
        $wipedUser = $this->sut->getSingleUser($user->getId());

        // check UserFields after:
        static::assertTrue($wipedUser->isDeleted());
        static::assertFalse($wipedUser->isAccessConfirmed());
        static::assertFalse($wipedUser->isIntranet());
        static::assertFalse($wipedUser->isLegacy());
        static::assertFalse($wipedUser->isNewUser());
        static::assertFalse($wipedUser->isInvited());
        static::assertFalse($wipedUser->isProfileCompleted());
        static::assertNull($wipedUser->getTitle());
        static::assertNull($wipedUser->getGender());
        static::assertNull($wipedUser->getLogin());
        static::assertNull($wipedUser->getAddress());
        static::assertNull($wipedUser->getLanguage());
        static::assertNull($wipedUser->getPassword());
        static::assertNull($wipedUser->getGwId());
        static::assertNull($wipedUser->getFirstname());
        static::assertNull($wipedUser->getLastname());
        static::assertNull($wipedUser->getPassword());

        static::assertIsInt($wipedUser->getEmail());
        static::assertEmpty($wipedUser->getSalt());
        // because of unable to set null (part of FOS\UserBundle\Model\User.php)
        static::assertEmpty($wipedUser->getPassword());
        static::assertEmpty($wipedUser->getAddresses());

        static::assertTrue(is_array($wipedUser->getRoles()));
        static::assertCount(0, $wipedUser->getRoles());

        static::assertEquals('', $wipedUser->getUserIdentifier());
        static::assertEquals($departmentId, $wipedUser->getDepartment()->getId());
        static::assertEquals($orgaId, $wipedUser->getOrga()->getId());

        static::assertEmpty($wipedUser->getAddresses());
        static::assertEquals('', $wipedUser->getDplanRolesString());

        $settings = $this->contentService->getSettingsOfUser($wipedUser->getId());
        static::assertEmpty($settings);
    }

    public function testSetAccessConfirmed()
    {
        /** @var User $testUser */
        $testUser = $this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $user = $this->sut->setAccessConfirmed($testUser);
        self::assertTrue($user->getFlag(UserFlagKey::ACCESS_CONFIRMED->value));
        self::assertEquals($testUser->getId(), $user->getId());
    }

    public function wipeUserStatements()
    {
        /*
        get user
        set statementversions + statements
        check for statemetns and statementsversion
        delete user
        check for statements and statementsversion
            ->assertNotNull!
        *
         */
    }

    public function testDenyAddDepartmentWithSystemName()
    {
        $this->expectException(ReservedSystemNameException::class);
        $testOrga = $this->testUser->getOrga();
        $this->sut->addDepartment($testOrga->getId(), ['name' => 'Keine Abteilung']);
    }

    public function testAddDepartmentWithSystemName()
    {
        $testOrga = $this->testUser->getOrga();
        $createdDepartment = $this->sut->addDepartment($testOrga->getId(), ['name' => 'Mein eigener Department Name']);
        static::assertInstanceOf(Department::class, $createdDepartment);
    }
}
