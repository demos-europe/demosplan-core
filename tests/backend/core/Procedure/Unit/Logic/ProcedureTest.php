<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use Exception;
use Tests\Base\FunctionalTestCase;

/**
 * Class ProcedureTest.
 *
 * @group UnitTest
 */
class ProcedureTest extends FunctionalTestCase
{
    /**
     * @var UserHandler
     */
    protected $procedureHandler;

    /**
     * @var UserHandler
     */
    protected $userHandler;

    /**
     * @var CustomerHandler
     */
    protected $customerHandler;

    public function getProcedureHandler(): ProcedureHandler
    {
        return $this->procedureHandler;
    }

    public function getUserHandler(): UserHandler
    {
        return $this->userHandler;
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function setUp(): void
    {
        parent::setUp();

        /* @var ProcedureHandler procedureHandler */
        $this->procedureHandler = self::getContainer()->get('dplan.procedure');
        $this->customerHandler = self::getContainer()->get(CustomerHandler::class);
        $this->userHandler = self::getContainer()->get(UserHandler::class);
    }

    /**
     * @throws Exception
     */
    public function testProcedureCustomers()
    {
        self::markSkippedForCIIntervention();

        /** @var Customer $rostockCustomer */
        $rostockCustomer = $this->getReference('Rostock');
        /** @var Customer $brandenburgCustomer */
        $brandenburgCustomer = $this->getReference('Brandenburg');
        /** @var Orga $paaOrga */
        $paaOrga = $this->getReference('testOrgaFP');
        /** @var Procedure $paaOrgaProcedure */
        $paaOrgaProcedure = $this->getReference('testProcedure2');

        $this->assertProcedureNotInCustomer($paaOrgaProcedure->getId(), $rostockCustomer->getSubdomain());
        $this->getUserHandler()->addCustomerToPublicAffairsAgencyByIds($rostockCustomer->getId(), $paaOrga->getId());
        $this->assertProcedureInCustomer($paaOrgaProcedure->getId(), $rostockCustomer->getSubdomain());
        $this->getUserHandler()->addCustomerToPublicAffairsAgencyByIds($brandenburgCustomer->getId(), $paaOrga->getId());
        $this->assertProcedureInCustomer($paaOrgaProcedure->getId(), $rostockCustomer->getSubdomain());
        $this->assertProcedureInCustomer($paaOrgaProcedure->getId(), $brandenburgCustomer->getSubdomain());
        $this->getUserHandler()->removeCustomerFromPublicAffairsAgencyByIds($brandenburgCustomer->getId(), $paaOrga->getId());
        $this->assertProcedureNotInCustomer($paaOrgaProcedure->getId(), $brandenburgCustomer->getSubdomain());
    }

    private function assertProcedureInCustomer($procedureId, $subdomain)
    {
        static::assertTrue($this->getProcedureHandler()->isProcedureInCustomer($procedureId, $subdomain));
    }

    private function assertProcedureNotInCustomer($procedureId, $subdomain)
    {
        static::assertFalse($this->getProcedureHandler()->isProcedureInCustomer($procedureId, $subdomain));
    }
}
