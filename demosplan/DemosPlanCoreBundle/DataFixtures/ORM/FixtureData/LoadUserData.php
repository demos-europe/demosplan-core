<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\FixtureData;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\DemosFixture;
use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanUserBundle\Logic\OrgaService;
use demosplan\DemosPlanUserBundle\Logic\UserHandler;
use demosplan\DemosPlanUserBundle\Logic\UserService;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ObjectManager;

class LoadUserData extends DemosFixture implements DependentFixtureInterface
{
    use FixtureGroup;

    private UserService $userService;
    private UserHandler $userHandler;
    private OrgaService $orgaService;

    public function __construct(
        EntityManagerInterface $entityManager,
        OrgaService $orgaService,
        UserHandler $userHandler,
        UserService $userService
    ) {
        parent::__construct($entityManager);
        $this->userService = $userService;
        $this->userHandler = $userHandler;
        $this->orgaService = $orgaService;
    }

    public function load(ObjectManager $manager)
    {

        // create defaultcustomer
        $customer = new Customer('demos', 'demos');
        $manager->persist($customer);

        // Load OrgaTyes
        $orgaTypeInstitution = $this->createOrgaType($manager, OrgaType::PUBLIC_AGENCY, 'Firmenkunde');
        $orgaTypePlanningAgency = $this->createOrgaType($manager, OrgaType::PLANNING_AGENCY, 'Planungsbüro');
        $this->createOrgaType($manager, OrgaType::HEARING_AUTHORITY_AGENCY, 'Anhörungsbehörde');
        $orgaTypeDefault = $this->createOrgaType($manager, OrgaType::DEFAULT, 'Sonstige');

        $orgaTypeOlauth = $this->createOrgaType($manager, OrgaType::MUNICIPALITY, 'Kommune');

        $this->createSuperUser($orgaTypeOlauth, $manager, $customer);

        // Citizen pseudo user suboptimal, but isso
        $this->createAnonymousCitizenUser($manager, $orgaTypeOlauth, $customer);

        $this->createAdminUsers($orgaTypeDefault, $manager, $customer);
        $this->createPlannerUsers($orgaTypeOlauth, $manager, $customer);
        $this->createPlaningAgencyUsers($orgaTypePlanningAgency, $manager, $customer);
        $this->createInstitutionUsers($orgaTypeInstitution, $manager, $customer);
        $this->createCitizenUser($manager, $customer);

        $manager->flush();
    }

    public function getDependencies()
    {
        return [
            LoadRolesData::class,
        ];
    }

