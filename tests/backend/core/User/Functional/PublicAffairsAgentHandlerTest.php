<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\PublicAffairsAgentHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Tests\Base\FunctionalTestCase;

class PublicAffairsAgentHandlerTest extends FunctionalTestCase
{
    /**
     * @var PublicAffairsAgentHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(PublicAffairsAgentHandler::class);
    }

    /**
     * Grep some organisations from the fixtures and check if
     * {@link PublicAffairsAgentHandler::isPublicAffairsAgent} returns the expected result
     * for each one..
     */
    public function testIsPublicAffairsAgent()
    {
        $orga = $this->getOrgaReference('testOrgaFP');
        $orga2 = $this->getOrgaReference('testOrgaPB');
        $orga3 = $this->getOrgaReference('testOrgaInvitableInstitution');
        $orga4 = $this->getOrgaReference('testOrgaPB2');
        $orga5 = $this->getOrgaReference('testOrgaInvitableInstitutionOnly');
        $orga7 = $this->getOrgaReference('deletedOrga');
        $orga8 = $this->getOrgaReference('dataInputOrga');
        $orga9 = $this->getOrgaReference('dataInputOrga2');
        self::assertTrue($this->sut->isPublicAffairsAgent($orga));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga2));
        self::assertTrue($this->sut->isPublicAffairsAgent($orga3));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga4));
        self::assertTrue($this->sut->isPublicAffairsAgent($orga5));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga7));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga8));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga9));
    }

    public function testAcceptedPublicAgencyWithoutUsersIsPublicAffairsAgent(): void
    {
        // Arrange
        $orga = $this->createOrga($this->makeNewCustomerCurrent());

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertTrue($result);
    }

    public function testAcceptedPublicAgencyWithOnlyPlanningAgencyUserIsPublicAffairsAgent(): void
    {
        // Arrange
        $customer = $this->makeNewCustomerCurrent();
        $orga = $this->createOrga($customer);
        $this->addUser($orga, RoleInterface::PLANNING_AGENCY_ADMIN, $customer);

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertTrue($result);
    }

    public function testAcceptedPublicAgencyWithInstitutionRoleInAnotherCustomerIsPublicAffairsAgent(): void
    {
        // Arrange
        $orga = $this->createOrga($this->makeNewCustomerCurrent());
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, $this->createCustomer());

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertTrue($result);
    }

    public function testPublicAgencyAcceptedOnlyInAnotherCustomerIsNoPublicAffairsAgent(): void
    {
        // Arrange
        $this->makeNewCustomerCurrent();
        $orga = $this->createOrga($this->createCustomer());

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertFalse($result);
    }

    public function testNotAcceptedPublicAgencyIsNoPublicAffairsAgent(): void
    {
        // Arrange
        $orga = $this->createOrga(
            $this->makeNewCustomerCurrent(),
            status: OrgaStatusInCustomerInterface::STATUS_REJECTED
        );

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertFalse($result);
    }

    public function testAcceptedOrgaOfAnotherTypeIsNoPublicAffairsAgent(): void
    {
        // Arrange
        $orga = $this->createOrga(
            $this->makeNewCustomerCurrent(),
            orgaTypeName: OrgaTypeInterface::MUNICIPALITY
        );

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertFalse($result);
    }

    public function testDeletedPublicAgencyWithoutUsersIsNoPublicAffairsAgent(): void
    {
        // Arrange
        $orga = $this->createOrga($this->makeNewCustomerCurrent(), deleted: true);

        // Act
        $result = $this->sut->isPublicAffairsAgent($this->reload($orga));

        // Assert
        self::assertFalse($result);
    }

    private function createCustomer(): Customer
    {
        return CustomerFactory::createOne(['subdomain' => 'agent-handler-'.uniqid()])->_real();
    }

    private function makeNewCustomerCurrent(): Customer
    {
        $customer = $this->createCustomer();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($customer->getSubdomain());

        return $customer;
    }

    /**
     * Creates an organisation without users, which has the given type and status in the given customer.
     */
    private function createOrga(
        Customer $customer,
        string $orgaTypeName = OrgaTypeInterface::PUBLIC_AGENCY,
        string $status = OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        bool $deleted = false,
    ): Orga {
        $orga = OrgaFactory::createOne(['deleted' => $deleted, 'showlist' => true])->_real();
        OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $customer,
            'orgaType' => OrgaTypeFactory::createOne(['name' => $orgaTypeName])->_real(),
            'status'   => $status,
            'showlist' => true,
        ]);

        return $orga;
    }

    /**
     * Adds a new user with the given role, held in the given customer, to the organisation.
     */
    private function addUser(Orga $orga, string $roleCode, Customer $roleCustomer): void
    {
        $role = $this->getContainer()->get(RoleHandler::class)->getUserRolesByCodes([$roleCode])[0];
        $user = UserFactory::createOne(['deleted' => false])->_real();
        $orga->addUser($user);
        $user->addDplanrole($role, $roleCustomer);
        $this->getEntityManager()->flush();
    }

    /**
     * Loads the organisation anew, so that its relations are those stored in the database.
     */
    private function reload(Orga $orga): Orga
    {
        $id = $orga->getId();
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->find(Orga::class, $id);
    }
}
