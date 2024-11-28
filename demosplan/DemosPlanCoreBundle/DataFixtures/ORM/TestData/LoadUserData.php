<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\User\Address;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\MasterToeb;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadUserData extends TestFixture
{
    /**
     * Test user with guest role.
     */
    final public const TEST_USER_GUEST = 'testUserGuest';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN = 'testUser3';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN2 = 'citizen2';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN3 = 'citizen3';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN4 = 'citizen4';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN5 = 'citizen5';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN6 = 'citizen6';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN7 = 'citizen7';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN8 = 'citizen8';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN9 = 'citizen9';

    /**
     * Test user with citizen role.
     */
    final public const TEST_USER_CITIZEN10 = 'citizen10';

    /**
     * Test user with public agency role.
     */
    final public const TEST_USER_INVITABLE_INSTITUTION_ONLY = 'testUserInvitableInstitutionOnly';

    /**
     * Test user with planner role.
     */
    final public const TEST_USER_FP_ONLY = 'testUserFpOnly';

    final public const TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY = 'testUser';

    /**
     * Test user with unfinished registration.
     */
    final public const TEST_USER_UNFINISHED_REGISTRATION = 'testUserUnfinishedRegistration';

    /**
     * Test user with FP role used in XBauleitplanungServiceTest.
     */
    final public const TEST_USER_XBAULEITPLANUNG = 'testUserXBauleitplanung';

    final public const TEST_USER_CONTENT_EDITOR = 'testUserContentEditor';

    final public const TEST_USER_CUSTOMER_MASTER = 'testUserCustomerMaster';

    final public const TEST_DEPARTMENT = 'testDepartment';
    final public const TEST_ORGA_PUBLIC_AGENCY = 'testOrgaInvitableInstitution';

    final public const TEST_ORGA_FP = 'testOrgaFP';
    final public const TEST_ORGA_WITH_REJECTED_ORGA_TYPE = 'orgaWithRejectedOrgaType';

    final public const DATA_INPUT_ORGA = 'dataInputOrga';
    final public const TEST_ORGA_PB = 'testOrgaPB';

    final public const TEST_USER_2_PLANNER_ADMIN = 'testUser2';

    final public const DEFAULT_PW_HASH = '2308912f941bd13be84578fa73877453f0e3eef455e6674aee57c78cd354fda94087762e9ea4d5e7a56a330f824edf7d125950ce7e5fda7080b34fe1836a0b4e';

    public function __construct(EntityManagerInterface $entityManager, private readonly OrgaService $orgaService)
    {
        parent::__construct($entityManager);
    }

    public function load(ObjectManager $manager): void
    {
        $address = new Address();
        $address->setStreet('Departmentstraße');
        $address->setPostalcode('54321');
        $address->setCity('Melilotus');
        $address->setState('Deutschland');
        $manager->persist($address);

        $this->setReference('testAddress', $address);

        $address2 = new Address();
        $address2->setStreet('PO Departmentstraße');
        $address2->setPostalcode('32321');
        $address2->setCity('Lions');
        $address2->setState('Deutschland');
        $manager->persist($address2);

        $this->setReference('testAddressPlanningOffice', $address2);

        $manager->flush();

        $role1 = new Role();
        $role1->setName('Institutions-Koordinator')
            ->setCode(Role::PUBLIC_AGENCY_COORDINATION)
            ->setGroupCode(Role::GPSORG)
            ->setGroupName('Institution');

        $manager->persist($role1);

        $role2 = new Role();
        $role2->setName('Bürger')
            ->setCode(Role::CITIZEN)
            ->setGroupCode(Role::GCITIZ)
            ->setGroupName('Bürgergruppe');

        $manager->persist($role2);

        $role3 = new Role();
        $role3->setName('Fachplaner-Admin')
            ->setCode(Role::PLANNING_AGENCY_ADMIN)
            ->setGroupCode(Role::GLAUTH)
            ->setGroupName('Kommune');

        $manager->persist($role3);

        $role4 = new Role();
        $role4->setName('Institutions-Sachbearbeiter')
            ->setCode(Role::PUBLIC_AGENCY_WORKER)
            ->setGroupCode(Role::GPSORG)
            ->setGroupName('Institution');

        $manager->persist($role4);

        $role5 = new Role();
        $role5->setName('Gast')
            ->setCode(Role::GUEST)
            ->setGroupCode(Role::GGUEST)
            ->setGroupName('Gäste');

        $manager->persist($role5);

        $role6 = new Role();
        $role6->setName('Fachplaner-Planungsbüro')
            ->setCode(Role::PRIVATE_PLANNING_AGENCY)
            ->setGroupCode(Role::GLAUTH)
            ->setGroupName('Kommune');

        $manager->persist($role6);

        $role7 = new Role();
        $role7->setName('Fachplaner-Sachbearbeiter')
            ->setCode(Role::PLANNING_AGENCY_WORKER)
            ->setGroupCode(Role::GLAUTH)
            ->setGroupName('Kommune');

        $manager->persist($role7);

        $role8 = new Role();
        $role8->setName('Verfahrenssupport')
            ->setCode(Role::PLATFORM_SUPPORT)
            ->setGroupCode(Role::GTSUPP)
            ->setGroupName('Verfahrenssupport');

        $manager->persist($role8);

        $role9 = new Role();
        $role9->setName('TöB-Verwalter')
            ->setCode(Role::PUBLIC_AGENCY_SUPPORT)
            ->setGroupCode(Role::GPSUPP)
            ->setGroupName('TöB-Verwalter');

        $manager->persist($role9);

        $role10 = new Role();
        $role10->setName('Redakteur')
            ->setCode(Role::CONTENT_EDITOR)
            ->setGroupCode(Role::GTEDIT)
            ->setGroupName('Redakteur');

        $manager->persist($role10);

        $role11 = new Role();
        $role11->setName('Fachplaner-Fachbehörde')
            ->setCode(Role::PLANNING_SUPPORTING_DEPARTMENT)
            ->setGroupCode(Role::GLAUTH)
            ->setGroupName('Kommune');

        $manager->persist($role11);

        $role12 = new Role();
        $role12->setName('Datenerfassung')
            ->setCode(Role::PROCEDURE_DATA_INPUT)
            ->setGroupCode(Role::GDATA)
            ->setGroupName('Datenerfassung');

        $manager->persist($role12);

        $role13 = new Role();
        $role13->setName('Organisationsadministration')
            ->setCode(Role::ORGANISATION_ADMINISTRATION)
            ->setGroupCode(Role::GLAUTH)
            ->setGroupName('Kommune');

        $manager->persist($role13);

        $role14 = new Role();
        $role14->setName(Role::CUSTOMER_MASTER_USER)
            ->setCode(Role::CUSTOMER_MASTER_USER)
            ->setGroupCode(Role::CUSTOMERMASTERUSERGROUP)
            ->setGroupName(Role::CUSTOMERMASTERUSERGROUP);

        $manager->persist($role14);

        $this->setReference('testRolePublicAgencyCoordination', $role1);
        $this->setReference('testRoleCitiz', $role2);
        $this->setReference('testRoleFP', $role3);
        $this->setReference('testRoleGuest', $role5);
        $this->setReference('testRolePlanningOffice', $role6);
        $this->setReference('testRoleDataInput', $role12);
        $this->setReference('testRoleOrganisationAdministration', $role13);

        $manager->flush();

        // Customers

        $customer = new Customer('Hindenburg', 'hindsight');
        $customer->setAccessibilityExplanation('Barrierefreiheitserklärung');
        $manager->persist($customer);

        $this->setReference(LoadCustomerData::HINDSIGHT, $customer);

        $customerBrandenburg = new Customer('Brandenburg', 'brandenburg');
        $customerBrandenburg->setAccessibilityExplanation('Barrierefreiheitserklärung');
        $manager->persist($customerBrandenburg);

        $this->setReference(LoadCustomerData::BB, $customerBrandenburg);

        $manager->flush();

        // Users

        $user = new User();
        $user->setEmail('functionaltest@test.de');
        $user->setLogin('functionaltestuser@demos-deutschland.de');
        $user->setPassword(md5('Advanced_12345'));
        $user->setAlternativeLoginPassword(md5('Advanced_12345'));
        $user->setLastname('Tester');
        $user->setFirstname('Klara');
        $user->setGender('female');
        $user->setNewsletter(true);
        $user->setNoPiwik(true);
        $user->setProfileCompleted(true);
        $user->setNewUser(false);
        $user->setInvited(false);
        $user->setAccessConfirmed(true);
        $user->setForumNotification(true);
        $user->setDplanroles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')], $customer);
        $user->addAddress($this->getReference('testAddress'));
        $user->setCurrentCustomer($customer);

        $manager->persist($user);

        $user2 = new User();
        $user2->setEmail('user2@test.de');
        $user2->setLogin('user2@demos-deutschland.de');
        $user2->setPassword(md5('user2'));
        $user2->setAlternativeLoginPassword(md5('user2'));
        $user2->setLastname('Tester user2');
        $user2->setFirstname('Planungsbüro Meinhard');
        $user2->setGender('male');
        $user2->setNewsletter(false);
        $user2->setNoPiwik(false);
        $user2->setProfileCompleted(true);
        $user2->setNewUser(false);
        $user2->setAccessConfirmed(true);
        $user2->setForumNotification(false);
        $user2->setDplanroles([$this->getReference('testRolePlanningOffice')], $customer);
        $user2->setCurrentCustomer($customer);

        $manager->persist($user2);

        $userMissingFlag = new User();
        $userMissingFlag->setEmail('DDfunctionaltest@test.de');
        $userMissingFlag->setLogin('DDfunctionaltestuser@demos-deutschland.de');
        $userMissingFlag->setPassword(md5('userMissingFlag'));
        $userMissingFlag->setAlternativeLoginPassword(md5('userMissingFlag'));
        $userMissingFlag->setLastname('Missing Flags');
        $userMissingFlag->setFirstname('Klara');
        $userMissingFlag->setGender('female');
        $userMissingFlag->setProfileCompleted(true);
        $userMissingFlag->setNewUser(false);
        $userMissingFlag->setAccessConfirmed(true);
        $userMissingFlag->setForumNotification(true);
        $userMissingFlag->setNewsletter(true);
        $userMissingFlag->setDplanroles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')], $customer);
        $userMissingFlag->setCurrentCustomer($customer);

        $manager->persist($userMissingFlag);

        $user3 = new User();
        $user3->setEmail('user3@test.de');
        $user3->setLogin('user3@demos-deutschland.de');
        $user3->setPassword(md5('$user3'));
        $user3->setAlternativeLoginPassword(md5('$user3'));
        $user3->setLastname('Tester user3 MasterToeb');
        $user3->setFirstname('Meinhard');
        $user3->setGender('male');
        $user3->setNewsletter(false);
        $user3->setNoPiwik(false);
        $user3->setProfileCompleted(true);
        $user3->setNewUser(false);
        $user3->setAccessConfirmed(true);
        $user3->setForumNotification(false);
        $user3->setDplanroles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')], $customer);
        $user3->addAddress($this->getReference('testAddress'));
        $user3->setCurrentCustomer($customer);

        $manager->persist($user3);

        $user4 = new User();
        $user4->setEmail('user4@test.de');
        $user4->setLogin('user4@demos-deutschland.de');
        $user4->setPassword(md5('$user4'));
        $user4->setAlternativeLoginPassword(md5('$user4'));
        $user4->setLastname('Tester user4');
        $user4->setFirstname('Planungsbüro Sabibe');
        $user4->setGender('female');
        $user4->setNewsletter(false);
        $user4->setNoPiwik(false);
        $user4->setProfileCompleted(true);
        $user4->setNewUser(false);
        $user4->setAccessConfirmed(true);
        $user4->setForumNotification(false);
        $user4->setDplanroles([$this->getReference('testRolePlanningOffice')], $customer);
        $user4->setCurrentCustomer($customer);

        $manager->persist($user4);

        $user5 = new User();
        $user5->setEmail('user5@test.de');
        $user5->setLogin('user5@demos-deutschland.de');
        $user5->setPassword(md5('$user5'));
        $user5->setAlternativeLoginPassword(md5('$user5'));
        $user5->setLastname('Tester user5');
        $user5->setFirstname('PublicAgencyCoordination only Sabibe');
        $user5->setGender('female');
        $user5->setNewsletter(false);
        $user5->setNoPiwik(false);
        $user5->setProfileCompleted(true);
        $user5->setNewUser(false);
        $user5->setAccessConfirmed(true);
        $user5->setForumNotification(false);
        $user5->setDplanroles([$this->getReference('testRolePublicAgencyCoordination')], $customer);
        $user5->setCurrentCustomer($customer);

        $manager->persist($user5);

        $citizenUser = new User();
        $citizenUser->setEmail('user6@test.de');
        $citizenUser->setLogin('user6@demos-deutschland.de');
        $citizenUser->setPassword(md5('$user6'));
        $citizenUser->setAlternativeLoginPassword(md5('$user6'));
        $citizenUser->setLastname('Tester user6 Buerger');
        $citizenUser->setFirstname('Fender');
        $citizenUser->setGender('female');
        $citizenUser->setNewsletter(false);
        $citizenUser->setNoPiwik(false);
        $citizenUser->setProfileCompleted(true);
        $citizenUser->setNewUser(false);
        $citizenUser->setAccessConfirmed(true);
        $citizenUser->setForumNotification(false);
        $citizenUser->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser->addAddress($this->getReference('testAddress'));
        $citizenUser->setCurrentCustomer($customer);

        $manager->persist($citizenUser);

        $citizenUser2 = new User();
        $citizenUser2->setEmail('citizen2@test.de');
        $citizenUser2->setLogin('citizen2@demos-deutschland.de');
        $citizenUser2->setPassword(md5('$citizenUser2'));
        $citizenUser2->setAlternativeLoginPassword(md5('$citizenUser2'));
        $citizenUser2->setLastname('Tester Buerger');
        $citizenUser2->setFirstname('Klaas');
        $citizenUser2->setGender('male');
        $citizenUser2->setNewsletter(false);
        $citizenUser2->setNoPiwik(false);
        $citizenUser2->setProfileCompleted(true);
        $citizenUser2->setNewUser(false);
        $citizenUser2->setAccessConfirmed(true);
        $citizenUser2->setForumNotification(false);
        $citizenUser2->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser2->addAddress($this->getReference('testAddress'));
        $citizenUser2->setCurrentCustomer($customer);
        $manager->persist($citizenUser2);

        $citizenUser3 = new User();
        $citizenUser3->setEmail('citizen3@test.de');
        $citizenUser3->setLogin('citizen3@demos-deutschland.de');
        $citizenUser3->setPassword(md5('$citizenUser3'));
        $citizenUser3->setAlternativeLoginPassword(md5('$citizenUser3'));
        $citizenUser3->setLastname('Tester Buerger');
        $citizenUser3->setFirstname('Martin');
        $citizenUser3->setGender('male');
        $citizenUser3->setNewsletter(false);
        $citizenUser3->setNoPiwik(false);
        $citizenUser3->setProfileCompleted(true);
        $citizenUser3->setNewUser(false);
        $citizenUser3->setAccessConfirmed(true);
        $citizenUser3->setForumNotification(false);
        $citizenUser3->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser3->addAddress($this->getReference('testAddress'));
        $citizenUser3->setCurrentCustomer($customer);
        $manager->persist($citizenUser3);

        $citizenUser4 = new User();
        $citizenUser4->setEmail('citizen4@test.de');
        $citizenUser4->setLogin('citizen4@demos-deutschland.de');
        $citizenUser4->setPassword(md5('$citizenUser4'));
        $citizenUser4->setAlternativeLoginPassword(md5('$citizenUser4'));
        $citizenUser4->setLastname('Tester Buerger');
        $citizenUser4->setFirstname('Helena');
        $citizenUser4->setGender('female');
        $citizenUser4->setNewsletter(false);
        $citizenUser4->setNoPiwik(false);
        $citizenUser4->setProfileCompleted(true);
        $citizenUser4->setNewUser(false);
        $citizenUser4->setAccessConfirmed(true);
        $citizenUser4->setForumNotification(false);
        $citizenUser4->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser4->addAddress($this->getReference('testAddress'));
        $citizenUser4->setCurrentCustomer($customer);
        $manager->persist($citizenUser4);

        $citizenUser5 = new User();
        $citizenUser5->setEmail('citizen5@test.de');
        $citizenUser5->setLogin('citizen5@demos-deutschland.de');
        $citizenUser5->setPassword(md5('$citizenUser5'));
        $citizenUser5->setAlternativeLoginPassword(md5('$citizenUser5'));
        $citizenUser5->setLastname('Tester Buerger');
        $citizenUser5->setFirstname('Julia');
        $citizenUser5->setGender('female');
        $citizenUser5->setNewsletter(false);
        $citizenUser5->setNoPiwik(false);
        $citizenUser5->setProfileCompleted(true);
        $citizenUser5->setNewUser(false);
        $citizenUser5->setAccessConfirmed(true);
        $citizenUser5->setForumNotification(false);
        $citizenUser5->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser5->addAddress($this->getReference('testAddress'));
        $citizenUser5->setCurrentCustomer($customer);
        $manager->persist($citizenUser5);

        $citizenUser6 = new User();
        $citizenUser6->setEmail('citizen6@test.de');
        $citizenUser6->setLogin('citizen6@demos-deutschland.de');
        $citizenUser6->setPassword(md5('$citizenUser6'));
        $citizenUser6->setAlternativeLoginPassword(md5('$citizenUser6'));
        $citizenUser6->setLastname('Tester Buerger');
        $citizenUser6->setFirstname('Manuel');
        $citizenUser6->setGender('male');
        $citizenUser6->setNewsletter(false);
        $citizenUser6->setNoPiwik(false);
        $citizenUser6->setProfileCompleted(true);
        $citizenUser6->setNewUser(false);
        $citizenUser6->setAccessConfirmed(true);
        $citizenUser6->setForumNotification(false);
        $citizenUser6->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser6->addAddress($this->getReference('testAddress'));
        $citizenUser6->setCurrentCustomer($customer);
        $manager->persist($citizenUser6);

        $citizenUser7 = new User();
        $citizenUser7->setEmail('citizen7@test.de');
        $citizenUser7->setLogin('citizen7@demos-deutschland.de');
        $citizenUser7->setPassword(md5('$citizenUser7'));
        $citizenUser7->setAlternativeLoginPassword(md5('$citizenUser7'));
        $citizenUser7->setLastname('Tester Buerger');
        $citizenUser7->setFirstname('Manuel');
        $citizenUser7->setGender('male');
        $citizenUser7->setNewsletter(false);
        $citizenUser7->setNoPiwik(false);
        $citizenUser7->setProfileCompleted(true);
        $citizenUser7->setNewUser(false);
        $citizenUser7->setAccessConfirmed(true);
        $citizenUser7->setForumNotification(false);
        $citizenUser7->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser7->addAddress($this->getReference('testAddress'));
        $citizenUser7->setCurrentCustomer($customer);
        $manager->persist($citizenUser7);

        $citizenUser8 = new User();
        $citizenUser8->setEmail('citizen8@test.de');
        $citizenUser8->setLogin('citizen8@demos-deutschland.de');
        $citizenUser8->setPassword(md5('$citizenUser8'));
        $citizenUser8->setAlternativeLoginPassword(md5('$citizenUser8'));
        $citizenUser8->setLastname('Tester Buerger');
        $citizenUser8->setFirstname('Gabriela');
        $citizenUser8->setGender('female');
        $citizenUser8->setNewsletter(false);
        $citizenUser8->setNoPiwik(false);
        $citizenUser8->setProfileCompleted(true);
        $citizenUser8->setNewUser(false);
        $citizenUser8->setAccessConfirmed(true);
        $citizenUser8->setForumNotification(false);
        $citizenUser8->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser8->addAddress($this->getReference('testAddress'));
        $citizenUser8->setCurrentCustomer($customer);
        $manager->persist($citizenUser8);

        $citizenUser9 = new User();
        $citizenUser9->setEmail('citizen9@test.de');
        $citizenUser9->setLogin('citizen9@demos-deutschland.de');
        $citizenUser9->setPassword(md5('$citizenUser89'));
        $citizenUser9->setAlternativeLoginPassword(md5('$citizenUser9'));
        $citizenUser9->setLastname('Tester Buerger');
        $citizenUser9->setFirstname('Enrica');
        $citizenUser9->setGender('female');
        $citizenUser9->setNewsletter(false);
        $citizenUser9->setNoPiwik(false);
        $citizenUser9->setProfileCompleted(true);
        $citizenUser9->setNewUser(false);
        $citizenUser9->setAccessConfirmed(true);
        $citizenUser9->setForumNotification(false);
        $citizenUser9->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser9->addAddress($this->getReference('testAddress'));
        $citizenUser9->setCurrentCustomer($customer);
        $manager->persist($citizenUser9);

        $citizenUser10 = new User();
        $citizenUser10->setEmail('citizen10@test.de');
        $citizenUser10->setLogin('citizen10@demos-deutschland.de');
        $citizenUser10->setPassword(md5('$citizenUser10'));
        $citizenUser10->setAlternativeLoginPassword(md5('$citizenUser10'));
        $citizenUser10->setLastname('Tester Buerger');
        $citizenUser10->setFirstname('Rupert');
        $citizenUser10->setGender('male');
        $citizenUser10->setNewsletter(false);
        $citizenUser10->setNoPiwik(false);
        $citizenUser10->setProfileCompleted(true);
        $citizenUser10->setNewUser(false);
        $citizenUser10->setAccessConfirmed(true);
        $citizenUser10->setForumNotification(false);
        $citizenUser10->setDplanroles([$this->getReference('testRoleCitiz')], $customer);
        $citizenUser10->addAddress($this->getReference('testAddress'));
        $citizenUser10->setCurrentCustomer($customer);
        $manager->persist($citizenUser10);

        $user7 = new User();
        $user7->setEmail('user7@test.de');
        $user7->setLogin('user7@demos-deutschland.de');
        // password is "myPass"
        $user7->setPassword(self::DEFAULT_PW_HASH);
        $user7->setAlternativeLoginPassword(self::DEFAULT_PW_HASH);
        $user7->setSalt('NaCl');
        $user7->setLastname('Tester user7');
        $user7->setFirstname('Planungsbüro Meinhard');
        $user7->setGender('fluid');
        $user7->setNewsletter(false);
        $user7->setNoPiwik(false);
        $user7->setProfileCompleted(true);
        $user7->setNewUser(false);
        $user7->setAccessConfirmed(true);
        $user7->setForumNotification(false);
        $user7->setDplanroles([$this->getReference('testRolePlanningOffice')], $customer);
        $user7->setCurrentCustomer($customer);

        $manager->persist($user7);

        $userDelete = new User();
        $userDelete->setDeleted(false);
        $userDelete->setIntranet(true);
        $userDelete->setInvited(true);
        $userDelete->setProfileCompleted(true);
        $userDelete->setTitle('Sir');
        $userDelete->setGender('female');
        $userDelete->setLogin('myLoginString');
        $userDelete->setLanguage('de');
        $userDelete->setGwId('67484');
        $userDelete->setEmail('userDelete@test.de');
        $userDelete->setPassword(self::DEFAULT_PW_HASH);
        $userDelete->setAlternativeLoginPassword(self::DEFAULT_PW_HASH);
        $userDelete->setSalt('NaCl');
        $userDelete->setLastname('Tester userDelete');
        $userDelete->setFirstname('Planungsbüro Meinhard');
        $userDelete->setNewsletter(false);
        $userDelete->setNoPiwik(false);
        $userDelete->setProfileCompleted(true);
        $userDelete->setNewUser(false);
        $userDelete->setAccessConfirmed(true);
        $userDelete->setForumNotification(false);
        $userDelete->setDplanroles([$this->getReference('testRolePlanningOffice')], $customer);
        $userDelete->addAddress($this->getReference('testAddress'));
        $userDelete->setCurrentCustomer($customer);

        $manager->persist($userDelete);

        $user8 = new User();
        $user8->setEmail('user8@test.de');
        $user8->setLogin('user8@demos-deutschland.de');
        // password is "myPass"
        $user8->setPassword(self::DEFAULT_PW_HASH);
        $user8->setAlternativeLoginPassword(self::DEFAULT_PW_HASH);
        $user8->setSalt('NaCl');
        $user8->setLastname('Erfasser1');
        $user8->setFirstname('Daten');
        $user8->setGender('fluid');
        $user8->setNewsletter(false);
        $user8->setNoPiwik(false);
        $user8->setProfileCompleted(true);
        $user8->setNewUser(false);
        $user8->setAccessConfirmed(true);
        $user8->setForumNotification(false);
        $user8->setDplanroles([$this->getReference('testRoleDataInput')], $customer);
        $user8->setCurrentCustomer($customer);

        $manager->persist($user8);

        $user9 = new User();
        $user9->setEmail('user9@test.de');
        $user9->setLogin('user9@demos-deutschland.de');
        // password is "myPass"
        $user9->setPassword(self::DEFAULT_PW_HASH);
        $user9->setAlternativeLoginPassword(self::DEFAULT_PW_HASH);
        $user9->setSalt('NaCl');
        $user9->setLastname('Erfasserin');
        $user9->setFirstname('Daten');
        $user9->setGender('fluid');
        $user9->setNewsletter(false);
        $user9->setNoPiwik(false);
        $user9->setProfileCompleted(true);
        $user9->setNewUser(false);
        $user9->setAccessConfirmed(true);
        $user9->setForumNotification(false);
        $user9->setDplanroles([$this->getReference('testRoleDataInput')], $customer);
        $user9->addFlag('splashModalHideVersion1.0', true);
        $user9->setCurrentCustomer($customer);

        $manager->persist($user9);

        $guestUser = new User();
        $guestUser->setEmail('anonym2@bobsh.de');
        $guestUser->setLogin('anonym2@bobsh.de');
        // password is "myPass"
        $guestUser->setPassword(self::DEFAULT_PW_HASH);
        $guestUser->setAlternativeLoginPassword(self::DEFAULT_PW_HASH);
        $guestUser->setSalt('NaCl');
        $guestUser->setLastname('Bürger');
        $guestUser->setFirstname('Bürger');
        $guestUser->setGender('fluid');
        $guestUser->setNewsletter(false);
        $guestUser->setNoPiwik(false);
        $guestUser->setProfileCompleted(true);
        $guestUser->setNewUser(false);
        $guestUser->setAccessConfirmed(true);
        $guestUser->setForumNotification(false);
        $guestUser->setDplanroles([$this->getReference('testRoleGuest')], $customer);
        $guestUser->setCurrentCustomer($customer);

        $manager->persist($guestUser);

        $md5user = new User();
        $md5user->setEmail('md5PasswordUser@test.de');
        $md5user->setLogin('md5passworduser@demos-deutschland.de');
        $md5user->setPassword(md5('myPass'));
        $md5user->setAlternativeLoginPassword(md5('myPass'));
        $md5user->setLastname('Tester');
        $md5user->setFirstname('Kalle');
        $md5user->setGender('female');
        $md5user->setNewsletter(true);
        $md5user->setNoPiwik(true);
        $md5user->setProfileCompleted(true);
        $md5user->setNewUser(false);
        $md5user->setInvited(false);
        $md5user->setAccessConfirmed(true);
        $md5user->setForumNotification(true);
        $md5user->setDplanroles([$this->getReference('testRoleFP'), $this->getReference('testRolePublicAgencyCoordination')], $customer);
        $md5user->addAddress($this->getReference('testAddress'));
        $md5user->setCurrentCustomer($customer);

        $manager->persist($md5user);

        $user11 = new User();
        $user11->setEmail('user11@test.de');
        $user11->setLogin('user11@demos-deutschland.de');
        $user11->setPassword(md5('user11'));
        $user11->setAlternativeLoginPassword(md5('user11'));
        $user11->setLastname('Tester user11');
        $user11->setFirstname('FP only');
        $user11->setGender('female');
        $user11->setNewsletter(false);
        $user11->setNoPiwik(false);
        $user11->setProfileCompleted(true);
        $user11->setNewUser(false);
        $user11->setAccessConfirmed(true);
        $user11->setForumNotification(false);
        $user11->setDplanroles([$this->getReference('testRoleFP')], $customer);
        $user11->setCurrentCustomer($customer);

        $manager->persist($user11);

        $user12 = new User();
        $user12->setEmail('user12@test.de');
        $user12->setLogin('unfinished@demos-deutschland.de');
        $user12->setPassword(md5('user12'));
        $user12->setAlternativeLoginPassword(md5('user12'));
        $user12->setLastname('Tester user12');
        $user12->setFirstname('FP only unfinished');
        $user12->setGender('female');
        $user12->setNewsletter(false);
        $user12->setNoPiwik(false);
        $user12->setNewUser(true);
        $user12->setProfileCompleted(false);
        $user12->setAccessConfirmed(false);
        $user12->setForumNotification(false);
        $user12->setDplanroles([$this->getReference('testRoleFP')], $customer);
        $user12->setCurrentCustomer($customer);

        $manager->persist($user12);

        $user13 = new User();
        $user13->setEmail('user13@test.de');
        $user13->setLogin('FHHNET\\ZinkDav');
        $user13->setPassword(md5('user13'));
        $user13->setAlternativeLoginPassword(md5('user13'));
        $user13->setLastname('Tester user13');
        $user13->setFirstname('a nother FP');
        $user13->setNewsletter(false);
        $user13->setNoPiwik(true);
        $user13->setNewUser(false);
        $user13->setProfileCompleted(true);
        $user13->setAccessConfirmed(true);
        $user13->setForumNotification(false);
        $user13->setDplanroles([$this->getReference('testRoleFP')], $customer);
        $user13->setCurrentCustomer($customer);

        $manager->persist($user13);

        $user14 = new User();
        $user14->setLogin('FHHNET\\UserWithoutEmailAddress');
        $user14->setPassword(md5('user14'));
        $user14->setAlternativeLoginPassword(md5('user14'));
        $user14->setLastname('Tester user14');
        $user14->setFirstname('a nother FP');
        $user14->setNewsletter(false);
        $user14->setNoPiwik(true);
        $user14->setNewUser(false);
        $user14->setProfileCompleted(true);
        $user14->setAccessConfirmed(true);
        $user14->setForumNotification(false);
        $user14->setDplanroles([$this->getReference('testRoleFP')], $customer);
        $user14->setCurrentCustomer($customer);

        $manager->persist($user14);

        $userContentEditor = new User();
        $userContentEditor->setLogin('content_editor');
        $userContentEditor->setPassword(md5('Advanced_12345'));
        $userContentEditor->setAlternativeLoginPassword(md5('Advanced_12345'));
        $userContentEditor->setLastname('Lastname Content Editor');
        $userContentEditor->setFirstname('Firstname Content Editor');
        $userContentEditor->setNewsletter(false);
        $userContentEditor->setNoPiwik(true);
        $userContentEditor->setNewUser(false);
        $userContentEditor->setProfileCompleted(true);
        $userContentEditor->setAccessConfirmed(true);
        $userContentEditor->setForumNotification(false);
        $userContentEditor->setDplanroles([$role10], $customer);
        $userContentEditor->setCurrentCustomer($customer);

        $manager->persist($userContentEditor);


//        CUSTOMER_MASTER_USER
        $custerMasterUser = new User();
        $custerMasterUser->setLogin('customer_master_user');
        $custerMasterUser->setPassword(md5('customer_master_user_12345'));
        $custerMasterUser->setAlternativeLoginPassword(md5('customer_master_user_12345'));
        $custerMasterUser->setLastname('Lastname Customer Master User');
        $custerMasterUser->setFirstname('Firstname Customer Master User');
        $custerMasterUser->setNewsletter(false);
        $custerMasterUser->setNoPiwik(true);
        $custerMasterUser->setNewUser(false);
        $custerMasterUser->setProfileCompleted(true);
        $custerMasterUser->setAccessConfirmed(true);
        $custerMasterUser->setForumNotification(false);
        $custerMasterUser->setDplanroles([$role14], $customer);
        $custerMasterUser->setCurrentCustomer($customer);

        $manager->persist($custerMasterUser);


        $manager->flush();

        $this->setReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY, $user);
        $this->setReference(self::TEST_USER_2_PLANNER_ADMIN, $user3);
        $this->setReference(self::TEST_USER_CITIZEN, $citizenUser);

        $this->setReference(self::TEST_USER_CITIZEN2, $citizenUser2);
        $this->setReference(self::TEST_USER_CITIZEN3, $citizenUser3);
        $this->setReference(self::TEST_USER_CITIZEN4, $citizenUser4);
        $this->setReference(self::TEST_USER_CITIZEN5, $citizenUser5);
        $this->setReference(self::TEST_USER_CITIZEN6, $citizenUser6);
        $this->setReference(self::TEST_USER_CITIZEN7, $citizenUser7);
        $this->setReference(self::TEST_USER_CITIZEN8, $citizenUser8);
        $this->setReference(self::TEST_USER_CITIZEN9, $citizenUser9);
        $this->setReference(self::TEST_USER_CITIZEN10, $citizenUser10);

        $this->setReference(self::TEST_USER_INVITABLE_INSTITUTION_ONLY, $user5);
        $this->setReference('testUserPlanningOffice', $user2);
        $this->setReference('testUserPlanningOffice2', $user4);
        $this->setReference('testUserMissingFlag', $userMissingFlag);
        $this->setReference('testUserDelete', $userDelete);
        $this->setReference('testUserDataInput1', $user8);
        $this->setReference('testUserDataInput2', $user9);
        $this->setReference(self::TEST_USER_GUEST, $guestUser);
        $this->setReference(self::TEST_USER_FP_ONLY, $user11);
        $this->setReference('md5User', $md5user);
        $this->setReference(self::TEST_USER_UNFINISHED_REGISTRATION, $user12);
        $this->setReference(self::TEST_USER_XBAULEITPLANUNG, $user13);
        $this->setReference(self::TEST_USER_CONTENT_EDITOR, $userContentEditor);
        $this->setReference(self::TEST_USER_CUSTOMER_MASTER, $custerMasterUser);

        $manager->flush();

        // Departments

        $department = new Department();
        $department->setName('TestDepartment');
        $department->addAddress($this->getReference('testAddress'));
        $department->addUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));

        $manager->persist($department);
        $this->setReference(self::TEST_DEPARTMENT, $department);

        $department2 = new Department();
        $department2->setName('testDepartmentMasterToeb');
        $department2->addAddress($this->getReference('testAddress'));
        $department2->addUser($this->getReference(self::TEST_USER_2_PLANNER_ADMIN));

        $manager->persist($department2);
        $this->setReference('testDepartmentMasterToeb', $department2);

        $department2a = new Department();
        $department2a->setName('testDepartmentMasterToeb Einhörner');
        $department2a->addAddress($this->getReference('testAddress'));

        $manager->persist($department2a);
        $this->setReference('testDepartmentMasterToebEinhoerner', $department2a);

        $department3 = new Department();
        $department3->setName('PlanningOffice TestDepartment');
        $department3->addAddress($this->getReference('testAddressPlanningOffice'));
        $department3->addUser($this->getReference('testUserPlanningOffice'));
        $department3->addUser($user7);

        $manager->persist($department3);

        $department4 = new Department();
        $department4->setName('PlanningOffice TestDepartment2');
        $department4->addAddress($this->getReference('testAddressPlanningOffice'));
        $department4->addUser($this->getReference('testUserPlanningOffice2'));

        $manager->persist($department4);

        $department5 = new Department();
        $department5->setName('PlanningOffice TestDepartment3');
        $department5->addAddress($this->getReference('testAddressPlanningOffice'));

        $manager->persist($department5);

        $departmentDelete = new Department();
        $departmentDelete->setName('PlanningOffice TestDepartmentDelete');
        $departmentDelete->addAddress($this->getReference('testAddressPlanningOffice'));
        $departmentDelete->addUser($this->getReference('testUserDelete'));

        $manager->persist($departmentDelete);

        $department6 = new Department();
        $department6->setName('PlanningOffice TestDepartment3');
        $department6->addAddress($this->getReference('testAddressPlanningOffice'));

        $manager->persist($department6);

        $department7 = new Department();
        $department7->setName('FP only department');
        $department7->addAddress($this->getReference('testAddressPlanningOffice'));

        $manager->persist($department7);

        $department8 = new Department();
        $department8->setName('Amt für Landesplanung und Stadtentwicklung');
        $department8->addAddress($this->getReference('testAddressPlanningOffice'));
        $department8->addUser($user13);

        $manager->persist($department8);

        $this->setReference('testDepartmentPlanningOffice', $department3);
        $this->setReference('testDepartmentPlanningOffice2', $department4);
        $this->setReference('testDepartmentPlanningOffice3', $department5);
        $this->setReference('testDepartmentInputDataOrga', $department6);
        $this->setReference('testDepartmentDelete', $departmentDelete);
        $this->setReference('testDepartmentFpOnly', $department7);

        $manager->flush();

        $orgaType = new OrgaType();
        $orgaType->setName(OrgaType::PUBLIC_AGENCY);
        $orgaType->setLabel('TöB');
        $manager->persist($orgaType);

        $orgaType2 = new OrgaType();
        $orgaType2->setName(OrgaType::MUNICIPALITY);
        $orgaType2->setLabel('Kommune');
        $manager->persist($orgaType2);

        $orgaType3 = new OrgaType();
        $orgaType3->setName(OrgaType::PLANNING_AGENCY);
        $orgaType3->setLabel('Planungsbüro');
        $manager->persist($orgaType3);

        $orgaType4 = new OrgaType();
        $orgaType4->setName(OrgaType::DEFAULT);
        $orgaType4->setLabel('Sonstige');
        $manager->persist($orgaType4);

        $address = new Address();
        $address->setStreet('Amselstraße');
        $address->setPostalcode('12345');
        $address->setCity('Drosseldorf');
        $address->setState('Deutschland');
        $manager->persist($address);

        $orga = new Orga();
        $orga->setName('Functional Test FP Orga');
        $orga->addCustomerAndOrgaType($customer, $orgaType2);
        $orga->setShowname(true);
        $orga->setShowlist(true);
        $orga->setGwId('123456789012345678901234567890123456');
        $orga->setGatewayName('myGwname');
        $orga->addSlug(new Slug('orga1Slug'));
        $orga->addAddress($address);

        $orga->addUser($this->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $orga->addUser($md5user);
        $orga->addDepartment($this->getReference(self::TEST_DEPARTMENT));
        $manager->persist($orga);

        $orgaFpOnly = new Orga();
        $orgaFpOnly->setName('FP Only Orga');
        $orgaFpOnly->addCustomerAndOrgaType($customer, $orgaType2);
        $orgaFpOnly->setShowname(true);
        $orgaFpOnly->setShowlist(true);
        $orgaFpOnly->setGwId('aksjd5678901234567890123456');
        $orgaFpOnly->setGatewayName('myGwnameFpOnly');
        $orgaFpOnly->addSlug(new Slug('orgaFPOnlySlug'));
        $orgaFpOnly->addAddress($address);

        $orgaFpOnly->addUser($this->getReference(self::TEST_USER_FP_ONLY));
        $orgaFpOnly->addUser($this->getReference(self::TEST_USER_UNFINISHED_REGISTRATION));
        $orgaFpOnly->addDepartment($this->getReference('testDepartmentFpOnly'));
        $manager->persist($orgaFpOnly);

        $orgaBuerger = new Orga();
        $orgaBuerger->setName(User::ANONYMOUS_USER_ORGA_NAME);
        $orgaBuerger->addCustomerAndOrgaType($customer, $orgaType2);
        $orgaBuerger->setShowname(false);
        $orgaBuerger->setShowlist(false);
        $orgaBuerger->addSlug(new Slug('orgaBürgerSlug'));
        $orgaBuerger->addAddress($address);
        $manager->persist($orgaBuerger);
        $manager->flush();

        $this->changeEntityId(
            '_orga',
            '_o_id',
            User::ANONYMOUS_USER_ORGA_ID,
            '_o_name',
            User::ANONYMOUS_USER_ORGA_NAME
        );
        $orgaBuerger = $this->orgaService->getOrga(User::ANONYMOUS_USER_ORGA_ID);

        $orgaBuerger->addUser($this->getReference(self::TEST_USER_CITIZEN));
        $orgaBuerger->addDepartment($this->getReference(self::TEST_DEPARTMENT));
        $manager->persist($orgaBuerger);

        $orga2 = new Orga();
        $orga2->setName('Functional Test PB Orga');
        $orga2->addCustomerAndOrgaType($customer, $orgaType3);
        $orga2->setShowname(true);
        $orga2->addSlug(new Slug('orga2Slug'));
        $orga2->setShowlist(true);
        $orga2->addUser($this->getReference('testUserPlanningOffice'));
        $orga2->addUser($user7);
        $orga2->addDepartment($this->getReference('testDepartmentPlanningOffice'));

        $manager->persist($orga2);

        $address3 = new Address();
        $address3->setStreet('Finkweg');
        $address3->setPostalcode('18345');
        $address3->setCity('Drosselhausen');
        $address3->setState('Deutschland');
        $manager->persist($address3);

        $orga3 = new Orga();
        $orga3->setName('Functional Test MastertToeb Orga');
        $orga3->addCustomerAndOrgaType($customer, $orgaType);
        $orga3->setShowname(true);
        $orga3->setShowlist(true);
        $orga3->setGatewayName('MasterToebGatewayName');
        $orga3->addSlug(new Slug('orga3Slug'));
        $orga3->addAddress($address3);

        $orga3->addUser($this->getReference(self::TEST_USER_2_PLANNER_ADMIN));
        $orga3->addDepartment($this->getReference('testDepartmentMasterToeb'));
        $manager->persist($orga3);

        $orga4 = new Orga();
        $orga4->setName('Functional Test PB Orga 2');
        $orga4->addCustomerAndOrgaType($customer, $orgaType3);
        $orga4->setShowname(true);
        $orga4->addSlug(new Slug('orga4Slug'));
        $orga4->setShowlist(true);
        $orga4->addUser($this->getReference('testUserPlanningOffice2'));
        $orga4->addDepartment($this->getReference('testDepartmentPlanningOffice2'));

        $manager->persist($orga4);

        $orga5 = new Orga();
        $orga5->setName('Functional Test Invitable Institution');
        $orga5->addCustomerAndOrgaType($customer, $orgaType);
        $orga5->setShowname(true);
        $orga5->setShowlist(true);
        $orga5->setGatewayName('GatewayName-Other');
        $orga5->addSlug(new Slug('orga5Slug'));
        $orga5->addAddress($address3);

        $orga5->addUser($this->getReference(self::TEST_USER_INVITABLE_INSTITUTION_ONLY));
        $orga5->addDepartment($this->getReference(self::TEST_DEPARTMENT));
        $manager->persist($orga5);

        $orga6 = new Orga();
        $orga6->setName('Functional Test PB Orga 3 Nouser');
        $orga6->addCustomerAndOrgaType($customer, $orgaType3);
        $orga6->setShowname(true);
        $orga6->addSlug(new Slug('orga6Slug'));
        $orga6->setShowlist(true);
        $orga6->addDepartment($this->getReference('testDepartmentPlanningOffice3'));

        $manager->persist($orga6);

        $orga7 = new Orga();
        $orga7->setName('Functional Test deleted Orga');
        $orga7->addCustomerAndOrgaType($customer, $orgaType3);
        $orga7->setShowname(true);
        $orga7->setShowlist(true);
        $orga7->addSlug(new Slug('orga7Slug'));
        $orga7->setDeleted(true);
        $orga7->addDepartment($this->getReference('testDepartmentPlanningOffice3'));
        $orga7->addUser($this->getReference('testUserDelete'));

        $manager->persist($orga7);

        $orga8 = new Orga();
        $orga8->setName('Functional Test Datainput Orga');
        $orga8->addCustomerAndOrgaType($customer, $orgaType);
        $orga8->setShowname(true);
        $orga8->setShowlist(true);
        $orga8->addSlug(new Slug('orga8Slug'));
        $orga8->setDeleted(true);
        $orga8->addDepartment($this->getReference('testDepartmentInputDataOrga'));
        $orga8->addUser($this->getReference('testUserDataInput1'));

        $manager->persist($orga8);

        $orga9 = new Orga();
        $orga9->setName('Functional Test Datainput Orga II');
        $orga9->addCustomerAndOrgaType($customer, $orgaType);
        $orga9->setShowname(true);
        $orga9->setShowlist(true);
        $orga9->addSlug(new Slug('orga9Slug'));
        $orga9->setDeleted(true);
        $orga9->addDepartment($this->getReference('testDepartmentInputDataOrga'));
        $orga9->addUser($this->getReference('testUserDataInput2'));

        $manager->persist($orga9);

        $orga10 = new Orga();
        $masterToeb = new MasterToeb();
        $masterToeb->setOrga($orga10);
        $orga10->setName('Functional Test MastertToeb Orga');
        $orga10->addCustomerAndOrgaType($customer, $orgaType);
        $orga10->setShowname(true);
        $orga10->setShowlist(true);
        $orga10->setGatewayName('MasterToebGatewayName');
        $orga10->addSlug(new Slug('orga10Slug'));
        $orga10->addAddress($address3);

        $orga10->addUser($this->getReference(self::TEST_USER_2_PLANNER_ADMIN));
        $orga10->addDepartment($this->getReference('testDepartmentMasterToeb'));
        $manager->persist($orga10);
        $manager->persist($masterToeb);

        $orga11 = new Orga();
        $orga11->setName('Behörde für Stadtentwicklung und Wohnen');
        $orga11->setShowname('Behörde für Stadtentwicklung und Wohnen');
        $orga11->addCustomerAndOrgaType($customer, $orgaType);
        $orga11->setShowname(true);
        $orga11->setShowlist(true);
        $orga11->addSlug(new Slug('orga11Slug'));
        $orga11->setDeleted(false);
        $orga11->addDepartment($department8);
        $orga11->addUser($user13);

        $manager->persist($orga11);

        $orga12 = new Orga();
        $orga12->setName('BSW Rejected');
        $orga12->addCustomerAndOrgaType($customer, $orgaType);
        $orga12->addCustomerAndOrgaType($customer, $orgaType2, OrgaStatusInCustomer::STATUS_REJECTED);
        $orga12->setShowname(true);
        $orga12->setShowlist(true);
        $orga12->addSlug(new Slug('orga12Slug'));
        $orga12->setDeleted(false);
        $orga12->addDepartment($department8);
        $orga12->addUser($user13);

        $manager->persist($orga12);

        $this->setReference(self::TEST_ORGA_FP, $orga);
        $this->setReference('testOrgaFPOnly', $orgaFpOnly);
        $this->setReference(self::TEST_ORGA_PB, $orga2);
        $this->setReference('testOrgaPB2', $orga4);
        $this->setReference(self::TEST_ORGA_PUBLIC_AGENCY, $orga3);
        $this->setReference('testOrgaInvitableInstitutionOnly', $orga5);
        $this->setReference('deletedOrga', $orga7);
        $this->setReference(self::DATA_INPUT_ORGA, $orga8);
        $this->setReference('dataInputOrga2', $orga9);
        $this->setReference('orgaWithMasterToeb', $orga10);
        $this->setReference(self::TEST_ORGA_WITH_REJECTED_ORGA_TYPE, $orga12);

        $anonymousUser = new User();
        $anonymousUser->setId(User::ANONYMOUS_USER_ID);
        $anonymousUser->setGender('male');
        $anonymousUser->setFirstname('Bürger');
        $anonymousUser->setLastname('Bürger');
        $anonymousUser->setEmail(User::ANONYMOUS_USER_LOGIN);
        $anonymousUser->setLogin(User::ANONYMOUS_USER_LOGIN);
        $anonymousUser->setLogin(md5('$anonymousUser'));
        $anonymousUser->setAlternativeLoginPassword(md5('$anonymousUser'));
        $anonymousUser->setNewsletter(false);
        $anonymousUser->setNoPiwik(false);
        $anonymousUser->setProfileCompleted(true);
        $anonymousUser->setNewUser(false);
        $anonymousUser->setAccessConfirmed(true);
        $anonymousUser->setForumNotification(false);
        $anonymousUser->setDplanroles([$this->getReference('testRolePlanningOffice')], $customer);
        $anonymousUser->setCurrentCustomer($customer);

        $manager->persist($anonymousUser);

        $orgaBuerger->addUser($anonymousUser);
        $manager->persist($orgaBuerger);

        $manager->flush();
    }
}
