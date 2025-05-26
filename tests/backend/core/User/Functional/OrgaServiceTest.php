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
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaService;
use Exception;
use Tests\Base\FunctionalTestCase;

class OrgaServiceTest extends FunctionalTestCase
{
    /**
     * @var OrgaService
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

        $this->sut = self::getContainer()->get(OrgaService::class);
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->testOrgaFp = $this->fixtures->getReference('testOrgaFP');
        $this->testDepartment = $this->fixtures->getReference('testDepartment');
        $this->testAddress = $this->fixtures->getReference('testAddress');
    }

    // ################## Orga ######################

    public function testAddOrgaWithAddress()
    {
        /** @var \demosplan\DemosPlanCoreBundle\Entity\User\Customer $customer */
        $customer = $this->fixtures->getReference('Brandenburg');
        $data = [
            'name'      => 'newName',
            'paperCopy' => '2',
            'showlist'  => '1',
            'type'      => OrgaType::PLANNING_AGENCY,
            'address'   => $this->testAddress,
            'customer'  => $customer,
        ];
        $orga = $this->sut->addOrga($data);
        static::assertEquals($data['name'], $orga->getName());
        static::assertEquals($data['paperCopy'], $orga->getPaperCopy());
        static::assertEquals(true, $orga->getShowlist());
        // nicht mit übergeben, also false
        static::assertEquals(false, $orga->getShowname());
        static::assertContains($data['type'], $orga->getTypes($customer->getSubdomain()));
        static::assertEquals($this->testAddress->getStreet(), $orga->getStreet());
        static::assertEquals($this->testAddress->getPostalcode(), $orga->getPostalcode());
        static::assertEquals($this->testAddress->getCity(), $orga->getCity());
        static::assertEquals($this->testAddress->getPhone(), $orga->getPhone());
    }

    public function testAddOrga()
    {
        /** @var \demosplan\DemosPlanCoreBundle\Entity\User\Customer $customer */
        $customer = $this->fixtures->getReference('Brandenburg');
        $data = [
            'name'      => 'newName',
            'paperCopy' => '2',
            'showlist'  => '1',
            'type'      => OrgaType::PLANNING_AGENCY,
            'customer'  => $customer,
        ];
        $orga = $this->sut->addOrga($data);
        static::assertEquals($data['name'], $orga->getName());
        static::assertEquals($data['paperCopy'], $orga->getPaperCopy());
        static::assertEquals(true, $orga->getShowlist());
        // nicht mit übergeben, also false
        static::assertEquals(false, $orga->getShowname());
        static::assertContains($data['type'], $orga->getTypes($customer->getSubdomain()));
    }

    public function testAddOrgaUser()
    {
        self::markSkippedForCIIntervention();

        $orga = $this->sut->getOrga($this->testOrgaFp->getId());
        static::assertEquals($this->testOrgaFp->getId(), $orga->getId());
        static::assertCount(1, $orga->getUsers());

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

        $em = $this->getEntityManager();
        $em->persist($newUser);
        $em->flush();
        static::assertNull($newUser->getOrga());

        $orgaUserAdded = $this->sut->orgaAddUser($this->testOrgaFp->getId(), $newUser);
        static::assertEquals($this->testOrgaFp->getId(), $orgaUserAdded->getId());
        static::assertCount(2, $orgaUserAdded->getUsers());
        static::assertNotNull($newUser->getOrga());
        static::assertCount(1, $newUser->getDplanroles());
    }

    public function testGetDataInputOrgas()
    {
        self::markSkippedForCIIntervention();

        $orgas = $this->sut->getDataInputOrgaList();
        static::assertCount(2, $orgas);
        static::assertEquals($this->fixtures->getReference('dataInputOrga')->getId(), $orgas[0]->getId());
    }

    public function testGetOrgaByIds()
    {
        $orgaIds = [
            $this->fixtures->getReference('testOrgaFP'),
            $this->fixtures->getReference('testOrgaPB'),
        ];
        $orgas = $this->sut->getOrganisationsByIds($orgaIds);
        static::assertCount(2, $orgas);
    }

    public function testGetPlanningOffices()
    {
        /** @var \demosplan\DemosPlanCoreBundle\Entity\User\Customer $customer */
        $customer = $this->fixtures->getReference('testCustomer');
        $orgas = $this->sut->getPlanningOfficesList($customer);

        foreach ($orgas as $organisation) {
            static::assertFalse($organisation->isDeleted());
        }
        static::assertCount(3, $orgas);

        $orgas = $this->sut->getPlanningOfficesList($this->fixtures->getReference('testCustomerBrandenburg'));
        static::assertCount(0, $orgas);
    }

    /**
     * @throws Exception
     */
    public function testGetOrga()
    {
        /** @var \demosplan\DemosPlanCoreBundle\Entity\User\Customer $customer */
        $customer = $this->fixtures->getReference('testCustomer');
        $orga = $this->sut->getOrga($this->testOrgaFp->getId());
        static::assertEquals($this->testOrgaFp->getId(), $orga->getId());
        static::assertEquals($this->testOrgaFp->getOrgaTypes($customer->getSubdomain()), $orga->getOrgaTypes($customer->getSubdomain()));
        static::assertTrue($orga->isRegisteredInSubdomain($this->testOrgaFp->getCustomers()[0]->getSubdomain()));
        static::assertFalse($orga->isRegisteredInSubdomain('NotExistingSubdomain'));
    }

    public function testGetOrgaOrgaTypes(): void
    {
        $customer = $this->getCustomerReference('testCustomer');
        $orga = $this->getOrgaReference(LoadUserData::TEST_ORGA_WITH_REJECTED_ORGA_TYPE);
        $activatedOrgaTypesOnly = $orga->getOrgaTypes($customer->getSubdomain(), true);
        $allOrgaTypes = $orga->getOrgaTypes($customer->getSubdomain());
        static::assertCount(2, $allOrgaTypes);
        static::assertCount(1, $activatedOrgaTypesOnly);
    }

    /**
     * @throws Exception
     */
    public function testGetOrgaList(): void
    {
        self::markSkippedForCIIntervention();

        $listOfOrgas = $this->sut->getOrganisations();
        static::assertIsArray($listOfOrgas);
        static::assertCount(11, $listOfOrgas);

        /** @var Orga $orga */
        foreach ($listOfOrgas as $orga) {
            static::assertInstanceOf(Orga::class, $orga);
            static::assertFalse($orga->isDeleted());
        }

        $listOfOrgas = $this->sut->getOrganisations();
        static::assertIsArray($listOfOrgas);
        static::assertCount(11, $listOfOrgas);

        /** @var Orga $orga */
        foreach ($listOfOrgas as $orga) {
            static::assertInstanceOf(Orga::class, $orga);
            static::assertFalse($orga->isDeleted());
        }
    }

    /**
     * @throws Exception
     */
    public function testGetOrgaAddress()
    {
        $orga = $this->sut->getOrga($this->testOrgaFp->getId());
        static::assertSame($this->testOrgaFp->getId(), $orga->getId());
        static::assertEquals($this->testOrgaFp->getAddresses()->first(), $orga->getAddresses()->first());
    }
}
