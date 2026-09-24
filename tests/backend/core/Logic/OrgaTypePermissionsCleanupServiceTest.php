<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\Permission\UserAccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaTypePermissionsCleanupService;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Tests\Base\FunctionalTestCase;

class OrgaTypePermissionsCleanupServiceTest extends FunctionalTestCase
{
    protected ?OrgaTypePermissionsCleanupService $sut = null;

    private ?AccessControlService $accessControlService = null;
    private ?UserAccessControlService $userAccessControlService = null;
    private ?Orga $orga = null;
    private ?User $user = null;
    private ?Customer $customer = null;
    private ?Role $municipalityRole = null;
    private ?Role $planningAgencyRole = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(OrgaTypePermissionsCleanupService::class);
        $this->accessControlService = $this->getContainer()->get(AccessControlService::class);
        $this->userAccessControlService = $this->getContainer()->get(UserAccessControlService::class);

        $roleHandler = $this->getContainer()->get(RoleHandler::class);
        // RMOPPO instead of RMOPHA as the second role: not every project lists RMOPHA in roles_allowed
        $this->municipalityRole = $roleHandler->getRoleByCode(RoleInterface::PLANNING_AGENCY_ADMIN);
        $this->planningAgencyRole = $roleHandler->getRoleByCode(RoleInterface::PRIVATE_PLANNING_AGENCY);

        $this->orga = OrgaFactory::createOne()->_real();
        $this->user = UserFactory::createOne()->_real();
        $this->user->setOrga($this->orga);
        $this->user->addDplanRole($this->municipalityRole);
        $this->user->addDplanRole($this->planningAgencyRole);
        $this->orga->addUser($this->user);
        $this->getEntityManager()->flush();

        // Grants are stored under the customer resolved for the user, so the cleanup must target that one
        $customer = $this->user->getCurrentCustomer();
        self::assertInstanceOf(Customer::class, $customer);
        $this->customer = $customer;
    }

    public function testRemovesOrgaWideAndIndividualGrantsOfRemovedType(): void
    {
        $this->accessControlService->createPermissions(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->orga,
            $this->customer,
            [$this->municipalityRole]
        );
        $this->userAccessControlService->createUserPermission(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->municipalityRole
        );

        $this->sut->cleanupPermissionsForRemovedType(OrgaTypeInterface::MUNICIPALITY, $this->orga, $this->customer);

        self::assertFalse($this->accessControlService->permissionExist(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->orga,
            $this->customer,
            [RoleInterface::PLANNING_AGENCY_ADMIN]
        ));
        self::assertFalse($this->userAccessControlService->userPermissionExists(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->municipalityRole
        ));
    }

    public function testLeavesIndividualGrantsOfOtherTypesUntouched(): void
    {
        $this->userAccessControlService->createUserPermission(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->municipalityRole
        );
        $this->userAccessControlService->createUserPermission(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->planningAgencyRole
        );

        $this->sut->cleanupPermissionsForRemovedType(OrgaTypeInterface::MUNICIPALITY, $this->orga, $this->customer);

        self::assertFalse($this->userAccessControlService->userPermissionExists(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->municipalityRole
        ));
        self::assertTrue($this->userAccessControlService->userPermissionExists(
            $this->user,
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->planningAgencyRole
        ));
    }
}
