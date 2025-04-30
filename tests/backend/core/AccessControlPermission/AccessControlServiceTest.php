<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\AccessControlPermission;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\Entity\Permission\AccessControl;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use Tests\Base\UnitTestCase;
use Zenstruck\Foundry\Proxy;

class AccessControlServiceTest extends UnitTestCase
{
    /**
     * @var AccessControlService|null
     */
    protected $sut;

    protected RoleHandler|Proxy|null $roleHandler;

    protected GlobalConfig|Proxy|null $globalConfig;

    private Orga|Proxy|null $testOrga;

    private Role|Proxy|null $testRole;

    private Customer|Proxy|null $testCustomer;

    private OrgaType|Proxy|null $testOrgaType;

    private OrgaStatusInCustomer|Proxy|null $testOrgaStatusInCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(AccessControlService::class);

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);
        $this->globalConfig = $this->getContainer()->get(GlobalConfig::class);

        $this->testRole = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];

        $this->testOrgaType = OrgaTypeFactory::createOne();
        $this->testOrgaType->setName(OrgaTypeInterface::PLANNING_AGENCY);
        $this->testOrgaType->save();

        $this->testOrga = OrgaFactory::createOne();
        $this->testCustomer = CustomerFactory::createOne();
        $this->testCustomer->setSubdomain('bb');
        $this->testCustomer->save();

        $this->testOrgaStatusInCustomer = OrgaStatusInCustomerFactory::createOne();

        $this->testOrgaStatusInCustomer->setOrga($this->testOrga->object());
        $this->testOrgaStatusInCustomer->save();

        $this->testOrgaStatusInCustomer->setCustomer($this->testCustomer->object());
        $this->testOrgaStatusInCustomer->save();

        $this->testOrgaStatusInCustomer->setOrgaType($this->testOrgaType->object());
        $this->testOrgaStatusInCustomer->save();

        $this->testOrgaStatusInCustomer->setStatus(OrgaStatusInCustomerInterface::STATUS_ACCEPTED);
        $this->testOrgaStatusInCustomer->save();

        $this->testOrga->addStatusInCustomer($this->testOrgaStatusInCustomer->object());
        $this->testOrga->save();
    }

    public function testCreatePermission(): void
    {
        // Arrange
        $permissionToCheck = 'my_permission';

        // Act
        $accessControlPermission = $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);

        // Assert
        self::assertInstanceOf(AccessControl::class, $accessControlPermission);
        self::assertSame($permissionToCheck, $accessControlPermission->getPermissionName());
    }

    public function testDuplicatePermissionCreationThrowsException(): void
    {
        $permissionToCheck = 'my_permission';
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);
        $createdPermission = $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);
        $this->assertNull($createdPermission);
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
        self::assertEmpty($permissions);

        // Arrange
        $this->sut->createPermission($permissionToCheckA, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);
        $this->sut->createPermission($permissionToCheckB, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);

        // Act
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), [RoleInterface::PRIVATE_PLANNING_AGENCY]);

        // Assert
        self::assertIsArray($permissions);
        self::assertCount(2, $permissions);
    }

    public function testHasPermission(): void
    {
        // Arrange
        $permissionToCheck = 'my_permission';
        $roleCodes = [RoleInterface::PRIVATE_PLANNING_AGENCY];

        // Act
        $hasPermission = $this->sut->permissionExist($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        self::assertFalse($hasPermission);

        // Arrange
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);

        // Act
        $hasPermission = $this->sut->permissionExist($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        self::assertTrue($hasPermission);
    }

    public function testDoesNotHavePermissionCreateProceduresPermission(): void
    {
        // Arrange
        $permissionToCheck = AccessControlService::CREATE_PROCEDURES_PERMISSION;
        $roleCodes = [RoleInterface::PRIVATE_PLANNING_AGENCY];
        $this->testOrgaType->setName(OrgaTypeInterface::PUBLIC_AGENCY);
        $this->testOrgaType->save();

        $this->testOrgaStatusInCustomer->setOrgaType($this->testOrgaType->object());
        $this->testOrgaStatusInCustomer->save();

        // Act
        $hasPermission = $this->sut->permissionExist($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        self::assertFalse($hasPermission);

        // Arrange
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);

        // Act
        $hasPermission = $this->sut->checkPermissionForOrgaType($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object());

        // Assert
        self::assertFalse($hasPermission);
    }

    public function testHasPermissionCreateProceduresPermission(): void
    {
        // Arrange
        $permissionToCheck = AccessControlService::CREATE_PROCEDURES_PERMISSION;
        $roleCodes = [RoleInterface::PRIVATE_PLANNING_AGENCY];

        // Act
        $hasPermission = $this->sut->permissionExist($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        self::assertFalse($hasPermission);

        // Arrange
        $this->sut->createPermission($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), [$this->testRole]);

        // Act
        $hasPermission = $this->sut->permissionExist($permissionToCheck, $this->testOrga->object(), $this->testCustomer->object(), $roleCodes);

        // Assert
        self::assertTrue($hasPermission);
    }
}
