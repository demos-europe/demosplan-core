<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Entity;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Tests\Base\FunctionalTestCase;

/**
 * An organisation has active institution users if at least one of its users is not deleted and holds an
 * institution role (RPSOCO or RPSODE) in the current customer.
 *
 * The roles of a user are only resolved for the current customer after the user was loaded from the
 * database, so every case reloads the organisation before it asks.
 */
class OrgaHasActiveInstitutionUsersTest extends FunctionalTestCase
{
    private ?Customer $currentCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->currentCustomer = $this->createCustomer();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
    }

    public function testIsFalseForOrgaWithoutUsers(): void
    {
        // Arrange
        $orga = $this->createOrga();

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertFalse($result);
    }

    public function testIsFalseForOrgaWithOnlyPlanningAgencyAdmin(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PLANNING_AGENCY_ADMIN);

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertFalse($result);
    }

    public function testIsTrueForPublicAgencyWorker(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertTrue($result);
    }

    public function testIsTrueForPublicAgencyCoordination(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_COORDINATION);

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertTrue($result);
    }

    public function testIsFalseIfTheInstitutionUserIsDeleted(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, deleted: true);

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertFalse($result);
    }

    public function testIsTrueWithOneActiveAndOneDeletedInstitutionUser(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, deleted: true);
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertTrue($result);
    }

    public function testIsFalseIfTheInstitutionRoleIsHeldInAnotherCustomer(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, customer: $this->createCustomer());

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertFalse($result);
    }

    public function testIsTrueIfAnotherUserHoldsTheInstitutionRole(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PLANNING_AGENCY_ADMIN);
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $result = $this->reload($orga)->hasActiveInstitutionUsers();

        // Assert
        self::assertTrue($result);
    }

    private function createCustomer(): Customer
    {
        return CustomerFactory::createOne(['subdomain' => 'active-users-'.uniqid()])->_real();
    }

    private function createOrga(): Orga
    {
        return OrgaFactory::createOne(['deleted' => false, 'showlist' => true])->_real();
    }

    /**
     * Adds a new user with the given role to the organisation. The role is held in the current
     * customer unless another customer is given.
     */
    private function addUser(Orga $orga, string $roleCode, bool $deleted = false, ?Customer $customer = null): void
    {
        $role = $this->getContainer()->get(RoleHandler::class)->getUserRolesByCodes([$roleCode])[0];
        $user = UserFactory::createOne(['deleted' => $deleted])->_real();
        $orga->addUser($user);
        $user->addDplanrole($role, $customer ?? $this->currentCustomer);
        $this->getEntityManager()->flush();
    }

    private function reload(Orga $orga): Orga
    {
        $id = $orga->getId();
        $this->getEntityManager()->clear();

        return $this->getEntityManager()->find(Orga::class, $id);
    }
}
