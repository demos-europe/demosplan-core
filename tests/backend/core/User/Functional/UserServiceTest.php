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
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\AddressBookEntry;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserAlreadyExistsException;
use demosplan\DemosPlanCoreBundle\Logic\ContentService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use demosplan\DemosPlanCoreBundle\Types\UserFlagKey;
use demosplan\DemosPlanCoreBundle\ValueObject\SettingsFilter;
use Exception;
use Tests\Base\FunctionalTestCase;

class UserServiceTest extends FunctionalTestCase
{
    /**
     * @var UserService
     */
    protected $sut;

    /**
     * @var User
     */
    protected $testUser;

    /**
     * @var Orga
     */
    protected $testOrgaFp;

    /**
     * @var Department
     */
    protected $testDepartment;

    /**
     * @var Address
     */
    protected $testAddress;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(UserService::class);
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->testOrgaFp = $this->fixtures->getReference('testOrgaFP');
        $this->testDepartment = $this->fixtures->getReference('testDepartment');
        $this->testAddress = $this->fixtures->getReference('testAddress');
    }

    public function testGetUser()
    {
        $user = $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertTrue($user->getNoPiwik());
        static::assertFalse($user->isNewUser());
    }

    public function testUpdateUser()
    {
        $userBefore = clone $this->sut->getSingleUser($this->testUser->getId());
        $data = [
            'email'     => 'new@email.de',
            'firstname' => 'newFirstname',
            'lastname'  => 'newLastname',
        ];
        $user = $this->sut->updateUser($this->testUser->getId(), $data);
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertFalse($user->isNewUser());
        static::assertNotEquals($userBefore->getEmail(), $user->getEmail());
        static::assertEquals($data['email'], $user->getEmail());
    }

    public function testUpdateUserAddressComplete()
    {
        $userBefore = clone $this->sut->getSingleUser($this->testUser->getId());
        $data = [
            'email'              => 'new@email.de',
            'firstname'          => 'newFirstname',
            'lastname'           => 'newLastname',
            'address_postalcode' => 'postalcodeNew',
            'address_street'     => 'streetNew',
            'address_city'       => 'cityNew',
            'address_state'      => 'stateNew',
        ];
        $user = $this->sut->updateUser($this->testUser->getId(), $data);
        $address = $user->getAddress();
        static::assertEquals($data['address_postalcode'], $address->getPostalcode());
        static::assertEquals($data['address_street'], $address->getStreet());
        static::assertEquals($data['address_city'], $address->getCity());
        static::assertEquals($data['address_state'], $address->getState());
    }

    public function testUpdateUserAddressPartial()
    {
        $userBefore = clone $this->sut->getSingleUser($this->testUser->getId());
        $userBeforeAddress = $userBefore->getAddress();
        $data = [
            'email'              => 'new@email.de',
            'firstname'          => 'newFirstname',
            'lastname'           => 'newLastname',
            'address_postalcode' => 'postalcodeNew',
            'address_city'       => 'cityNew',
            'address_state'      => 'stateNew',
        ];
        $user = $this->sut->updateUser($this->testUser->getId(), $data);
        $address = $user->getAddress();
        static::assertEquals($data['address_postalcode'], $address->getPostalcode());
        static::assertEquals($userBeforeAddress->getStreet(), $address->getStreet());
        static::assertEquals($data['address_city'], $address->getCity());
        static::assertEquals($data['address_state'], $address->getState());
    }

    public function testUpdateUserOrga()
    {
        $userBefore = clone $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testOrgaFp->getId(), $userBefore->getOrga()->getId());
        $userBeforeOrgaUsersCount = $userBefore->getOrga()->getUsers()->count();
        $data = [
            'organisationId' => $this->fixtures->getReference('testOrgaPB')->getId(),
        ];
        $user = $this->sut->updateUser($this->testUser->getId(), $data);
        static::assertEquals($this->fixtures->getReference('testOrgaPB')->getId(), $user->getOrga()->getId());
        // User has been deleted from former Orga
        static::assertEquals($userBeforeOrgaUsersCount - 1, $userBefore->getOrga()->getUsers()->count());
    }

    public function testUpdateUserDepartment()
    {
        $userBefore = clone $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testDepartment->getId(), $userBefore->getDepartment()->getId());
        $userBeforeDepartmentUsersCount = $userBefore->getDepartment()->getUsers()->count();
        $data = [
            'departmentId' => $this->fixtures->getReference('testDepartmentMasterToeb')->getId(),
        ];
        $user = $this->sut->updateUser($this->testUser->getId(), $data);
        static::assertEquals($this->fixtures->getReference('testDepartmentMasterToeb')->getId(), $user->getDepartment()->getId());
        static::assertContains($user, $user->getDepartment()->getUsers());
        // User has been deleted from former Department
        static::assertEquals($userBeforeDepartmentUsersCount - 1, $userBefore->getDepartment()->getUsers()->count());
    }

    public function testAddUser()
    {
        $data = [
            'email'                                       => 'new@email.de',
            'firstname'                                   => 'newFirstname',
            'lastname'                                    => 'newLastname',
            'gender'                                      => 'female',
            'login'                                       => 'myLogin',
            'password'                                    => md5('myPassword'),
            UserFlagKey::IS_NEW_USER->value               => true,
            UserFlagKey::PROFILE_COMPLETED->value         => false,
            UserFlagKey::ACCESS_CONFIRMED->value          => false,
            UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value => false,
        ];
        $user = $this->sut->addUser($data);
        static::assertTrue($user->isNewUser());
        static::assertEquals($data['email'], $user->getEmail());
    }

    public function testAddUserNoDefaultFlags()
    {
        $data = [
            'email'         => 'new@email.de',
            'firstname'     => 'newFirstname',
            'lastname'      => 'newLastname',
            'gender'        => 'female',
            'login'         => 'myLogin',
            'password'      => md5('myPassword'),
        ];
        $user = $this->sut->addUser($data);
        static::assertTrue($user->isNewUser());
        static::assertEquals($data['email'], $user->getEmail());
        static::assertFalse($user->isAccessConfirmed());
        static::assertFalse($user->isProfileCompleted());
        static::assertTrue($user->isNewUser());
    }

    public function testAddUserWithExistingLogin()
    {
        $this->expectException(UserAlreadyExistsException::class);

        $data = [
            'email'         => 'new@email.de',
            'firstname'     => 'newFirstname',
            'lastname'      => 'newLastname',
            'gender'        => 'female',
            'login'         => 'myLogin',
            'password'      => md5('myPassword'),
        ];
        $user = $this->sut->addUser($data);
        static::assertTrue($user->isNewUser());
        static::assertEquals($data['email'], $user->getEmail());
        static::assertFalse($user->isAccessConfirmed());
        static::assertFalse($user->isProfileCompleted());
        static::assertTrue($user->isNewUser());

        $data = [
            'email'         => 'new2@email.de',
            'firstname'     => 'newFirstname2',
            'lastname'      => 'newLastname2',
            'login'         => 'myLogin',
            'password'      => md5('myPassword2'),
        ];
        $user = $this->sut->addUser($data);
    }

    public function testAddUserWithAddress()
    {
        $data = [
            'email'                                       => 'new@email.de',
            'firstname'                                   => 'newFirstname',
            'lastname'                                    => 'newLastname',
            'gender'                                      => 'female',
            'login'                                       => 'myLogin',
            'password'                                    => md5('myPassword'),
            UserFlagKey::IS_NEW_USER->value               => true,
            UserFlagKey::PROFILE_COMPLETED->value         => false,
            UserFlagKey::ACCESS_CONFIRMED->value          => false,
            UserFlagKey::WANTS_FORUM_NOTIFICATIONS->value => false,
            'address'                                     => $this->testAddress,
        ];
        $user = $this->sut->addUser($data);
        static::assertInstanceof('demosplan\DemosPlanCoreBundle\Entity\User\Address', $user->getAddress());
    }

    public function testUpdatePiwikUser()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'email'     => 'new@email.de',
            'firstname' => 'newFirstname',
            'lastname'  => 'newLastname',
        ];
        // no key "noPiwik" given should set noPiwik to false
        // -> true, but this is not set in the service but in the handler
        /*
        $user = $this->sut->updateUser($this->testUser->getId(), $data);
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertFalse($user->getNoPiwik());
        static::assertFalse($user->isNewUser());
        */
    }

    public function testGetUserOrga()
    {
        $user = $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertInstanceOf('demosplan\DemosPlanCoreBundle\Entity\User\Orga', $user->getOrga());
        static::assertEquals($this->testOrgaFp->getId(), $user->getOrga()->getId());
    }

    public function testGetUserDepartment()
    {
        $user = $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertInstanceOf('demosplan\DemosPlanCoreBundle\Entity\User\Department', $user->getDepartment());
        static::assertEquals($this->testDepartment->getId(), $user->getDepartment()->getId());
    }

    public function testGetUserAddress()
    {
        $user = $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertInstanceOf('demosplan\DemosPlanCoreBundle\Entity\User\Address', $user->getAddress());
        static::assertEquals($this->testAddress->getId(), $user->getAddress()->getId());
    }

    public function testGetUserRoles()
    {
        self::markSkippedForCIIntervention();

        $user = $this->sut->getSingleUser($this->testUser->getId());
        static::assertEquals($this->testUser->getId(), $user->getId());
        static::assertContains(Role::PUBLIC_AGENCY_COORDINATION, $user->getDplanRolesString());
        static::assertContains(Role::PLANNING_AGENCY_ADMIN, $user->getDplanRolesString());
        static::assertContains(Role::GPSORG, $user->getDplanRolesGroupString());
        static::assertContains(Role::GLAUTH, $user->getDplanRolesGroupString());
    }

    public function testGetUserMissingFlag()
    {
        self::markSkippedForCIIntervention();

        $user = $this->sut->getSingleUser($this->fixtures->getReference('testUserMissingFlag'));
        static::assertContains(Role::PUBLIC_AGENCY_COORDINATION, $user->getDplanRolesString());
        static::assertContains(Role::PLANNING_AGENCY_ADMIN, $user->getDplanRolesString());
        static::assertContains(Role::GPSORG, $user->getDplanRolesGroupString());
        static::assertContains(Role::GLAUTH, $user->getDplanRolesGroupString());
        // Flag noPiwik is missing and should be false
        static::assertFalse($user->getNoPiwik());
    }

    public function testUpdateRoles()
    {
        $user = $this->sut->getSingleUser($this->testUser->getId());
        static::assertCount(2, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => []]);
        static::assertCount(0, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => [Role::PUBLIC_AGENCY_COORDINATION]]);
        static::assertCount(1, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => []]);
        static::assertCount(0, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => [Role::PUBLIC_AGENCY_COORDINATION, Role::PLANNING_AGENCY_ADMIN]]);
        static::assertCount(2, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => [Role::PLATFORM_SUPPORT]]);
        static::assertCount(1, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => []]);
        static::assertCount(0, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => [$this->fixtures->getReference('testRolePublicAgencyCoordination')]]);
        static::assertCount(1, $user->getDplanroles());
        $user = $this->sut->updateUser($this->testUser->getId(), ['roles' => [Role::PUBLIC_AGENCY_COORDINATION], 'customer' => $this->fixtures->getReference('testCustomer')]);
        static::assertCount(1, $user->getDplanroles());
    }

    public function testGetUserStatistics()
    {
        self::markSkippedForCIIntervention();

        /*
        array (
            'roles' =>
                array (
                    'Fachliche Leitstelle' => 1,
                    'Bürger' => 6,
                    'Fachplaner-Admin' => 9,
                    'Fachplaner-Planungsbüro' => 4,
                    'Fachplaner-Admin / TöB-Koordinator' => 14,
                    'TöB-Sachbearbeiter' => 15,
                    'Gast' => 1,
                    'Fachplaner-Sachbearbeiter / TöB-Koordinator' => 2,
                    'Fachplaner-Admin / Fachplaner-Sachbearbeiter' => 1,
                    'TöB-Koordinator' => 17,
                    'Fachplaner-Sachbearbeiter' => 10,
                    'Redakteur' => 2,
                    'Verfahrenssupport' => 3,
                    'Moderator' => 1,
                    'TöB-Koordinator / TöB-Sachbearbeiter' => 2,
                    'Interessent' => 4,
                    'Fachplaner-Admin / TöB-Sachbearbeiter' => 3,
                    'Fachplaner-Masteruser' => 2,
                ),
            'orga' =>
                array (
                    'Planungsbüro' => 2,
                    'TÖB' => 10,
                    'Verfahrensträger' => 10,
                ),
        )
            */
        $users = $this->sut->getUndeletedUsers();

        $orga = $this->sut->collectOrgaStatistics($users);
        static::assertArrayHasKey('Planungsbüro', $orga);
        static::assertArrayHasKey('TöB', $orga);
        static::assertIsNumeric($orga['TöB']);
        static::assertArrayHasKey('Verfahrensträger', $orga);

        $roles = $this->sut->collectRoleStatistics($users);
        static::assertArrayHasKey('Fachplanung-Admin / TöB-Koordination', $roles);
        static::assertIsNumeric($roles['Fachplanung-Admin / TöB-Koordination']);
        static::assertArrayHasKey('Fachplanung-Planungsbüro', $roles);
    }

    // ################## Orga ######################

    public function testUpdateOrga()
    {
        /** @var \demosplan\DemosPlanCoreBundle\Entity\User\Customer $customer */
        $customer = $this->fixtures->getReference('testCustomer');
        $data = [
            'name'               => 'newName',
            'paperCopy'          => '2',
            'showlist'           => '1',
            'showname'           => false,
            'type'               => OrgaType::MUNICIPALITY,
            'customer'           => $customer,
            'address_street'     => 'newStreet',
            'address_postalcode' => 'newPostalcode',
            'address_city'       => 'newCity',
            'address_phone'      => 'newPhone',
            'ccEmail2'           => 'newCCEmail@mine.de',
            'emailReviewerAdmin' => 'newEmailReviewerAdmin@mine.de',
        ];
        $orga = $this->sut->updateOrga($this->testOrgaFp->getId(), $data);
        static::assertEquals($this->testOrgaFp->getId(), $orga->getId());
        static::assertEquals($data['name'], $orga->getName());
        static::assertEquals($data['paperCopy'], $orga->getPaperCopy());
        static::assertEquals(true, $orga->getShowlist());
        static::assertEquals(false, $orga->getShowname());
        static::assertContains($data['type'], $orga->getTypes($customer->getSubdomain()));
        static::assertEquals($data['address_street'], $orga->getStreet());
        static::assertEquals($data['address_postalcode'], $orga->getPostalcode());
        static::assertEquals($data['address_city'], $orga->getCity());
        static::assertEquals($data['address_phone'], $orga->getPhone());
        static::assertEquals($data['ccEmail2'], $orga->getCcEmail2());
        static::assertEquals($data['emailReviewerAdmin'], $orga->getEmailReviewerAdmin());
    }

    public function testUpdateOrgaNotifications()
    {
        self::markSkippedForCIIntervention();

        $this->enablePermissions([
            'feature_notification_statement_new',
            'feature_notification_ending_phase',
        ]);

        $data = [
            'emailNotificationNewStatement' => true,
            'emailNotificationEndingPhase'  => true,
        ];
        $orga = $this->sut->updateOrga($this->testOrgaFp->getId(), $data);
        $notifications = $orga->getNotifications();

        // content is saved as string "true"
        static::assertEquals('true', $notifications['emailNotificationNewStatement']['content']);
        static::assertEquals('true', $notifications['emailNotificationEndingPhase']['content']);

        $data = [
            'emailNotificationNewStatement' => false,
            'emailNotificationEndingPhase'  => false,
        ];
        $orga = $this->sut->updateOrga($this->testOrgaFp->getId(), $data);
        $notifications = $orga->getNotifications();

        // content is saved as string "false"
        static::assertEquals('false', $notifications['emailNotificationNewStatement']['content']);
        static::assertEquals('false', $notifications['emailNotificationEndingPhase']['content']);
    }

    public function testUpdateOrgaSubmissionType()
    {
        self::markSkippedForCIIntervention();

        $this->sut->setPermissions(
            [
                'feature_change_submission_type'     => ['enabled' => true],
                'feature_notification_statement_new' => ['enabled' => false],
                'feature_notification_ending_phase'  => ['enabled' => false],
            ]
        );

        // check that no Orgasetting submissionType in Settings exist
        $data = [
            'submission_type'    => Orga::STATEMENT_SUBMISSION_TYPE_DEFAULT,

            // some random values to be able to update
            'name'               => 'newName',
            'paperCopy'          => '2',
            'showlist'           => '1',
            'type'               => OrgaType::PLANNING_AGENCY,
            'address_street'     => 'newStreet',
            'address_postalcode' => 'newPostalcode',
            'address_city'       => 'newCity',
            'address_phone'      => 'newPhone',
            'ccEmail2'           => 'newCCEmail@mine.de',
            'emailReviewerAdmin' => 'newEmailReviewerAdmin@mine.de',
        ];
        $orga = $this->sut->updateOrga($this->testOrgaFp->getId(), $data);
        $contentService = self::getContainer()->get(ContentService::class);
        $existingSetting = $contentService->getSettings(
            'submissionType',
            SettingsFilter::whereOrga($this->testOrgaFp)->lock(),
            false
        );
        static::assertCount(0, $existingSetting);
        static::assertEquals(Orga::STATEMENT_SUBMISSION_TYPE_DEFAULT, $orga->getSubmissionType());

        // create a submissionType
        $data = [
            'submission_type'    => Orga::STATEMENT_SUBMISSION_TYPE_SHORT,

            // some random values to be able to update
            'name'               => 'newName',
            'paperCopy'          => '2',
            'showlist'           => '1',
            'type'               => OrgaType::PLANNING_AGENCY,
            'address_street'     => 'newStreet',
            'address_postalcode' => 'newPostalcode',
            'address_city'       => 'newCity',
            'address_phone'      => 'newPhone',
            'address_state'      => 'newState',
            'ccEmail2'           => 'newCCEmail@mine.de',
            'emailReviewerAdmin' => 'newEmailReviewerAdmin@mine.de',
        ];
        $orga = $this->sut->updateOrga($this->testOrgaFp->getId(), $data);
        $existingSetting = $contentService->getSettings(
            'submissionType',
            SettingsFilter::whereOrga($this->testOrgaFp)->lock(),
            false
        );
        static::assertCount(1, $existingSetting);
        static::assertEquals('submissionType', $existingSetting[0]->getKey());
        static::assertEquals(Orga::STATEMENT_SUBMISSION_TYPE_SHORT, $existingSetting[0]->getContent());
        static::assertEquals(Orga::STATEMENT_SUBMISSION_TYPE_SHORT, $orga->getSubmissionType());

        // Delete Setting again
        $data = [
            'submission_type'    => Orga::STATEMENT_SUBMISSION_TYPE_DEFAULT,

            // some random values to be able to update
            'name'               => 'newName',
            'paperCopy'          => '2',
            'showlist'           => '1',
            'type'               => OrgaType::PLANNING_AGENCY,
            'address_street'     => 'newStreet',
            'address_postalcode' => 'newPostalcode',
            'address_city'       => 'newCity',
            'address_phone'      => 'newPhone',
            'ccEmail2'           => 'newCCEmail@mine.de',
            'emailReviewerAdmin' => 'newEmailReviewerAdmin@mine.de',
        ];
        $orga = $this->sut->updateOrga($this->testOrgaFp->getId(), $data);
        $existingSetting = $contentService->getSettings(
            'submissionType',
            SettingsFilter::whereOrga($this->testOrgaFp)->lock(),
            false
        );
        static::assertCount(0, $existingSetting);
        static::assertEquals(Orga::STATEMENT_SUBMISSION_TYPE_DEFAULT, $orga->getSubmissionType());
    }

    public function testGetOrgaUsers()
    {
        self::markSkippedForCIIntervention();

        $orga = $this->sut->getOrga($this->testOrgaFp->getId());
        static::assertEquals($this->testOrgaFp->getId(), $orga->getId());
        static::assertEquals($this->testOrgaFp->getUsers()->first(), $orga->getUsers()->first());
    }

    public function testIsOrgaType()
    {
        self::markSkippedForCIIntervention();

        $invitableInstitutionsOnlyOrgaId = $this->fixtures->getReference('testOrgaInvitableInstitutionOnly')->getId();
        static::assertTrue($this->sut->isOrgaType($invitableInstitutionsOnlyOrgaId, OrgaType::PUBLIC_AGENCY));
        static::assertFalse($this->sut->isOrgaType($invitableInstitutionsOnlyOrgaId, OrgaType::MUNICIPALITY));
        static::assertFalse($this->sut->isOrgaType($invitableInstitutionsOnlyOrgaId, OrgaType::PLANNING_AGENCY));

        $invitableInstitutionOrgaId = $this->fixtures->getReference('testOrgaInvitableInstitution')->getId();
        static::assertTrue($this->sut->isOrgaType($invitableInstitutionOrgaId, OrgaType::PUBLIC_AGENCY));
        static::assertTrue($this->sut->isOrgaType($invitableInstitutionOrgaId, OrgaType::MUNICIPALITY));
        static::assertFalse($this->sut->isOrgaType($invitableInstitutionOrgaId, OrgaType::PLANNING_AGENCY));

        $fpOrgaId = $this->fixtures->getReference('testOrgaFPOnly')->getId();
        static::assertFalse($this->sut->isOrgaType($fpOrgaId, OrgaType::PUBLIC_AGENCY));
        static::assertTrue($this->sut->isOrgaType($fpOrgaId, OrgaType::MUNICIPALITY));
        static::assertFalse($this->sut->isOrgaType($fpOrgaId, OrgaType::PLANNING_AGENCY));

        $pbOrgaId = $this->fixtures->getReference('testOrgaPB')->getId();
        static::assertFalse($this->sut->isOrgaType($pbOrgaId, OrgaType::PUBLIC_AGENCY));
        static::assertFalse($this->sut->isOrgaType($pbOrgaId, OrgaType::MUNICIPALITY));
        static::assertTrue($this->sut->isOrgaType($pbOrgaId, OrgaType::PLANNING_AGENCY));
    }

    public function testDeleteOrga()
    {
        self::markSkippedForCIIntervention();

        $orga = $this->sut->getOrga($this->testOrgaFp->getId());
        static::assertEquals($this->testOrgaFp->getId(), $orga->getId());

        $deleted = $this->sut->deleteOrga($orga->getId());
        static::assertTrue($deleted);

        $deletedOrga = $this->sut->getDepartment($this->testOrgaFp->getId());
        static::assertNull($deletedOrga);
    }

    // ################## Department ######################

    public function testGetDepartment()
    {
        $department = $this->sut->getDepartment($this->testDepartment->getId());
        static::assertEquals($this->testDepartment->getId(), $department->getId());
    }

    public function testGetDepartmentAddress()
    {
        $department = $this->sut->getDepartment($this->testDepartment->getId());
        static::assertEquals($this->testDepartment->getId(), $department->getId());
        static::assertEquals($this->testDepartment->getAddresses()->first(), $department->getAddresses()->first());
        static::assertCount(1, $department->getAddresses());
    }

    public function testUpdateDepartment()
    {
        $data = [
            'name' => 'newDepartment',
            'code' => '25d88f4',
            'gwId' => '28965132',
        ];
        $department = $this->sut->updateDepartment($this->testDepartment->getId(), $data);
        static::assertEquals($this->testDepartment->getId(), $department->getId());
        static::assertEquals($data['name'], $department->getName());
        static::assertEquals($data['code'], $department->getCode());
        static::assertEquals($data['gwId'], $department->getGwId());

        static::assertFalse($department->getDeleted());

        static::assertEquals($this->testAddress->getId(), $department->getAddress()->getId());
    }

    public function testUpdateDepartmentAddressDoubled()
    {
        $this->expectException(Exception::class);

        $data = [
            'address' => $this->testAddress,
        ];
        $this->sut->updateDepartment($this->testDepartment->getId(), $data);
    }

    public function testAddDepartment()
    {
        $data = [
            'name'    => 'newDepartment',
            'code'    => '25d88f4',
            'gwId'    => '28965132',
            'address' => $this->testAddress,
        ];
        $department = $this->sut->addDepartment($data, $this->testOrgaFp->getId());
        static::assertEquals($data['name'], $department->getName());
        static::assertEquals($data['code'], $department->getCode());
        static::assertEquals($data['gwId'], $department->getGwId());

        static::assertFalse($department->getDeleted());

        static::assertEquals($this->testAddress->getId(), $department->getAddress()->getId());
    }

    public function testGetDepartmentUsers()
    {
        $department = $this->sut->getDepartment($this->testDepartment->getId());
        static::assertEquals($this->testDepartment->getId(), $department->getId());
        $users = $department->getUsers();
        static::assertEquals($this->testDepartment->getUsers()->first(), $department->getUsers()->first());
        static::assertCount(1, $department->getUsers());
    }

    public function testAddDepartmentUser()
    {
        $department = $this->sut->getDepartment($this->testDepartment->getId());
        static::assertEquals($this->testDepartment->getId(), $department->getId());
        static::assertCount(1, $department->getUsers());

        $newUser = new User();
        $newUser->setEmail('nextfunctionaltest@test.de');
        $newUser->setLogin('nextfunctionaltestuser@demos-deutschland.de');
        $newUser->setLastname('nextTester');
        $newUser->setFirstname('nextKlara');
        $newUser->setGender('female');

        $newUser->setNewsletter(true);
        $newUser->setNoPiwik(true);
        $newUser->setProfileCompleted(true);
        $newUser->setNewUser(false);
        $newUser->setAccessConfirmed(true);
        $newUser->setForumNotification(true);
        $newUser->setDplanroles([$this->fixtures->getReference('testRoleFP')]);

        $departmentUserAdded = $this->sut->departmentAddUser($this->testDepartment->getId(), $newUser);
        static::assertEquals($this->testDepartment->getId(), $departmentUserAdded->getId());
        static::assertCount(2, $departmentUserAdded->getUsers());

        $anotherDepartment = $this->fixtures->getReference('testDepartmentPlanningOffice');
        $secondDepartmentUserAdded = $this->sut->departmentAddUser($anotherDepartment->getId(), $newUser);
        static::assertEquals($anotherDepartment->getId(), $secondDepartmentUserAdded->getId());
        static::assertCount(3, $secondDepartmentUserAdded->getUsers());
    }

    public function testAddOrgaDepartment()
    {
        self::markSkippedForCIIntervention();

        $orga = $this->sut->getOrga($this->testOrgaFp->getId());
        static::assertEquals($this->testOrgaFp->getId(), $orga->getId());
        static::assertCount(1, $orga->getDepartments());

        /** @var Department $departmentPlanningOffice */
        $departmentPlanningOffice = $this->fixtures->getReference('testDepartmentPlanningOffice');
        $orgaDepartmentAdded = $this->sut->orgaAddDepartment($this->testOrgaFp->getId(), $departmentPlanningOffice);
        static::assertEquals($this->testOrgaFp->getId(), $orgaDepartmentAdded->getId());
        static::assertCount(2, $orgaDepartmentAdded->getDepartments());
        $departments = $orgaDepartmentAdded->getDepartments();
        static::assertEquals($departmentPlanningOffice->getName(), $departments[1]->getName());
    }

    public function testDeleteDepartment()
    {
        $department = $this->sut->getDepartment($this->testDepartment->getId());
        static::assertEquals($this->testDepartment->getId(), $department->getId());

        $deleted = $this->sut->deleteDepartment($department->getId());
        static::assertTrue($deleted);

        $deletedDepartment = $this->sut->getDepartment($this->testDepartment->getId());
        static::assertNull($deletedDepartment);
    }

    // ################## Address ######################

    public function testGetAddress()
    {
        self::markSkippedForCIIntervention();

        $address = $this->sut->getAddress($this->testAddress->getId());
        static::assertEquals($this->testAddress->getId(), $address->getId());
    }

    public function testGetNoAddress()
    {
        self::markSkippedForCIIntervention();

        $address = $this->sut->getAddress('wrongId');
        static::assertEquals(null, $address);
        $address = $this->sut->getAddress(null);
        static::assertEquals(null, $address);
        $address = $this->sut->getAddress([]);
        static::assertEquals(null, $address);
        $address = $this->sut->getAddress(false);
        static::assertEquals(null, $address);
    }

    public function testUpdateAddress()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'city'          => 'newCity',
            'code'          => 'newCode',
            'email'         => 'newemail@mine.de',
            'fax'           => '22354',
            'phone'         => '2847892',
            'postalcode'    => '28456',
            'postofficebox' => '289561',
            'region'        => 'Niedersachsen',
            'state'         => 'Island',
            'street'        => 'Bärlauchstraße 8',
            'street1'       => 'Ecke Holstenklause',
            'url'           => 'http://klause-de.de',
        ];
        $address = $this->sut->updateAddress($this->testAddress->getId(), $data);
        static::assertEquals($this->testAddress->getId(), $address->getId());
        static::assertEquals($data['city'], $address->getCity());
        static::assertEquals($data['code'], $address->getCode());
        // nicht mit übergeben, also false
        static::assertFalse($address->getDeleted());
        static::assertEquals($data['email'], $address->getEmail());
        static::assertEquals($data['fax'], $address->getFax());
        static::assertEquals($data['phone'], $address->getPhone());
        static::assertEquals($data['postalcode'], $address->getPostalcode());
        static::assertEquals($data['postofficebox'], $address->getPostofficebox());
        static::assertEquals($data['region'], $address->getRegion());
        static::assertEquals($data['state'], $address->getState());
        static::assertEquals($data['street'], $address->getStreet());
        static::assertEquals($data['street1'], $address->getStreet1());
        static::assertEquals($data['url'], $address->getUrl());
    }

    public function testAddAddress()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'city'          => 'newCity',
            'code'          => 'newCode',
            'email'         => 'newemail@mine.de',
            'fax'           => '22354',
            'phone'         => '2847892',
            'postalcode'    => '28456',
            'postofficebox' => '289561',
            'region'        => 'Niedersachsen',
            'state'         => 'Island',
            'street'        => 'Bärlauchstraße 8',
            'street1'       => 'Ecke Holstenklause',
            'url'           => 'http://klause-de.de',
        ];
        $address = $this->sut->addAddress($data);
        static::assertEquals($data['city'], $address->getCity());
        static::assertEquals($data['code'], $address->getCode());
        // nicht mit übergeben, also false
        static::assertFalse($address->getDeleted());
        static::assertEquals($data['email'], $address->getEmail());
        static::assertEquals($data['fax'], $address->getFax());
        static::assertEquals($data['phone'], $address->getPhone());
        static::assertEquals($data['postalcode'], $address->getPostalcode());
        static::assertEquals($data['postofficebox'], $address->getPostofficebox());
        static::assertEquals($data['region'], $address->getRegion());
        static::assertEquals($data['state'], $address->getState());
        static::assertEquals($data['street'], $address->getStreet());
        static::assertEquals($data['street1'], $address->getStreet1());
        static::assertEquals($data['url'], $address->getUrl());
    }

    /**
     * dataProvider changePasswordData.
     */
    public function testChangePassword(/* $userId, $oldPassword, $newPassword, $success */)
    {
        self::markSkippedForCIIntervention();

        try {
            $this->sut->changePassword($userId, $oldPassword, $newPassword);
        } catch (Exception $e) {
            if ($success) {
                static::assertTrue(false);
            }
        }
    }

    public function changePasswordData()
    {
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $newPass = 'geheimnis';

        return [
            [$user->getIdent(), 'myPass', $newPass, true],
            [$user->getIdent(), 'inkorrekt', 'geheimnis', false],
        ];
    }

    public function testPseudoDeleteAddress()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'deleted' => 'on',
        ];
        $address = $this->sut->updateAddress($this->testAddress->getId(), $data);
        static::assertTrue($address->getDeleted());
    }

    public function testGetUsersOfRole()
    {
        $role = Role::PLANNING_AGENCY_ADMIN;
        $result = $this->sut->getUsersOfRole($role);
        static::assertIsArray($result);
        static::assertCount(8, $result);
    }

    public function testPseudoDeleteAddressBool()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'deleted' => true,
        ];
        $address = $this->sut->updateAddress($this->testAddress->getId(), $data);
        static::assertTrue($address->getDeleted());
    }

    public function testDeleteAddress()
    {
        self::markSkippedForCIIntervention();

        $deleted = $this->sut->deleteAddress($this->testAddress->getId());
        static::assertTrue($deleted);
        $address = $this->sut->getAddress($this->testAddress->getId());
        static::assertEquals(null, $address);
    }

    /**
     * Participating orgas sould only be listed if they are allowed to be displayed (showlist == 1)
     * and orga allowes to be displayed in list (showname == 1).
     */
    public function testGetParticipatingOrganisations()
    {
        /** @var OrgaService $orgaService */
        $orgaService = self::getContainer()->get(OrgaService::class);
        $publicAgencyTestId = $this->getOrgaReference('testOrgaInvitableInstitution')->getId();

        $countOrgas = $this->countEntries(Orga::class, ['showname' => true, 'showlist' => true, 'deleted' => false]);
        $listOfOrgas = $orgaService->getParticipants();
        static::assertCount($countOrgas, $listOfOrgas);

        $data = [
            'updateShowlist' => true,
            'showlist'       => false,
            'showname'       => true,
        ];
        $orga = $this->sut->updateOrga($publicAgencyTestId, $data);

        $listOfOrgas2 = $orgaService->getParticipants();
        static::assertCount($countOrgas - 1, $listOfOrgas2);

        // skip variable showname for setting it false
        $data = [
            'updateShowlist' => true,
            'showlist'       => true,
            'showname'       => false,
        ];
        $orga = $this->sut->updateOrga($publicAgencyTestId, $data);

        $listOfOrgas3 = $orgaService->getParticipants();
        static::assertCount($countOrgas - 1, $listOfOrgas3);

        $data = [
            'updateShowlist' => true,
            'showlist'       => '1',
            'showname'       => '1',
        ];
        $orga = $this->sut->updateOrga($publicAgencyTestId, $data);

        $listOfOrgas4 = $orgaService->getParticipants();
        static::assertCount($countOrgas, $listOfOrgas4);
    }

    public function testWipeUser()
    {
        $userToWipe = $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $departmentId = $userToWipe->getDepartment()->getId();
        $orgaId = $userToWipe->getOrga()->getId();

        $user = $this->sut->wipeUser($userToWipe);

        static::assertNotFalse($user);

        $wipedUser = $this->sut->getSingleUser($user->getId());

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

        //        delete addresses of user will be executed in handler method
        static::assertNotNull($wipedUser->getAddress());

        static::assertNull($wipedUser->getLanguage());
        static::assertNull($wipedUser->getPassword());
        static::assertNull($wipedUser->getGwId());

        static::assertTrue(is_numeric($wipedUser->getEmail()));
        static::assertNull($wipedUser->getSalt());
        static::assertEmpty($wipedUser->getPassword());

        static::assertTrue(is_array($wipedUser->getRoles()));
        static::assertCount(0, $wipedUser->getRoles());
        static::assertFalse($wipedUser->getNewsletter());
        static::assertFalse($wipedUser->getNoPiwik());
        static::assertFalse($wipedUser->getNewsletter());
        static::assertFalse($wipedUser->isIntranet());

        static::assertEquals('', $wipedUser->getUserIdentifier());
        static::assertEquals($departmentId, $wipedUser->getDepartment()->getId());
        static::assertEquals($orgaId, $wipedUser->getOrga()->getId());
        static::assertNull($wipedUser->getFirstname());
        static::assertNull($wipedUser->getLastname());

        static::assertNotEmpty($wipedUser->getAddresses());
    }

    public function testGetAddressBookEntriesFromOrganisation()
    {
        /** @var Orga $organisation */
        $organisation = $this->testUser = $this->fixtures->getReference('testOrgaFP');
        $entries = $this->getEntries(
            AddressBookEntry::class,
            ['organisation' => $organisation->getId()]
        );
        static::assertNotEmpty($entries);
        static::assertNotEquals($entries, $organisation->getAddressBookEntries());
    }

    public function testGetOrganisationFromAddressBookEntry()
    {
        /** @var Orga $organisation */
        $organisation = $this->testUser = $this->fixtures->getReference('testOrgaFP');
        /** @var AddressBookEntry $addressBookEntry */
        $addressBookEntry = $this->testUser = $this->fixtures->getReference('testAddressBookEntry1');
        static::assertEquals($organisation->getId(), $addressBookEntry->getOrganisation()->getId());
    }
}
