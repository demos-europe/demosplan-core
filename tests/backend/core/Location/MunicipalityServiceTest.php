<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Location;

use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Logic\Statement\MunicipalityService;
use Tests\Base\FunctionalTestCase;

class MunicipalityServiceTest extends FunctionalTestCase
{
    /**
     * @var MunicipalityService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(MunicipalityService::class);
    }

    public function testGetAllMunicipalities()
    {
        $testMunicipality1 = $this->fixtures->getReference('testMunicipality1');
        $testMunicipality2 = $this->fixtures->getReference('testMunicipality2');

        $result = $this->sut->getAllMunicipalities();
        static::assertNotNull($result);
        $actualAmountOfMunicipalities = $this->countEntries(Municipality::class);
        static::assertCount($actualAmountOfMunicipalities, $result);

        static::assertContains($testMunicipality1, $result);
        static::assertContains($testMunicipality2, $result);
    }

    public function testGetAllMunicipalitiesAsArray()
    {
        /** @var Municipality $testMunicipality1 */
        $testMunicipality1 = $this->fixtures->getReference('testMunicipality1');
        /** @var Municipality $testMunicipality2 */
        $testMunicipality2 = $this->fixtures->getReference('testMunicipality2');

        $result = $this->sut->getAllMunicipalitiesAsArray();
        static::assertNotNull($result);
        static::assertIsArray($result);

        $actualAmountOfMunicipalities = $this->countEntries(Municipality::class);
        static::assertCount($actualAmountOfMunicipalities, $result);

        static::assertEquals(['id', 'name'], \array_keys($result[0]));

        // NOTE: this is supposed to test the sorting which it kind of does but it requires knowledge of the
        //       fixtures which is a bad thing
        static::assertEquals($testMunicipality1->getName(), $result[0]['name']);
        static::assertEquals($testMunicipality2->getName(), $result[1]['name']);
    }
}
