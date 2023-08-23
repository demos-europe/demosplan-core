<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Story;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureTemplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Report\ReportEntryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\CountyFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerCountyFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\CustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaStatusInCustomerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserRoleInCustomerFactory;
use Zenstruck\Foundry\Story;

final class OrgaHasMultipleRelatedCustomerStory extends Story
{
    public const NAMES = ['Bayern', 'Brandenburg'];

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
//        //Create basic customer with blueprint
//        $testCustomer = CustomerFactory::createOne([
//            'name' => self::NAMES[0],
//            'subdomain' =>self::NAMES[0]
//        ]);
//
//        $testCustomer2 = CustomerFactory::createOne([
//            'name' => self::NAMES[1],
//            'subdomain' => self::NAMES[1],
//            'defaultProcedureBlueprint' => ProcedureTemplateFactory::createOne()
//        ]);
//
//        //Create four counties and attach them to the testCustomer
//        CustomerCountyFactory::createMany(4, static fn (int $i) => [
//            'customer' => $testCustomer,
//            'county' => CountyFactory::new(),
//        ]);
//
//        //Create customer related user-role, orga-type and reports
//        UserRoleInCustomerFactory::createOne(['customer' => $testCustomer]);
//        ProcedureTemplateFactory::createOne(['customer' => $testCustomer]);
//
//        OrgaStatusInCustomerFactory::createOne(
//            [
//                'customer' => $testCustomer,
//                'orga' => OrgaFactory::findOrCreate(['name' => 'orga1']),
//            ]
//        );
//
//        OrgaStatusInCustomerFactory::createOne(
//            [
//                'customer' => $testCustomer2,
//                'orga' => OrgaFactory::findOrCreate(['name' => 'orga1']),
//            ]
//        );
//
//        ReportEntryFactory::createMany(7, ['customer' => $testCustomer]);
    }
}
