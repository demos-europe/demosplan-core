<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Location;

use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Logic\Statement\CountyService;
use Tests\Base\FunctionalTestCase;

class CountyServiceTest extends FunctionalTestCase
{
    /**
     * @var CountyService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        // @improve T14122
        $this->sut = self::getContainer()->get(CountyService::class);
    }

    public function testGetCounty()
    {
        $testCounty = $this->fixtures->getReference('testCounty1');
        $result = $this->sut->getCounty($testCounty->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(County::class, $result);
        static::assertSame($testCounty->getName(), $result->getName());
        static::assertEquals($testCounty->getStatements(), $result->getStatements());
    }

    public function testGetAllCounties()
    {
        self::markSkippedForCIIntervention();

        $testCounty1 = $this->fixtures->getReference('testCounty1');
        $testCounty2 = $this->fixtures->getReference('testCounty2');

        $result = $this->sut->getAllCounties();
        static::assertNotNull($result);
        $actualAmountOfCounties = $this->countEntries(County::class);
        static::assertCount($actualAmountOfCounties, $result);

        static::assertContains($testCounty1, $result);
        static::assertContains($testCounty2, $result);

        // test sorting
        static::assertSame('Kreis 1', $result[0]->getName());
        static::assertSame('Kreis 2', $result[1]->getName());
        static::assertSame('Nordfriesland', $result[2]->getName());
        static::assertSame('Rendsburg-Eckernförde', $result[3]->getName());
    }

    public function testGetAllCountiesAsArray()
    {
        self::markSkippedForCIIntervention();

        $result = $this->sut->getAllCountiesAsArray();
        static::assertIsArray($result);

        $actualAmountOfCounties = $this->countEntries(County::class);
        static::assertCount($actualAmountOfCounties, $result);

        static::assertEquals(['id', 'name'], \array_keys($result[0]));

        static::assertEquals('Kreis 1', $result[0]['name']);
        static::assertEquals('Kreis 2', $result[1]['name']);
        static::assertEquals('Nordfriesland', $result[2]['name']);
        static::assertEquals('Rendsburg-Eckernförde', $result[3]['name']);
    }
}