    /**
     * @param Customer $customer
     */
    public function createSuperUser(OrgaType $orgaTypeOlauth, ObjectManager $manager, $customer)
    {
        // Create Department
        $department = new Department();
        $department->setName('DEMOS');

        $manager->persist($department);

        // Create Orga
        $orga = new Orga();
        $orga->setName('DEMOS E-Partizipation GmbH');
        $slug = new Slug('demos');
        $orga->addSlug($slug);
        $orga->setCurrentSlug($slug);
        $orga->addCustomerAndOrgaType($customer, $orgaTypeOlauth);
        $orga->setDepartments([$department]);

        $manager->persist($orga);
        $this->setReference('orga_demos', $orga);

        $superUser = new User();
        $superUser->setFirstname('DEMOS');
        $superUser->setLastname('Support');
        $superUser->setLogin('demos_admin');
        $superUser->setEmail('bob-sh27@demos-deutschland.de');
        // use md5 only to be able to login, otherwise projectspecific salt will
        // mitigate login. Pass is upgraded anyhow automatically at first login
        $superUser->setPassword(md5('Advanced_12345'));
        $superUser->setAlternativeLoginPassword(md5('Advanced_12345'));
        $superUser->setLanguage('de_DE');
        $superUser->setDplanroles([
            $this->getReference('role_RTSUPP'),
            $this->getReference('role_RCOMAU'),
            $this->getReference('role_RMOPSM'),
            $this->getReference('role_RMOPSA'),
        ], $customer);
        $superUser->setOrga($orga);
        $orga->addUser($superUser);
        $superUser->setDepartment($department);
        $department->addUser($superUser);

        $this->setDefaultUserData($superUser);

        $manager->persist($superUser);
    }
    public function createAdminUsers(OrgaType $orgaTypeDefault, ObjectManager $manager, $customer)
    {
        // Create Department
        $department = new Department();
        $department->setName('Zentral');

        $manager->persist($department);

        // Create Orga
        $orga = new Orga();
        $orga->setName('Oberverwaltung');
        $slug = new Slug('oberverwaltung');
        $orga->addSlug($slug);
        $orga->setCurrentSlug($slug);
        $orga->addCustomerAndOrgaType($customer, $orgaTypeDefault);
        $orga->setDepartments([$department]);

        $manager->persist($orga);

        $baboUser = new User();
        $baboUser->setFirstname('Monica');
        $baboUser->setLastname('Support');
        $baboUser->setLogin('monica.support@oberverwaltung.de');
        $baboUser->setEmail('monica.support@oberverwaltung.de');
        $baboUser->setPassword(md5('Monica-Oberverwaltung22'));
        $baboUser->setAlternativeLoginPassword(md5('Monica-Oberverwaltung22'));
        $baboUser->setLanguage('de_DE');
        $baboUser->setDplanroles([
            $this->getReference('role_RTSUPP'),
            $this->getReference('role_RCOMAU'),
            $this->getReference('role_RMOPSM'),
        ], $customer);
        $baboUser->setOrga($orga);
        $orga->addUser($baboUser);
        $baboUser->setDepartment($department);
        $department->addUser($baboUser);

        $this->setDefaultUserData($baboUser);

        $manager->persist($baboUser);

        $redaktionUser = new User();
        $redaktionUser->setFirstname('Hans');
        $redaktionUser->setLastname('Redaktion');
        $redaktionUser->setLogin('hans.redaktion@oberverwaltung.de');
        $redaktionUser->setEmail('hans.redaktion@oberverwaltung.de');
        $redaktionUser->setPassword(md5('Hans-Oberverwaltung22'));
        $redaktionUser->setAlternativeLoginPassword(md5('Hans-Oberverwaltung22'));
        $redaktionUser->setDplanroles([
            $this->getReference('role_RTEDIT'),
        ], $customer);
        $redaktionUser->setOrga($orga);
        $orga->addUser($redaktionUser);
        $redaktionUser->setDepartment($department);
        $department->addUser($redaktionUser);

        $this->setDefaultUserData($redaktionUser);

        $manager->persist($redaktionUser);
    }
    /**
     * @throws DBALException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createAnonymousCitizenUser(
        ObjectManager $manager,
        OrgaType $orgaTypeOlauth,
        Customer $customer
    ): void {
        // Create Department
        $department = new Department();
        $department->setName(User::ANONYMOUS_USER_DEPARTMENT_NAME);

        $manager->persist($department);
        $manager->flush();

        $this->changeEntityId(
            '_department',
            '_d_id',
            User::ANONYMOUS_USER_DEPARTMENT_ID,
            '_d_name',
            User::ANONYMOUS_USER_DEPARTMENT_NAME
        );

        $department = $this->userService->getDepartment(User::ANONYMOUS_USER_DEPARTMENT_ID);

        // Create Orga
        $orga = new Orga();
        $orga->setName(User::ANONYMOUS_USER_ORGA_NAME);
        $slug = new Slug(User::ANONYMOUS_USER_ORGA_NAME);
        $orga->addSlug($slug);
        $orga->setCurrentSlug($slug);
        $orga->addCustomerAndOrgaType($customer, $orgaTypeOlauth);

        $manager->persist($orga);
        $manager->flush();

        $this->changeEntityId(
            '_orga',
            '_o_id',
            User::ANONYMOUS_USER_ORGA_ID,
            '_o_name',
            User::ANONYMOUS_USER_ORGA_NAME
        );

        $orga = $this->orgaService->getOrga(User::ANONYMOUS_USER_ORGA_ID);

        $orga->setDepartments([$department]);

        $manager->persist($orga);

        $citizenUser = new User();
        $citizenUser->setFirstname(User::ANONYMOUS_USER_NAME);
        $citizenUser->setLastname(User::ANONYMOUS_USER_NAME);
        $citizenUser->setLogin(User::ANONYMOUS_USER_LOGIN);
        $citizenUser->setEmail(User::ANONYMOUS_USER_LOGIN);
        // use md5 only to be able to login, otherwise projectspecific salt will
        // mitigate login. Pass is upgraded anyhow automatically at first login
        $citizenUser->setPassword(md5(random_int(0, mt_getrandmax())));
        $citizenUser->setAlternativeLoginPassword(md5(random_int(0, mt_getrandmax())));
        $citizenUser->setLanguage('de_DE');
        $manager->persist($citizenUser);

        $manager->flush();

        $this->changeEntityId(
            '_user',
            '_u_id',
            User::ANONYMOUS_USER_ID,
            '_u_login',
            User::ANONYMOUS_USER_LOGIN
        );

        // reload procedure as doctrine could not know that id changed
        $citizenUser = $this->userHandler->getSingleUser(User::ANONYMOUS_USER_ID);

        $citizenUser->setDplanroles([
            $this->getReference('role_RGUEST'),
        ], $customer);

        $this->setDefaultUserData($citizenUser);

        $citizenUser->setOrga($orga);
        $orga->addUser($citizenUser);
        $citizenUser->setDepartment($department);
        $department->addUser($citizenUser);

        $manager->persist($citizenUser);

        $this->setReference('orga_citizen', $orga);
        $this->setReference('department_citizen', $department);

    }

    /**
     * @param string $name
     * @param string $label
     */
    protected function createOrgaType(ObjectManager $manager, $name, $label): OrgaType
    {
        $orgaType = new OrgaType();
        $orgaType->setName($name);
        $orgaType->setLabel($label);
        $manager->persist($orgaType);

        return $orgaType;
    }

