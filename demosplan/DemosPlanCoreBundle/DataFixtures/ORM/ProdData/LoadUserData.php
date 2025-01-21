<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\ProdData;

use demosplan\DemosPlanCoreBundle\Entity\Slug;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserService;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\DBAL\DBALException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Doctrine\Persistence\ObjectManager;

class LoadUserData extends ProdFixture implements DependentFixtureInterface
{
    public function __construct(
        EntityManagerInterface $entityManager,
        private readonly OrgaService $orgaService,
        private readonly UserHandler $userHandler,
        private readonly UserService $userService,
    ) {
        parent::__construct($entityManager);
    }

    public function load(ObjectManager $manager): void
    {
        // create defaultcustomer
        $customer = new Customer('demos', 'demos');
        $manager->persist($customer);

        // Load OrgaTyes
        $this->createOrgaType($manager, OrgaType::PUBLIC_AGENCY, 'Firmenkunde');
        $this->createOrgaType($manager, OrgaType::PLANNING_AGENCY, 'Planungsbüro');
        $this->createOrgaType($manager, OrgaType::HEARING_AUTHORITY_AGENCY, 'Anhörungsbehörde');
        $this->createOrgaType($manager, OrgaType::DEFAULT, 'Sonstige');

        $orgaTypeOlauth = $this->createOrgaType($manager, OrgaType::MUNICIPALITY, 'Kommune');

        $this->createSuperUser($orgaTypeOlauth, $manager, $customer);

        // Citizen pseudo user suboptimal, but isso
        $this->createAnonymousCitizenUser($manager, $orgaTypeOlauth, $customer);
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
        $orga->setName('DEMOS plan GmbH');
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
            $this->getReference('role_RMOPSM'),
            $this->getReference('role_RMOPSA'),
        ], $customer);
        $superUser->setOrga($orga);
        $orga->addUser($superUser);
        $superUser->setDepartment($department);
        $department->addUser($superUser);

        $superUser->setNewsletter(false);
        $superUser->setAccessConfirmed(true);
        $superUser->setProfileCompleted(true);
        $superUser->setNoPiwik(false);
        $superUser->setForumNotification(false);
        $superUser->setNewUser(false);

        $manager->persist($superUser);
    }

    /**
     * @throws DBALException
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function createAnonymousCitizenUser(
        ObjectManager $manager,
        OrgaType $orgaTypeOlauth,
        Customer $customer,
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

        $citizenUser->setNewsletter(false);
        $citizenUser->setAccessConfirmed(true);
        $citizenUser->setProfileCompleted(true);
        $citizenUser->setNoPiwik(false);
        $citizenUser->setForumNotification(false);
        $citizenUser->setNewUser(false);

        $citizenUser->setOrga($orga);
        $orga->addUser($citizenUser);
        $citizenUser->setDepartment($department);
        $department->addUser($citizenUser);

        $manager->persist($citizenUser);

        $manager->flush();
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
}
