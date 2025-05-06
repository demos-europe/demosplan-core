<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Location\Unit;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadLocationData;
use demosplan\DemosPlanCoreBundle\Entity\Location;
use demosplan\DemosPlanCoreBundle\Logic\LocationService;
use Tests\Base\UnitTestCase;

class LocationServiceTest extends UnitTestCase
{
    /** @var LocationService */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(LocationService::class);
    }

    public function testFindByArs(): void
    {
        $amt1 = $this->getLocationByReference(LoadLocationData::AMT_1);
        $locations = $this->sut->findByArs($amt1->getArs());
        $count = count($locations);
        $errorMsg = "Expected 1 result from findByArs. $count received.";
        $this->assertCount(1, $locations, $errorMsg);
        $this->checkLocationsEquals($amt1, $locations[0]);

        $county1 = $this->getLocationByReference(LoadLocationData::COUNTY_1);
        $locations = $this->sut->findByArs($county1->getArs());
        $count = count($locations);
        $errorMsg = "Expected 1 result from findByArs. $count received.";
        $this->assertCount(1, $locations, $errorMsg);
        $this->checkLocationsEquals($county1, $locations[0]);

        $municipality1 = $this->getLocationByReference(LoadLocationData::MUNICIPALTIY_1);
        $locations = $this->sut->findByArs($municipality1->getArs());
        $count = count($locations);
        $errorMsg = "Expected 1 result from findByArs. $count received.";
        $this->assertCount(1, $locations, $errorMsg);
        $this->checkLocationsEquals($municipality1, $locations[0]);

        $nonexistentArs = '0000';
        $emptyResult = $this->sut->findByArs($nonexistentArs);
        $count = count($emptyResult);
        $errorMsg = "Expected 0 results from findByArs. $count received.";
        $this->assertCount(0, $emptyResult, $errorMsg);
    }

    public function testFindByMunicipalCode(): void
    {
        $nonexistentMunicipalCode = '0000';
        $emptyResult = $this->sut->findByMunicipalCode($nonexistentMunicipalCode);
        $count = count($emptyResult);
        $errorMsg = "Expected 0 results from findByMunicipalCode. $count received.";
        $this->assertCount(0, $emptyResult, $errorMsg);

        $municipality1 = $this->getLocationByReference(LoadLocationData::MUNICIPALTIY_1);
        $locations = $this->sut->findByMunicipalCode($municipality1->getMunicipalCode());
        $count = count($locations);
        $errorMsg = "Expected 1 result from findByMunicipalCode. $count received.";
        $this->assertCount(1, $locations, $errorMsg);
        $this->checkLocationsEquals($municipality1, $locations[0]);
    }

    public function checkLocationsEquals(Location $location1, Location $location2): void
    {
        $this->assertEquals($location1->getArs(), $location2->getArs());
        $this->assertEquals($location1->getId(), $location2->getId());
        $this->assertEquals($location1->getLat(), $location2->getLat());
        $this->assertEquals($location1->getLon(), $location2->getLon());
        $this->assertEquals($location1->getMunicipalCode(), $location2->getMunicipalCode());
        $this->assertEquals($location1->getName(), $location2->getName());
        $this->assertEquals($location1->getPostcode(), $location2->getPostcode());
    }

    public function getLocationByReference(string $reference): Location
    {
        /** @var Location $location */
        $location = $this->fixtures->getReference($reference);

        return $location;
    }
}