    public function createPlannerUsers(OrgaType $orgaTypeOlauth, ObjectManager $manager, $customer)
    {
        // Create Department
        $department = new Department();
        $department->setName('Trifolium');

        $manager->persist($department);

        // Create Orga
        $orga = new Orga();
        $orga->setName('Amt der grünen Wiese');
        $slug = new Slug('gruenewiese');
        $orga->addSlug($slug);
        $orga->setCurrentSlug($slug);
        $orga->addCustomerAndOrgaType($customer, $orgaTypeOlauth);
        $orga->setDepartments([$department]);

        $manager->persist($orga);
        $this->setReference('orga_gruenewiese', $orga);

        $karla = new User();
        $karla->setFirstname('Karla');
        $karla->setLastname('Fachplanerin');
        $karla->setLogin('karla.fachplanerin@gruenewiese.de');
        $karla->setEmail('karla.fachplanerin@gruenewiese.de');
        $karla->setPassword(md5('Karla-Gruenewiese22'));
        $karla->setAlternativeLoginPassword(md5('Karla-Gruenewiese22'));
        $karla->setDplanroles([
            $this->getReference('role_RMOPSA'),
        ], $customer);
        $karla->setOrga($orga);
        $orga->addUser($karla);
        $karla->setDepartment($department);
        $department->addUser($karla);
        $this->setDefaultUserData($karla);
        $manager->persist($karla);

        $franz = new User();
        $franz->setFirstname('Franz');
        $franz->setLastname('Fachplaner');
        $franz->setLogin('franz.fachplaner@gruenewiese.de');
        $franz->setEmail('franz.fachplaner@gruenewiese.de');
        $franz->setPassword(md5('Franz-Gruenewiese22'));
        $franz->setAlternativeLoginPassword(md5('Franz-Gruenewiese22'));
        $franz->setDplanroles([
            $this->getReference('role_RMOPSA'),
        ], $customer);
        $franz->setOrga($orga);
        $orga->addUser($franz);
        $franz->setDepartment($department);
        $department->addUser($franz);
        $this->setDefaultUserData($franz);
        $manager->persist($franz);
    }

