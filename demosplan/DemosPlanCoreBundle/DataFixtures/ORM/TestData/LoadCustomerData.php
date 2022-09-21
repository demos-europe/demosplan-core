<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\Persistence\ObjectManager;

class LoadCustomerData extends TestFixture
{
    public const ROSTOCK = 'Rostock';
    public const BRANDENBURG = 'Brandenburg';
    public const SCHLESWIGHOLSTEIN = 'Schleswig-Holstein';
    public const DEMOS = 'Demos';

    public function load(ObjectManager $manager)
    {
        $customerRostock = new Customer(self::ROSTOCK, 'rostock');
        $customerRostock->setAccessibilityExplanation('Barrierefreiheitserklärung');
        $manager->persist($customerRostock);
        $this->setReference(self::ROSTOCK, $customerRostock);

        $customerBrandenburg = new Customer(self::BRANDENBURG, 'bb');
        $customerBrandenburg->setAccessibilityExplanation('Barrierefreiheitserklärung');
        $manager->persist($customerBrandenburg);
        $this->setReference(self::BRANDENBURG, $customerBrandenburg);

        $customerSH = new Customer(self::SCHLESWIGHOLSTEIN, 'sh');
        $customerSH->setAccessibilityExplanation('Barrierefreiheitserklärung');
        $manager->persist($customerSH);
        $this->setReference(self::SCHLESWIGHOLSTEIN, $customerSH);

        $customerDemos = new Customer(self::DEMOS, 'demos');
        $customerDemos->setAccessibilityExplanation('Barrierefreiheitserklärung');
        $manager->persist($customerDemos);
        $this->setReference(self::DEMOS, $customerDemos);

        $manager->flush();
    }
}
