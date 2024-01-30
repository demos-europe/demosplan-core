<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureDeleter;
use demosplan\DemosPlanCoreBundle\Services\Queries\SqlQueriesService;
use Tests\Base\FunctionalTestCase;
use Zenstruck\Foundry\Proxy;

class ProcedureDeleterTest extends FunctionalTestCase
{
    private null|Procedure|Proxy $testProcedure;

    private ?array $testProcedures;

    /** @var ProcedureDeleter */
    protected $sut;

    /** @var SqlQueriesService */
    protected $queriesService;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(ProcedureDeleter::class);
        $this->queriesService = $this->getContainer()->get(SqlQueriesService::class);
        $this->testProcedure = ProcedureFactory::createOne();
        $this->testProcedures = ProcedureFactory::createMany(2);
    }

    public function testDeleteProcedure()
    {
        $entriesIds = [];
        foreach ($this->getEntries(Procedure::class) as $procedure) {
            $entriesIds[] = $procedure->getId();
        }

        $this->assertEquals(in_array($this->testProcedure->getId(), $entriesIds), 1);

        $totalAmountOfProceduresBeforeDeletion = $this->countEntries(Procedure::class);

        $this->sut->deleteProcedures([$this->testProcedure->getId()], false);

        static::assertSame($totalAmountOfProceduresBeforeDeletion - 1, $this->countEntries(Procedure::class));
    }

    public function testDeleteProcedures()
    {
        $ids = [];
        foreach ($this->testProcedures as $procedure) {
            $ids[] = $procedure->getId();
        }

        $entriesIds = [];
        foreach ($this->getEntries(Procedure::class) as $procedure) {
            $entriesIds[] = $procedure->getId();
        }
        $this->assertEquals(count(array_intersect($ids, $entriesIds)), 2);

        $totalAmountOfProceduresBeforeDeletion = $this->countEntries(Procedure::class);


        $this->sut->deleteProcedures($ids, false);

        static::assertSame($totalAmountOfProceduresBeforeDeletion - 2, $this->countEntries(Procedure::class));
    }
}
