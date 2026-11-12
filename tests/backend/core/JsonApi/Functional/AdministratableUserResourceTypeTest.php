<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

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
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AdministratableUserResourceType;
use EDT\Wrapping\EntityData;
use ReflectionMethod;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class AdministratableUserResourceTypeTest extends FunctionalTestCase
{
    protected ?AdministratableUserResourceType $sut = null;

    private ?UserAccessControlService $userAccessControlService = null;

    private ?AccessControlService $accessControlService = null;

    private ?RoleHandler $roleHandler = null;

    private Customer|Proxy|null $customer = null;

    private Orga|Proxy|null $orga = null;

    private ?User $user = null;

    private ?RoleInterface $rmopsaRole = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(AdministratableUserResourceType::class);
        $this->userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);
        $this->accessControlService = $this->getContainer()->get(AccessControlService::class);
        $this->roleHandler = $this->getContainer()->get(RoleHandler::class);

        $this->customer = CustomerFactory::createOne(['subdomain' => 'administratable-user-test-'.uniqid()]);
        $this->makeCustomerCurrent($this->customer->_real());

        $orgaType = OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::MUNICIPALITY]);
        $this->orga = OrgaFactory::createOne();
        $status = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $this->orga->_real(),
            'customer' => $this->customer->_real(),
            'orgaType' => $orgaType->_real(),
            'status'   => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        ]);
        $this->orga->addStatusInCustomer($status->_real());
        $this->orga->_save();

        $this->rmopsaRole = $this->roleHandler->getRoleByCode(RoleInterface::PLANNING_AGENCY_ADMIN);

        $this->user = UserFactory::createOne([
            'deleted' => false,
            'login'   => 'administratable-user-test-'.uniqid().'@example.com',
        ])->_real();
        $this->user->setOrga($this->orga->_real());
        $this->user->setCurrentCustomer($this->customer->_real());
        $this->user->addDplanrole($this->rmopsaRole, $this->customer->_real());
        $this->getEntityManager()->flush();

        $this->logIn($this->user);
        $this->enablePermissions(['feature_manage_user_procedure_creation_permission']);
    }

    public function testUpdateEntityGrantsCanManageProceduresWhenOrgaAccepted(): void
    {
        // Act
        $this->sut->updateEntity(
            $this->user->getId(),
            new EntityData('AdministratableUser', ['canManageProcedures' => true], [], [])
        );

        // Assert
        self::assertTrue($this->userPermissionExists($this->rmopsaRole));
    }

    public function testUpdateEntityDoesNotGrantWhenOrgaNotAcceptedForRole(): void
    {
        // Arrange: revoke the accepted status, so isOrgaAcceptedForRole() no longer holds
        $status = $this->orga->_real()->getStatusInCustomers()->first();
        $status->setStatus(OrgaStatusInCustomerInterface::STATUS_PENDING);
        $this->getEntityManager()->flush();

        // Act
        $this->sut->updateEntity(
            $this->user->getId(),
            new EntityData('AdministratableUser', ['canManageProcedures' => true], [], [])
        );

        // Assert
        self::assertFalse($this->userPermissionExists($this->rmopsaRole));
    }

    public function testUpdateEntityRevokesCanManageProcedures(): void
    {
        // Arrange
        $this->userAccessControlService->createUserPermission(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->rmopsaRole
        );

        // Act
        $this->sut->updateEntity(
            $this->user->getId(),
            new EntityData('AdministratableUser', ['canManageProcedures' => false], [], [])
        );

        // Assert
        self::assertFalse($this->userPermissionExists($this->rmopsaRole));
    }

    public function testUpdateEntitySkipsIndividualGrantWhenOrgaWideGrantAlreadyExists(): void
    {
        // Arrange: the organisation already grants procedure creation org-wide for RMOPSA
        $this->accessControlService->createPermission(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->orga->_real(),
            $this->customer->_real(),
            $this->rmopsaRole
        );

        // Act
        $this->sut->updateEntity(
            $this->user->getId(),
            new EntityData('AdministratableUser', ['canManageProcedures' => true], [], [])
        );

        // Assert: no individual grant was created since the org-wide one already covers it
        self::assertFalse($this->userPermissionExists($this->rmopsaRole));
    }

    public function testUpdateRolesRemovesStalePermissionWhenRoleRemoved(): void
    {
        // RMOPHA is registered as a role, but User::getDplanroles() additionally filters
        // by the active project's `roles_allowed` config, which not every project includes
        // HEARING_AUTHORITY_ADMIN in.
        $rolesAllowed = $this->getContainer()->get(GlobalConfigInterface::class)->getRolesAllowed();
        if (!in_array(RoleInterface::HEARING_AUTHORITY_ADMIN, $rolesAllowed, true)) {
            self::markTestSkipped('RMOPHA is not in roles_allowed for the currently active project.');
        }

        // Arrange: user also holds RMOPHA, individually granted, on top of RMOPSA
        $rmophaRole = $this->roleHandler->getRoleByCode(RoleInterface::HEARING_AUTHORITY_ADMIN);
        $this->user->addDplanrole($rmophaRole, $this->customer->_real());
        $this->getEntityManager()->flush();
        $this->userAccessControlService->createUserPermission(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $rmophaRole
        );

        // updateRoles() is only reachable via the EDT relationship pipeline in production, which
        // additionally requires resolving role ids through RoleResourceType (isGetAllowed() is
        // false there); invoke it directly to test the cleanup logic in isolation.
        $updateRoles = new ReflectionMethod($this->sut, 'updateRoles');
        $updateRoles->setAccessible(true);

        // Act: role set no longer includes RMOPHA
        $updateRoles->invoke($this->sut, $this->user, [$this->rmopsaRole]);
        $this->getEntityManager()->flush();

        // Assert
        self::assertFalse($this->userPermissionExists($rmophaRole));
    }

    private function userPermissionExists(RoleInterface $role): bool
    {
        return $this->userAccessControlService->userPermissionExists(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $role
        );
    }

    private function makeCustomerCurrent(Customer $customer): void
    {
        $globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);
        $globalConfig->setSubdomain($customer->getSubdomain());
    }
}
