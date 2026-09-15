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
use demosplan\DemosPlanCoreBundle\Controller\User\DemosPlanOrganisationAPIController;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaTypeFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use ReflectionMethod;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Persistence\Proxy;

class DemosPlanOrganisationAPIControllerTest extends FunctionalTestCase
{
    protected ?DemosPlanOrganisationAPIController $sut = null;

    private ?ReflectionMethod $getAvailableOrgaRoles = null;

    private Customer|Proxy|null $customer = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(DemosPlanOrganisationAPIController::class);

        $this->getAvailableOrgaRoles = new ReflectionMethod($this->sut, 'getAvailableOrgaRoles');
        $this->getAvailableOrgaRoles->setAccessible(true);

        $this->customer = CustomerFactory::createOne(['subdomain' => 'organisation-api-test-'.uniqid()]);
    }

    public function testIncludesPrivatePlanningAgencyForAcceptedPlanningAgency(): void
    {
        $orga = $this->acceptOrgaAsType(OrgaTypeInterface::PLANNING_AGENCY);

        self::assertContainsRoleCode(RoleInterface::PRIVATE_PLANNING_AGENCY, $this->invokeGetAvailableOrgaRoles($orga));
    }

    public function testIncludesPlanningAgencyAdminForAcceptedMunicipality(): void
    {
        $orga = $this->acceptOrgaAsType(OrgaTypeInterface::MUNICIPALITY);

        self::assertContainsRoleCode(RoleInterface::PLANNING_AGENCY_ADMIN, $this->invokeGetAvailableOrgaRoles($orga));
    }

    public function testIncludesHearingAuthorityAdminForAcceptedHearingAuthorityAgency(): void
    {
        $orga = $this->acceptOrgaAsType(OrgaTypeInterface::HEARING_AUTHORITY_AGENCY);

        self::assertContainsRoleCode(RoleInterface::HEARING_AUTHORITY_ADMIN, $this->invokeGetAvailableOrgaRoles($orga));
    }

    private static function assertContainsRoleCode(string $roleCode, array $roles): void
    {
        $roleCodes = array_map(static fn (Role $role): string => $role->getCode(), $roles);
        self::assertContains($roleCode, $roleCodes);
    }

    private function invokeGetAvailableOrgaRoles(Orga $orga): array
    {
        return $this->getAvailableOrgaRoles->invoke($this->sut, $orga);
    }

    private function acceptOrgaAsType(string $orgaTypeName): Orga
    {
        $orgaType = OrgaTypeFactory::createOne(['name' => $orgaTypeName]);
        $orga = OrgaFactory::createOne();
        $status = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga->_real(),
            'customer' => $this->customer->_real(),
            'orgaType' => $orgaType->_real(),
            'status'   => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
        ]);
        $orga->addStatusInCustomer($status->_real());
        $orga->_save();

        return $orga->_real();
    }
}
