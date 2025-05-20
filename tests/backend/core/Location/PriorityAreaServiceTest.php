<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Location;

use demosplan\DemosPlanCoreBundle\Entity\Statement\PriorityArea;
use demosplan\DemosPlanCoreBundle\Logic\Statement\PriorityAreaService;
use Tests\Base\FunctionalTestCase;

class PriorityAreaServiceTest extends FunctionalTestCase
{
    /**
     * @var PriorityAreaService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(PriorityAreaService::class);
    }

    public function testGetAllPriorityAreas()
    {
        $testPriorityArea1 = $this->fixtures->getReference('testPriorityArea1');
        $testPriorityArea2 = $this->fixtures->getReference('testPriorityArea2');

        $result = $this->sut->getAllPriorityAreas();
        static::assertNotNull($result);
        $actualAmountOfPriorityAreas = $this->countEntries(PriorityArea::class);
        static::assertCount($actualAmountOfPriorityAreas, $result);

        static::assertContains($testPriorityArea1, $result);
        static::assertContains($testPriorityArea2, $result);
        static::assertSame('PR1_NFL_021', $result[0]->getName());
    }

    public function testGetAllPriorityAreasAsArray()
    {
        $result = $this->sut->getAllPriorityAreasAsArray();
        static::assertIsArray($result);

        $actualAmountOfPriorityAreas = $this->countEntries(PriorityArea::class);
        static::assertCount($actualAmountOfPriorityAreas, $result);

        static::assertEquals(['id', 'name'], \array_keys($result[0]));
        static::assertEquals('PR1_NFL_021', $result[0]['name']);
    }
}
