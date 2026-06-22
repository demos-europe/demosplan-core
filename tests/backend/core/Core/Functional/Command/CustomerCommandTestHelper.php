<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional\Command;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaStatusInCustomer;
use demosplan\DemosPlanCoreBundle\Entity\User\OrgaType;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\MockObject\MockObject;

trait CustomerCommandTestHelper
{
    private function createOrga(string $name, string $id): MockObject&Orga
    {
        $orga = $this->createMock(Orga::class);
        $orga->method('getName')->willReturn($name);
        $orga->method('getId')->willReturn($id);

        return $orga;
    }

    private function createOrgaType(string $name): MockObject&OrgaType
    {
        $orgaType = $this->createMock(OrgaType::class);
        $orgaType->method('getLabel')->willReturn($name);

        return $orgaType;
    }

    private function createOrgaStatusInCustomer(
        MockObject&Orga $orga,
        MockObject&OrgaType $orgaType,
        string $status,
    ): MockObject&OrgaStatusInCustomer {
        $orgaStatus = $this->createMock(OrgaStatusInCustomer::class);
        $orgaStatus->method('getOrga')->willReturn($orga);
        $orgaStatus->method('getOrgaType')->willReturn($orgaType);
        $orgaStatus->method('getStatus')->willReturn($status);

        return $orgaStatus;
    }

    private function createCustomerWithOrgaStatuses(
        string $name,
        string $subdomain,
        string $id,
        array $orgaStatuses,
    ): MockObject&Customer {
        $customer = $this->createMock(Customer::class);
        $customer->method('getName')->willReturn($name);
        $customer->method('getSubdomain')->willReturn($subdomain);
        $customer->method('getId')->willReturn($id);
        $customer->method('getOrgaStatuses')->willReturn(new ArrayCollection($orgaStatuses));

        return $customer;
    }
}
