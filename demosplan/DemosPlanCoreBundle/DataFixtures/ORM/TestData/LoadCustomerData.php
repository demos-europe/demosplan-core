<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\User\Customer;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadCustomerData extends TestFixture
{
    /**
     * @deprecated use {@link LoadCustomerData::BRANDENBURG} instead
     */
    final public const BB = 'testCustomerBrandenburg';
    final public const HINDSIGHT = 'testCustomer';
    final public const ROSTOCK = 'Rostock';
    final public const BRANDENBURG = 'Brandenburg';
    final public const SCHLESWIGHOLSTEIN = 'Schleswig-Holstein';
    final public const DEMOS = 'Demos';

    public function load(ObjectManager $manager): void
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
        $customerDemos->setBaseLayerUrl('https://sgx.geodatenzentrum.de/wms_basemapde');
        $customerDemos->setBaseLayerLayers('de_basemapde_web_raster_farbe');
        $manager->persist($customerDemos);
        $this->setReference(self::DEMOS, $customerDemos);

        $manager->flush();
    }
}
