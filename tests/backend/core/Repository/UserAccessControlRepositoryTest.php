<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Repository;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\UserAccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Repository\UserAccessControlRepository;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class UserAccessControlRepositoryTest extends FunctionalTestCase
{
    /**
     * @var UserAccessControlRepository|null
     */
    protected $sut;

    private User|Proxy|null $testUser;
    private User|Proxy|null $testUser2;
    private Orga|Proxy|null $testOrga;
    private Customer|Proxy|null $testCustomer;
    private ?Role $testRole;
    private ?RoleHandler $roleHandler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getEntityManager()->getRepository(UserAccessControl::class);
        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->testRole = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];

        $this->testCustomer = CustomerFactory::createOne();
        $this->testOrga = OrgaFactory::createOne();
        $this->testUser = UserFactory::createOne();
        $this->testUser2 = UserFactory::createOne();
    }

    public function testFindPermissionsByUserAndRoles(): void
    {
        // Arrange
        $permission1 = 'feature_statement_bulk_edit';
        $permission2 = 'feature_procedure_planning_area_match';

        $userAccessControl1 = new UserAccessControl();
        $userAccessControl1->setUser($this->testUser->_real());
        $userAccessControl1->setOrganisation($this->testOrga->_real());
        $userAccessControl1->setCustomer($this->testCustomer->_real());
        $userAccessControl1->setRole($this->testRole);
        $userAccessControl1->setPermission($permission1);

        $userAccessControl2 = new UserAccessControl();
        $userAccessControl2->setUser($this->testUser->_real());
        $userAccessControl2->setOrganisation($this->testOrga->_real());
        $userAccessControl2->setCustomer($this->testCustomer->_real());
        $userAccessControl2->setRole($this->testRole);
        $userAccessControl2->setPermission($permission2);

        $this->getEntityManager()->persist($userAccessControl1);
        $this->getEntityManager()->persist($userAccessControl2);
        $this->getEntityManager()->flush();

        // Act
        $permissions = $this->sut->getPermissionsByUserAndRoles(
            $this->testUser->_real(),
            $this->testOrga->_real(),
            $this->testCustomer->_real(),
            [$this->testRole]
        );

        // Assert
        self::assertCount(2, $permissions);
        self::assertContainsOnlyInstancesOf(UserAccessControl::class, $permissions);

        $permissionNames = array_map(fn ($p) => $p->getPermission(), $permissions);
        self::assertContains($permission1, $permissionNames);
        self::assertContains($permission2, $permissionNames);
    }

    public function testFindPermissionsByUserRespectsOrganizationBoundaries(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';
        $differentOrga = OrgaFactory::createOne();

        $userAccessControl = new UserAccessControl();
        $userAccessControl->setUser($this->testUser->_real());
        $userAccessControl->setOrganisation($differentOrga->_real());
        $userAccessControl->setCustomer($this->testCustomer->_real());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission($permission);

        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();

        // Act - Query for different organization
        $permissions = $this->sut->getPermissionsByUserAndRoles(
            $this->testUser->_real(),
            $this->testOrga->_real(), // Different org than the permission
            $this->testCustomer->_real(),
            [$this->testRole]
        );

        // Assert - Should be empty due to organization boundary
        self::assertEmpty($permissions);
    }

    public function testFindPermissionsByUserHandlesCustomerFiltering(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';
        $differentCustomer = CustomerFactory::createOne();

        $userAccessControl = new UserAccessControl();
        $userAccessControl->setUser($this->testUser->_real());
        $userAccessControl->setOrganisation($this->testOrga->_real());
        $userAccessControl->setCustomer($differentCustomer->_real());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission($permission);

        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();

        // Act - Query for different customer
        $permissions = $this->sut->getPermissionsByUserAndRoles(
            $this->testUser->_real(),
            $this->testOrga->_real(),
            $this->testCustomer->_real(), // Different customer than the permission
            [$this->testRole]
        );

        // Assert - Should be empty due to customer boundary
        self::assertEmpty($permissions);
    }

    public function testFindByUser(): void
    {
        // Arrange
        $permission1 = 'feature_statement_bulk_edit';
        $permission2 = 'feature_procedure_planning_area_match';

        $userAccessControl1 = new UserAccessControl();
        $userAccessControl1->setUser($this->testUser->_real());
        $userAccessControl1->setOrganisation($this->testOrga->_real());
        $userAccessControl1->setCustomer($this->testCustomer->_real());
        $userAccessControl1->setRole($this->testRole);
        $userAccessControl1->setPermission($permission1);

        $userAccessControl2 = new UserAccessControl();
        $userAccessControl2->setUser($this->testUser2->_real()); // Different user
        $userAccessControl2->setOrganisation($this->testOrga->_real());
        $userAccessControl2->setCustomer($this->testCustomer->_real());
        $userAccessControl2->setRole($this->testRole);
        $userAccessControl2->setPermission($permission2);

        $this->getEntityManager()->persist($userAccessControl1);
        $this->getEntityManager()->persist($userAccessControl2);
        $this->getEntityManager()->flush();

        // Act
        $permissions = $this->sut->findByUser($this->testUser->_real());

        // Assert - Should only return permissions for testUser
        self::assertCount(1, $permissions);
        self::assertSame($permission1, $permissions[0]->getPermission());
        self::assertSame($this->testUser->_real(), $permissions[0]->getUser());
    }

    public function testPermissionExists(): void
    {
        // Arrange
        $permission = 'feature_statement_bulk_edit';

        $userAccessControl = new UserAccessControl();
        $userAccessControl->setUser($this->testUser->_real());
        $userAccessControl->setOrganisation($this->testOrga->_real());
        $userAccessControl->setCustomer($this->testCustomer->_real());
        $userAccessControl->setRole($this->testRole);
        $userAccessControl->setPermission($permission);

        $this->getEntityManager()->persist($userAccessControl);
        $this->getEntityManager()->flush();

        // Act & Assert
        self::assertTrue($this->sut->permissionExists(
            $permission,
            $this->testUser->_real(),
            $this->testOrga->_real(),
            $this->testCustomer->_real(),
            $this->testRole
        ));

        self::assertFalse($this->sut->permissionExists(
            'non_existent_permission',
            $this->testUser->_real(),
            $this->testOrga->_real(),
            $this->testCustomer->_real(),
            $this->testRole
        ));
    }
}
