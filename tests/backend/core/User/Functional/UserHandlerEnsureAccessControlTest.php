<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaStatusInCustomerInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\OrgaTypeInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Logic\Permission\AccessControlService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use Tests\Base\UnitTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class UserHandlerEnsureAccessControlTest extends UnitTestCase
{
    /** @var UserHandler|null */
    protected $sut;

    private ?AccessControlService $accessControlService = null;

    private Orga|Proxy|null $orga = null;

    private Customer|Proxy|null $customer = null;

    private OrgaStatusInCustomer|Proxy|null $statusInCustomer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(UserHandler::class);
        $this->accessControlService = $this->getContainer()->get(AccessControlService::class);

        $orgaType = OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::PLANNING_AGENCY]);
        $this->orga = OrgaFactory::createOne();
        $this->customer = CustomerFactory::createOne(['subdomain' => 'ensure-access-control-test']);

        $this->statusInCustomer = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $this->orga->_real(),
            'customer' => $this->customer->_real(),
            'orgaType' => $orgaType->_real(),
            'status'   => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        ]);
        $this->orga->addStatusInCustomer($this->statusInCustomer->_real());
        $this->orga->_save();
    }

    public function testGrantsForAcceptedTypeWithoutSnapshot(): void
    {
        $this->sut->ensureAccessControl($this->orga->_real(), $this->customer->_real());

        self::assertTrue($this->hasOrgaWideGrant());
    }

    public function testSkipsCustomerThatWasAlreadyAcceptedBeforeChange(): void
    {
        // Arrange: type was accepted before, admin revoked the grant, then an unrelated save happens
        $acceptedBefore = $this->sut->getAcceptedCustomerIdsByProcedureCreationType($this->orga->_real());

        // Act
        $this->sut->ensureAccessControl($this->orga->_real(), $this->customer->_real(), $acceptedBefore);

        // Assert
        self::assertFalse($this->hasOrgaWideGrant());
    }

    public function testGrantsWhenTypeBecomesAcceptedAfterSnapshot(): void
    {
        // Arrange: PENDING → snapshot → ACCEPTED, mirrors the approval flow in updateOrga()
        $this->statusInCustomer->setStatus(OrgaStatusInCustomerInterface::STATUS_PENDING);
        $this->statusInCustomer->_save();
        $acceptedBefore = $this->sut->getAcceptedCustomerIdsByProcedureCreationType($this->orga->_real());

        $this->statusInCustomer->setStatus(OrgaStatusInCustomerInterface::STATUS_ACCEPTED);
        $this->statusInCustomer->_save();

        // Act
        $this->sut->ensureAccessControl($this->orga->_real(), $this->customer->_real(), $acceptedBefore);

        // Assert
        self::assertTrue($this->hasOrgaWideGrant());
    }

    public function testSecondTypeInheritsDisabledOrgaWideGrant(): void
    {
        // Arrange: planning agency accepted with the grant revoked, then hearing authority gets accepted
        $acceptedBefore = $this->sut->getAcceptedCustomerIdsByProcedureCreationType($this->orga->_real());
        $this->acceptOrgaAsType(OrgaTypeInterface::HEARING_AUTHORITY_AGENCY);

        // Act
        $this->sut->ensureAccessControl($this->orga->_real(), $this->customer->_real(), $acceptedBefore);

        // Assert
        self::assertFalse($this->hasOrgaWideGrant());
        self::assertFalse($this->hasOrgaWideGrant(RoleInterface::HEARING_AUTHORITY_ADMIN));
    }

    public function testSecondTypeInheritsEnabledOrgaWideGrant(): void
    {
        // Arrange: planning agency accepted and granted, then hearing authority gets accepted
        $this->sut->ensureAccessControl($this->orga->_real(), $this->customer->_real());
        $acceptedBefore = $this->sut->getAcceptedCustomerIdsByProcedureCreationType($this->orga->_real());
        $this->acceptOrgaAsType(OrgaTypeInterface::HEARING_AUTHORITY_AGENCY);

        // Act
        $this->sut->ensureAccessControl($this->orga->_real(), $this->customer->_real(), $acceptedBefore);

        // Assert
        self::assertTrue($this->hasOrgaWideGrant());
        self::assertTrue($this->hasOrgaWideGrant(RoleInterface::HEARING_AUTHORITY_ADMIN));
    }

    public function testSnapshotListsAcceptedCustomersPerProcedureCreationType(): void
    {
        $snapshot = $this->sut->getAcceptedCustomerIdsByProcedureCreationType($this->orga->_real());

        self::assertSame([$this->customer->getId()], $snapshot[OrgaTypeInterface::PLANNING_AGENCY]);
        self::assertSame([], $snapshot[OrgaTypeInterface::MUNICIPALITY]);
        self::assertSame([], $snapshot[OrgaTypeInterface::HEARING_AUTHORITY_AGENCY]);
    }

    private function acceptOrgaAsType(string $orgaTypeName): void
    {
        $orgaType = OrgaTypeFactory::createOne(['name' => $orgaTypeName]);
        $status = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $this->orga->_real(),
            'customer' => $this->customer->_real(),
            'orgaType' => $orgaType->_real(),
            'status'   => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        ]);
        $this->orga->addStatusInCustomer($status->_real());
        $this->orga->_save();
    }

    private function hasOrgaWideGrant(string $roleCode = RoleInterface::PRIVATE_PLANNING_AGENCY): bool
    {
        return $this->accessControlService->permissionExist(
            AccessControlService::CREATE_PROCEDURES_PERMISSION,
            $this->orga->_real(),
            $this->customer->_real(),
            [$roleCode]
        );
    }
}
