<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\User;

use ApiPlatform\Doctrine\Orm\Util\QueryNameGenerator;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\User\ActiveInstitutionUsers;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Tests\Base\FunctionalTestCase;

/**
 * An organisation counts as having active institution users if at least one of its users is not deleted and
 * holds an institution role (RPSOCO or RPSODE) in the current customer.
 */
class ActiveInstitutionUsersTest extends FunctionalTestCase
{
    protected ?ActiveInstitutionUsers $sut = null;

    private ?Customer $currentCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();

        // built directly with its real dependencies, as nothing else uses the service yet
        $this->sut = new ActiveInstitutionUsers(
            $this->getEntityManager(),
            $this->getContainer()->get(CustomerService::class)
        );
        $this->currentCustomer = $this->createCustomer();
        $this->getContainer()->get(GlobalConfigInterface::class)->setSubdomain($this->currentCustomer->getSubdomain());
    }

    public function testFindIdsDoesNotReturnOrgaWithOnlyPlanningAgencyAdmin(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PLANNING_AGENCY_ADMIN);

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([], $result);
    }

    public function testFindIdsReturnsOrgaWithPublicAgencyWorker(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([$orga->getId()], $result);
    }

    public function testFindIdsReturnsOrgaWithPublicAgencyCoordination(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_COORDINATION);

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([$orga->getId()], $result);
    }

    public function testFindIdsDoesNotReturnOrgaWhoseInstitutionUserIsDeleted(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, deleted: true);

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([], $result);
    }

    public function testFindIdsReturnsOrgaWithOneActiveAndOneDeletedInstitutionUser(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, deleted: true);
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([$orga->getId()], $result);
    }

    public function testFindIdsDoesNotReturnOrgaWithInstitutionRoleInAnotherCustomer(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER, customer: $this->createCustomer());

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([], $result);
    }

    public function testFindIdsReturnsOrgaWithSeveralInstitutionUsersOnce(): void
    {
        // Arrange
        $orga = $this->createOrga();
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_WORKER);
        $this->addUser($orga, RoleInterface::PUBLIC_AGENCY_COORDINATION);

        // Act
        $result = $this->sut->findIds([$orga->getId()]);

        // Assert
        self::assertSame([$orga->getId()], $result);
    }

    public function testFindIdsReturnsOnlyRequestedIds(): void
    {
        // Arrange
        $requested = $this->createOrga();
        $this->addUser($requested, RoleInterface::PUBLIC_AGENCY_WORKER);
        $notRequested = $this->createOrga();
        $this->addUser($notRequested, RoleInterface::PUBLIC_AGENCY_WORKER);

        // Act
        $result = $this->sut->findIds([$requested->getId()]);

        // Assert
        self::assertSame([$requested->getId()], $result);
    }

    public function testFindIdsSeparatesOrgasWithAndWithoutInstitutionUsers(): void
    {
        // Arrange
        $withInstitutionUser = $this->createOrga();
        $this->addUser($withInstitutionUser, RoleInterface::PUBLIC_AGENCY_WORKER);
        $withoutInstitutionUser = $this->createOrga();
        $this->addUser($withoutInstitutionUser, RoleInterface::PLANNING_AGENCY_ADMIN);

        // Act
        $result = $this->sut->findIds([$withInstitutionUser->getId(), $withoutInstitutionUser->getId()]);

        // Assert
        self::assertSame([$withInstitutionUser->getId()], $result);
    }

    public function testFindIdsReturnsEmptyListForEmptyInput(): void
    {
        // Act
        $result = $this->sut->findIds([]);

        // Assert
        self::assertSame([], $result);
    }

    /**
     * The computed column (lists) and findIds() (single institution) are two ways to ask the same question.
     * They have to agree for every organisation, so the rule stays defined once.
     */
    public function testAddSelectFlagsTheSameOrgasAsFindIds(): void
    {
        // Arrange
        $withInstitutionUser = $this->createOrga();
        $this->addUser($withInstitutionUser, RoleInterface::PUBLIC_AGENCY_WORKER);
        $withPlanningAgencyAdminOnly = $this->createOrga();
        $this->addUser($withPlanningAgencyAdminOnly, RoleInterface::PLANNING_AGENCY_ADMIN);
        $withDeletedInstitutionUser = $this->createOrga();
        $this->addUser($withDeletedInstitutionUser, RoleInterface::PUBLIC_AGENCY_WORKER, deleted: true);
        $withRoleInAnotherCustomer = $this->createOrga();
        $this->addUser($withRoleInAnotherCustomer, RoleInterface::PUBLIC_AGENCY_COORDINATION, customer: $this->createCustomer());
        $queryBuilder = $this->getEntityManager()->createQueryBuilder()
            ->select('orga.id')
            ->from(Orga::class, 'orga');

        // Act
        $this->sut->addSelect($queryBuilder, new QueryNameGenerator());
        $rows = $queryBuilder->getQuery()->getResult();

        // Assert
        $allIds = array_column($rows, 'id');
        $flaggedIds = array_column(
            array_filter($rows, static fn (array $row): bool => (bool) $row[ActiveInstitutionUsers::DEFAULT_ALIAS]),
            'id'
        );
        $idsFromFindIds = $this->sut->findIds($allIds);
        sort($flaggedIds);
        sort($idsFromFindIds);
        self::assertSame($idsFromFindIds, $flaggedIds);
        self::assertContains($withInstitutionUser->getId(), $flaggedIds);
        self::assertNotContains($withPlanningAgencyAdminOnly->getId(), $flaggedIds);
        self::assertNotContains($withDeletedInstitutionUser->getId(), $flaggedIds);
        self::assertNotContains($withRoleInAnotherCustomer->getId(), $flaggedIds);
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
}