    public function createPlaningAgencyUsers(
        OrgaType $orgaTypePlanningAgency,
        ObjectManager $manager,
        $customer
    ) {
        // Create Department
        $department = new Department();
        $department->setName('Hamsterwiese');

        $manager->persist($department);

        // Create Orga
        $orga = new Orga();
        $orga->setName('Büro für biologische Aufnahmen');
        $slug = new Slug('bioauf');
        $orga->addSlug($slug);
        $orga->setCurrentSlug($slug);
        $orga->addCustomerAndOrgaType($customer, $orgaTypePlanningAgency);
        $orga->setDepartments([$department]);

        $manager->persist($orga);

        $martina = new User();
        $martina->setFirstname('Martina');
        $martina->setLastname('Planungsbüro');
        $martina->setLogin('martina.planungsbuero@bioauf.de');
        $martina->setEmail('martina.planungsbuero@bioauf.de');
        $martina->setPassword(md5('Martina-Bioauf22'));
        $martina->setAlternativeLoginPassword(md5('Martina-Bioauf22'));
        $martina->setDplanroles([
            $this->getReference('role_RMOPPO'),
        ], $customer);
        $martina->setOrga($orga);
        $orga->addUser($martina);
        $martina->setDepartment($department);
        $department->addUser($martina);
        $this->setDefaultUserData($martina);
        $manager->persist($martina);

    }

    public function createInstitutionUsers(OrgaType $orgaTypeInstitution, ObjectManager $manager, $customer)
    {
        // Create Department
        $department = new Department();
        $department->setName('Lausunto');

        $manager->persist($department);

        // Create Orga
        $orga = new Orga();
        $orga->setName('Mitreden e.V.');
        $slug = new Slug('mitreden');
        $orga->addSlug($slug);
        $orga->setCurrentSlug($slug);
        $orga->addCustomerAndOrgaType($customer, $orgaTypeInstitution);
        $orga->setDepartments([$department]);

        $manager->persist($orga);
        $this->setReference('orga_mitreden', $orga);

        $ida = new User();
        $ida->setFirstname('Ida');
        $ida->setLastname('Koordinatorin');
        $ida->setLogin('ida.koordinatorin@mitreden.de');
        $ida->setEmail('ida.koordinatorin@mitreden.de');
        $ida->setPassword(md5('Ida-Mitreden22'));
        $ida->setAlternativeLoginPassword(md5('Ida-Mitreden22'));
        $ida->setDplanroles([
            $this->getReference('role_RPSOCO'),
        ], $customer);
        $ida->setOrga($orga);
        $orga->addUser($ida);
        $ida->setDepartment($department);
        $department->addUser($ida);
        $this->setDefaultUserData($ida);
        $manager->persist($ida);

        $jack = new User();
        $jack->setFirstname('Jack');
        $jack->setLastname('Sachbearbeiter');
        $jack->setLogin('jack.sachbearbeiter@mitreden.de');
        $jack->setEmail('jack.sachbearbeiter@mitreden.de');
        $jack->setPassword(md5('Jack-Mitreden22'));
        $jack->setAlternativeLoginPassword(md5('Jack-Mitreden22'));
        $jack->setDplanroles([
            $this->getReference('role_RPSODE'),
        ], $customer);
        $jack->setOrga($orga);
        $orga->addUser($jack);
        $jack->setDepartment($department);
        $department->addUser($jack);
        $this->setDefaultUserData($jack);
        $manager->persist($jack);
    }

    public function createCitizenUser(
        ObjectManager $manager,
        Customer $customer
    ): void {
        // Create Department

        $citizenUser = new User();
        $citizenUser->setFirstname('Hildegunst');
        $citizenUser->setLastname('Wolpertinger');
        $citizenUser->setLogin('wolpertinger@zamon.io');
        $citizenUser->setEmail('wolpertinger@zamon.io');
        $citizenUser->setPassword(md5('Rumo@Buchhaim04'));
        $citizenUser->setAlternativeLoginPassword(md5('Rumo@Buchhaim04'));
        $citizenUser->setDplanroles([
            $this->getReference('role_RCITIZ'),
        ], $customer);

        $this->setDefaultUserData($citizenUser);

        $orga = $this->getReference('orga_citizen');
        $citizenUser->setOrga($orga);
        $orga->addUser($citizenUser);
        $department = $this->getReference('department_citizen');
        $citizenUser->setDepartment($department);
        $department->addUser($citizenUser);

        $manager->persist($citizenUser);

    }
    private function setDefaultUserData(User $karla): void
    {
        $karla->setNewsletter(false);
        $karla->setAccessConfirmed(true);
        $karla->setProfileCompleted(true);
        $karla->setNoPiwik(false);
        $karla->setForumNotification(false);
        $karla->setNewUser(false);
    }
}
