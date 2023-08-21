<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData;

use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use Doctrine\Persistence\ObjectManager;

/**
 * @deprecated loading fixture data via Foundry-Factories instead
 */
class LoadLocationData extends TestFixture
{
    final public const COUNTY_1 = 'County1';
    final public const COUNTY_2 = 'County2';
    final public const AMT_1 = 'Amt1';
    final public const AMT_2 = 'Amt2';
    final public const AMT_3 = 'Amt3';
    final public const MUNICIPALTIY_1 = 'Municipality1';
    final public const MUNICIPALTIY_2 = 'Municipality2';
    final public const MUNICIPALTIY_3 = 'Municipality3';
    final public const MUNICIPALTIY_4 = 'Municipality4';
    final public const MUNICIPALTIY_5 = 'Municipality5';
    final public const MUNICIPALTIY_6 = 'Municipality6';

    public function load(ObjectManager $manager): void
    {
        $entity = new County();
        $entity->setName('Kreis 1');
        $entity->setEmail('tolleEmail@notValid.com');
        $manager->persist($entity);
        $this->setReference('testCounty1', $entity);

        $entity2 = new County();
        $entity2->setName('Kreis 2');
        $manager->persist($entity2);
        $entity2->setEmail('tolleEmail@notValid.com');
        $this->setReference('testCounty2', $entity2);

        $keys = [
            'Steinburg',
            'Rendsburg-Eckernförde',
            'Nordfriesland',
            'Schleswig-Flensburg',
        ];
        foreach ($keys as $key) {
            $e = new County();
            $e->setName($key);
            $manager->persist($e);
        }

        $manager->flush();

        $entity = new PriorityArea();
        $entity->setKey('Vorrang 1');
        $entity->setType('positive');
        $manager->persist($entity);
        $this->setReference('testPriorityArea1', $entity);

        $entity2 = new PriorityArea();
        $entity2->setKey('Vorrang 2');
        $entity2->setType('negative');
        $manager->persist($entity2);
        $this->setReference('testPriorityArea2', $entity2);

        $keys = [
            'PR2_RDE_140' => 'positive',
            'PR1_NFL_021' => 'positive',
            'PR3_STE_027' => 'negative',
            'PR1_NFL_028' => 'negative',
            'PR1_NFL_030' => 'positive',
            'PR1_NFL_035' => 'positive',
            'PR1_NFL_037' => 'positive',
            'PR1_SLF_022' => 'positive',
            'PR1_SLF_021' => 'positive',
            'PR1_SLF_027' => 'positive',
            'PR1_SLF_028' => 'positive',
            'PR1_SLF_033' => 'positive',
            'PR1_SLF_039' => 'positive',
        ];
        foreach ($keys as $key => $type) {
            $e = new PriorityArea();
            $e->setKey($key);
            $e->setType($type);
            $manager->persist($e);
        }

        $manager->flush();

        $entity = new Municipality();
        $entity->setName('Gemeinde 1');
        $manager->persist($entity);
        $this->setReference('testMunicipality1', $entity);

        $entity2 = new Municipality();
        $entity2->setName('Gemeinde 2');
        $manager->persist($entity2);
        $this->setReference('testMunicipality2', $entity2);

        $entity3 = new Municipality();
        $entity3->setName('Gemeinde 3');
        $entity3->setOfficialMunicipalityKey(53646);
        $manager->persist($entity3);
        $this->setReference('testMunicipality3', $entity3);

        $this->loadLocations($manager);

        $manager->flush();
    }

    /**
     * Add Location Entity Fixtures.
     */
    public function loadLocations(ObjectManager $manager): void
    {
        $county1 = new Location();
        $county1->setArs('11111');
        $county1->setName('County1');
        $county1->setPostcode('11111');
        $manager->persist($county1);
        $this->setReference(self::COUNTY_1, $county1);

        $county2 = new Location();
        $county2->setArs('22222');
        $county2->setName('County2');
        $county2->setPostcode('22222');
        $manager->persist($county2);
        $this->setReference(self::COUNTY_2, $county2);

        $amt1 = new Location();
        $amt1->setArs('111111111');
        $amt1->setName('Amt1');
        $manager->persist($amt1);
        $this->setReference(self::AMT_1, $amt1);

        $amt2 = new Location();
        $amt2->setArs('222222222');
        $amt2->setName('Amt2');
        $manager->persist($amt2);
        $this->setReference(self::AMT_2, $amt2);

        $amt3 = new Location();
        $amt3->setArs('333333333');
        $amt3->setName('Amt3');
        $manager->persist($amt3);
        $this->setReference(self::AMT_3, $amt3);

        $municipality1 = new Location();
        $municipality1->setArs('111111111111');
        $municipality1->setLat('11.11111');
        $municipality1->setLon('11.11111');
        $municipality1->setMunicipalCode('1111111');
        $municipality1->setName('Municipality1');
        $municipality1->setPostcode('11111');
        $manager->persist($municipality1);
        $this->setReference(self::MUNICIPALTIY_1, $municipality1);

        $municipality2 = new Location();
        $municipality2->setArs('222222222222');
        $municipality2->setLat('22.22222');
        $municipality2->setLon('22.22222');
        $municipality2->setMunicipalCode('2222222');
        $municipality2->setName('Municipality2');
        $municipality2->setPostcode('22222');
        $manager->persist($municipality2);
        $this->setReference(self::MUNICIPALTIY_2, $municipality2);

        $municipality3 = new Location();
        $municipality3->setArs('333333333333');
        $municipality3->setLat('33.33333');
        $municipality3->setLon('33.33333');
        $municipality3->setMunicipalCode('3333333');
        $municipality3->setName('Municipality3');
        $municipality3->setPostcode('33333');
        $manager->persist($municipality3);
        $this->setReference(self::MUNICIPALTIY_3, $municipality3);

        $municipality4 = new Location();
        $municipality4->setArs('44444444444');
        $municipality4->setLat('44.44444');
        $municipality4->setLon('44.44444');
        $municipality4->setMunicipalCode('4444444');
        $municipality4->setName('Municipality4');
        $municipality4->setPostcode('44444');
        $manager->persist($municipality4);
        $this->setReference(self::MUNICIPALTIY_4, $municipality4);

        $municipality5 = new Location();
        $municipality5->setArs('555555555555');
        $municipality5->setLat('55.55555');
        $municipality5->setLon('55.555554');
        $municipality5->setMunicipalCode('5555555');
        $municipality5->setName('Municipality5');
        $municipality5->setPostcode('55555');
        $manager->persist($municipality5);
        $this->setReference(self::MUNICIPALTIY_5, $municipality5);

        $municipality6 = new Location();
        $municipality6->setArs('666666666666');
        $municipality6->setLat('66.66666');
        $municipality6->setLon('66.66666');
        $municipality6->setMunicipalCode('6666666');
        $municipality6->setName('Municipality6');
        $municipality6->setPostcode('66666');
        $manager->persist($municipality6);
        $this->setReference(self::MUNICIPALTIY_6, $municipality6);
    }
}
