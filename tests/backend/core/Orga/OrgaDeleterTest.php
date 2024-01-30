<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Orga;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\OrgaFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\Orga\OrgaDeleter;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class OrgaDeleterTest extends FunctionalTestCase
{
    private null|Orga|Proxy $testOrga;

    private ?array $testOrgas;

    /** @var OrgaDeleter */
    protected $sut;

    /** @var SqlQueriesService */
    protected $queriesService;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(OrgaDeleter::class);
        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testOrga = OrgaFactory::createOne();
        $this->testOrgas = OrgaFactory::createMany(2);
    }

    public function testDeleteOrga()
    {
        $entriesIds = [];
        foreach ($this->getEntries(Orga::class) as $orga) {
            $entriesIds[] = $orga->getId();
        }

        $this->assertEquals(in_array($this->testOrga->getId(), $entriesIds), 1);

        $totalAmountOfOrgasBeforeDeletion = $this->countEntries(Orga::class);

        $this->sut->deleteOrganisations([$this->testOrga->getId()], false);

        static::assertSame($totalAmountOfOrgasBeforeDeletion - 1, $this->countEntries(Orga::class));
    }

    public function testDeleteOrgas()
    {
        $ids = [];
        foreach ($this->testOrgas as $orga) {
            $ids[] = $orga->getId();
        }

        $entriesIds = [];
        foreach ($this->getEntries(Orga::class) as $orga) {
            $entriesIds[] = $orga->getId();
        }
        $this->assertEquals(count(array_intersect($ids, $entriesIds)), 2);

        $totalAmountOfOrgasBeforeDeletion = $this->countEntries(Orga::class);


        $this->sut->deleteOrganisations($ids, false);

        static::assertSame($totalAmountOfOrgasBeforeDeletion - 2, $this->countEntries(Orga::class));
    }
}
