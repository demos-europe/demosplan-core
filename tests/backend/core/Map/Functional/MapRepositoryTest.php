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

    public function testCreateGlobalLayerInsertsOneCopyPerProcedure(): void
    {
        $procedureA = ProcedureFactory::createOne();
        $procedureB = ProcedureFactory::createOne();

        $conn = $this->getEntityManager()->getConnection();
        $procedureCount = (int) $conn->fetchOne('SELECT COUNT(*) FROM _procedure');

        $globalGisLayer = $this->sut->add([
            'name'    => 'global-layer',
            'url'     => 'https://example.test/wms',
            'type'    => 'overlay',
            'layers'  => '0',
            'order'   => 1,
            'opacity' => 100,
            'enabled' => true,
        ]);

        $copies = $conn->fetchAllAssociative(
            'SELECT _g_id, _p_id, _g_name, _g_create_date FROM _gis WHERE _g_global_id = ?',
            [$globalGisLayer->getId()]
        );

        self::assertCount($procedureCount, $copies);
        $procedureIds = array_column($copies, '_p_id');
        self::assertContains($procedureA->getId(), $procedureIds);
        self::assertContains($procedureB->getId(), $procedureIds);
        foreach ($copies as $copy) {
            self::assertSame('global-layer', $copy['_g_name']);
            self::assertNotEmpty($copy['_g_create_date']);
        }
    }

    public function testCreateGlobalLayerIssuesConstantStatementCountRegardlessOfProcedureCount(): void
    {
        ProcedureFactory::createMany(5);

        $logger = new \Doctrine\DBAL\Logging\DebugStack();
        $this->getEntityManager()->getConnection()->getConfiguration()->setSQLLogger($logger);

        $this->sut->add([
            'name'    => 'global-layer',
            'url'     => 'https://example.test/wms',
            'type'    => 'overlay',
            'layers'  => '0',
            'order'   => 1,
            'opacity' => 100,
            'enabled' => true,
        ]);

        $inserts = array_filter(
            $logger->queries,
            static fn (array $q): bool => 1 === preg_match('/^\s*INSERT INTO `?_gis`?/i', (string) $q['sql'])
        );

        // One INSERT for the parent global layer + exactly one bulk multi-row
        // INSERT for all per-procedure copies (5 fits in a single chunk of 500).
        self::assertCount(2, $inserts, 'create path must collapse copies into one bulk INSERT');
    }
}
