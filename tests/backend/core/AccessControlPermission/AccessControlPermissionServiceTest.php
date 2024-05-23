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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\RoleFactory;
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
    protected RoleHandler|Proxy|null $roleHandler;

    private Orga|Proxy|null $testOrga;

    private Role|Proxy|null $testRole;

    private Customer|Proxy|null $testCustomer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(AccessControlPermissionService::class);

        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);

        $this->testRole = $this->roleHandler->getUserRolesByCodes([RoleInterface::PRIVATE_PLANNING_AGENCY])[0];

        if (null === $this->testRole) {
            $this->testRole = RoleFactory::createOne();
            $this->testRole->setCode(RoleInterface::PRIVATE_PLANNING_AGENCY);
            $this->testRole->save();
        }

        $this->testOrga = OrgaFactory::createOne();
        $this->testCustomer = CustomerFactory::createOne();
    }

    public function testCreatePermission(): void
    {
        $accessControlPermission = $this->sut->createPermission('my_permission', $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
        self::assertInstanceOf(AccessControlPermission::class, $accessControlPermission);
        self::assertEquals('my_permission', $accessControlPermission->getPermissionName());
    }

    public function testDuplicatePermissionCreationThrowsException(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->sut->createPermission('my_permission', $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
        $this->sut->createPermission('my_permission', $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
    }

    public function testGetPermissions(): void
    {
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), [RoleInterface::PRIVATE_PLANNING_AGENCY]);
        $this->assertEmpty($permissions);

        $this->sut->createPermission('my_permission', $this->testOrga->object(), $this->testCustomer->object(), $this->testRole);
        $permissions = $this->sut->getPermissions($this->testOrga->object(), $this->testCustomer->object(), [RoleInterface::PRIVATE_PLANNING_AGENCY]);
        $this->assertCount(1, $permissions);
    }
}
