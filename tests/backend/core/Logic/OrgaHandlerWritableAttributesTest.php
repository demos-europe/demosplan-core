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

use DemosEurope\DemosplanAddon\Contracts\Entities\RoleInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Orga\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\RoleHandler;
use Tests\Base\FunctionalTestCase;

class OrgaHandlerWritableAttributesTest extends FunctionalTestCase
{
    protected ?OrgaHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(OrgaHandler::class);

        $role = self::getContainer()->get(RoleHandler::class)->getRoleByCode(RoleInterface::CUSTOMER_MASTER_USER);
        $orga = OrgaFactory::createOne()->_real();
        $customerMasterUser = UserFactory::createOne()->_real();
        self::assertInstanceOf(Orga::class, $orga);
        self::assertInstanceOf(User::class, $customerMasterUser);
        $customerMasterUser->setOrga($orga);
        $customerMasterUser->addDplanrole($role);
        $orga->addUser($customerMasterUser);

        $this->logIn($customerMasterUser);
    }

    public function testRegistrationStatusesNotWritableWithoutPermission(): void
    {
        $this->enablePermissions(['feature_orga_edit_all_fields']);

        $writableAttributes = $this->sut->getWritableAttributes();

        self::assertArrayNotHasKey('registrationStatuses', $writableAttributes);
    }

    public function testRegistrationStatusesWritableWithRegistrationStatusEditPermission(): void
    {
        $this->enablePermissions(['feature_orga_registration_status_edit']);

        self::assertArrayHasKey('registrationStatuses', $this->sut->getWritableAttributes());
    }

    public function testRegistrationStatusesNotWritableWithApplicationsManagePermissionOnly(): void
    {
        $this->enablePermissions(['area_organisations_applications_manage']);

        self::assertArrayNotHasKey('registrationStatuses', $this->sut->getWritableAttributes());
    }
}
