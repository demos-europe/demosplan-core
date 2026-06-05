<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Map\GisLayerFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Repository\MapRepository;
use Tests\Base\FunctionalTestCase;

class MapRepositoryTest extends FunctionalTestCase
{
    protected ?MapRepository $sut = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(MapRepository::class);
    }

    public function testGlobalLayerUpdatePropagatesNameToAllCopies(): void
    {
        $procedure = ProcedureFactory::createOne();
        $globalGisLayer = GisLayerFactory::createOne(['procedureId' => '']);
        $globalGisLayerIdColumnName = 'gId';
        $procedureGisLayerA = GisLayerFactory::createOne([
            $globalGisLayerIdColumnName      => $globalGisLayer->getId(),
            'procedureId'                    => $procedure->getId(),
        ]);
        $procedureGisLayerB = GisLayerFactory::createOne([
            $globalGisLayerIdColumnName      => $globalGisLayer->getId(),
            'procedureId'                    => $procedure->getId(),
        ]);

        $this->sut->updateByArray([
            'id'   => $globalGisLayer->getId(),
            'name' => 'propagated-name',
        ]);

        $procedureGisLayerA->_refresh();
        $procedureGisLayerB->_refresh();

        self::assertSame('propagated-name', $procedureGisLayerA->getName());
        self::assertSame('propagated-name', $procedureGisLayerB->getName());
    }
}
