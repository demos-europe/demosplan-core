<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Project\AccessControlPermission\Unit;

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControlPermission;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlPermissionService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Tests\Base\UnitTestCase;
use Zenstruck\Foundry\Proxy;

class AccessControlPermissionServiceTest extends UnitTestCase
{

    /**
     * @var AccessControlPermissionService|null
     */
    protected $sut;
    protected null|RoleHandler|Proxy $roleHandler;

    private null|Orga|Proxy $testOrga;

    private null|Role|Proxy $testRole;

    private null|Customer|Proxy $testCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(AccessControlPermissionService::class);

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);

        $this->testRole =  $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];
        $this->testOrga = OrgaFactory::createOne();
        $this->testCustomer = CustomerFactory::createOne();
    }

    public function testCreatePermission(): void
    {
        // Arrange
        $permissionToCheck = 'my_permission';

        // Act
        $accessControlPermission = $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);

        // Assert
        self::assertInstanceOf(AccessControlPermission::class, $accessControlPermission);
        self::assertEquals($permissionToCheck, $accessControlPermission->getPermissionName());

    }


    /**
     *
     * The purpose of this test is to ensure that when a permission is created with a null role,
     * it is granted to all roles.
     */
    public function testCreatePermissionForOrgaCustomer(): void
    {
        // Arrange
        $permissionToCheck = 'my_permission';

        // Act
        $accessControlPermission = $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), null);

        // Assert
        self::assertInstanceOf(AccessControlPermission::class, $accessControlPermission);
        self::assertEquals($permissionToCheck, $accessControlPermission->getPermissionName());

        // Act
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), [RoleInterface::PRIVATE_PLANNING_AGENCY]);

        // Assert
        $this->assertIsArray($permissions);
        $this->assertCount(1, $permissions);

        // Act
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), [RoleInterface::GUEST]);

        // Assert
        $this->assertIsArray($permissions);
        $this->assertCount(1, $permissions);

    }

    public function testDuplicatePermissionCreationThrowsException(): void
    {
        $permissionToCheck = 'my_permission';
        $this->expectException(UniqueConstraintViolationException::class);
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
    }

    public function testGetPermissions(): void
    {
        // Arrange
        $permissionToCheckA = 'my_permission_a';
        $permissionToCheckB = 'my_permission_b';
        $roleCodes = [RoleInterface::PRIVATE_PLANNING_AGENCY];

        // Act
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        $this->assertEmpty($permissions);

        // Arrange
        $this->sut->createPermission($permissionToCheckA, $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
        $this->sut->createPermission($permissionToCheckB, $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);

        // Act
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), [RoleInterface::PRIVATE_PLANNING_AGENCY]);

        // Assert
        $this->assertIsArray($permissions);
        $this->assertCount(2, $permissions);
    }

    public function testHasPermission(): void
    {
        // Arrange
        $permissionToCheck = 'my_permission';
        $roleCodes = [RoleInterface::PRIVATE_PLANNING_AGENCY];

        // Act
        $hasPermission = $this->sut->hasPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        $this->assertFalse($hasPermission);


        // Arrange
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);

        // Act
        $hasPermission = $this->sut->hasPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        $this->assertTrue($hasPermission);

    }

}
