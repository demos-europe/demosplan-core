<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User;

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
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;

trait PublicAgencyOrgaTestTrait
{
    /**
     * Creates an organisation which is accepted as public agency in the given customer, but whose only
     * user has the planning agency admin role (RMOPSA) and thus no institution role.
     */
    protected function createPublicAgencyWithOnlyPlanningAgencyAdminUser(?Customer $customer = null): Orga
    {
        $customer ??= CustomerFactory::createOne(['subdomain' => 'customer-'.uniqid()])->_real();

        $orga = OrgaFactory::createOne(['deleted' => false, 'showlist' => true]);
        $statusInCustomer = OrgaStatusInCustomerFactory::createOne([
            'orga'     => $orga,
            'customer' => $customer,
            'orgaType' => OrgaTypeFactory::createOne(['name' => OrgaTypeInterface::PUBLIC_AGENCY]),
            'status'   => OrgaStatusInCustomerInterface::STATUS_ACCEPTED,
            'showlist' => true,
        ]);
        $realOrga = $orga->_real();
        $realOrga->addStatusInCustomer($statusInCustomer->_real());

        $planningAgencyAdminRole = $this->getContainer()->get(RoleHandler::class)
            ->getUserRolesByCodes([RoleInterface::PLANNING_AGENCY_ADMIN])[0];
        $user = UserFactory::createOne(['deleted' => false]);
        $realOrga->addUser($user->_real());
        $user->_real()->addDplanrole($planningAgencyAdminRole, $customer);

        $user->_save();
        $orga->_save();

        return $realOrga;
    }
}
