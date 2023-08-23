<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Story;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureTemplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Report\ReportEntryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\CountyFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerCountyFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserRoleInCustomerFactory;
use Zenstruck\Foundry\Story;

final class FullCustomerStory extends Story
{
    public const NAME = 'Bayern';

    /**
     * Creates a full Customer including all possible relations:
     * - Customer-Blueprint (new Procedure)
     * - Customer-County (new Counties)
     * - Customer-User-Role (new User and new Role)
     * - Customer-OrgaType (new Orga and new OrgaType)
     * - Customer-Report (new ReportEntries).
     */
    public function build(): void
    {
        // Create basic customer
        $testCustomer = CustomerFactory::createOne([
            'name'      => self::NAME,
            'subdomain' => self::NAME,
        ]);

        // Create some counties and attach them to the testCustomer
        CustomerCountyFactory::createMany(4, static fn (int $i) => [
            'customer' => $testCustomer,
            'county'   => CountyFactory::new(),
        ]);

        // Createu roles of users and ProcedureTemplate and attach them to the testCustomer
        UserRoleInCustomerFactory::createOne(['customer' => $testCustomer]);
        ProcedureTemplateFactory::createOne(['customer' => $testCustomer]);

        // Create orga as owner of a procedure and attach them to the testCustomer via orgaType.
        $orga = OrgaFactory::createOne();
        $procedure1 = ProcedureFactory::createOne(['orga' => $orga]);
        OrgaStatusInCustomerFactory::createOne([
            'customer' => $testCustomer,
            'orga'     => $orga,
        ]);

        $orga2 = OrgaFactory::createOne();
        ProcedureFactory::createOne(['orga' => $orga2]);
        OrgaStatusInCustomerFactory::createOne([
            'customer' => $testCustomer,
            'orga'     => $orga2,
        ]);

        // create ReportEntries with related procedure of one of the orgas related to the customer
        ReportEntryFactory::createMany(7, ['identifier' => $procedure1->getId()]);
    }
}
