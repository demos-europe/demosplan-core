<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Entity\OpenGeoDbShortTable;
use demosplan\DemosPlanCoreBundle\Logic\OpenGeoDbService;
use Tests\Base\FunctionalTestCase;

class OpenGeoDbServiceTest extends FunctionalTestCase
{
    /**
     * @var OpenGeoDbService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(OpenGeoDbService::class);
    }

    public function testGetAll()
    {
        $result = $this->sut->getAll();

        static::assertIsArray($result);
        static::assertCount(1, $result);
        static::assertInstanceOf(OpenGeoDbShortTable::class, $result[0]);
        static::assertEquals('Berlin', $result[0]->getCity());
    }
}
