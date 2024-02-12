<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Map\Functional;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use Tests\Base\FunctionalTestCase;

class MapServiceTest extends FunctionalTestCase
{
    /**
     * @var MapService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(MapService::class);
    }

    public function testGetGisLayerListValueStructure()
    {
        $procedureId = $this->fixtures->getReference('testProcedure2')->getId();
        $layerList = $this->sut->getGisList($procedureId, 'base');

        static::assertCount(1, $layerList);
        static::assertTrue(19 <= sizeof($layerList[0]));

        $this->checkArrayStructure($layerList[0]);
    }

    public function testGetGisLayerListWithEmptyProcedure()
    {
        $layerList = $this->sut->getGisList('', null);
        static::assertCount(1, $layerList);
    }

    public function testGetGisAdminLayerListValueStructure()
    {
        $procedureId = $this->fixtures->getReference('testProcedure2')->getId();

        $numberOfNonGlobalLayer = $this->countEntries(
            GisLayer::class,
            ['deleted' => false, 'procedureId' => $procedureId]
        );

        $layerList = $this->sut->getGisAdminList($procedureId);

        static::assertTrue(19 <= sizeof($layerList[0]));
        static::assertCount($numberOfNonGlobalLayer, $layerList);

        $this->checkArrayStructure($layerList[0]);
    }

    public function testGetAdminGisLayerListWithNotExistingProcedure()
    {
        $procedureId = 'notexist';

        $layerList = $this->sut->getGisAdminList($procedureId);

        static::assertCount(0, $layerList);
    }

    public function testGetGisGlobalListStructure()
    {
        $layerList = $this->sut->getGisGlobalList();
        static::assertTrue(19 <= count($layerList[0]));
    }

    public function testGetSingleGis()
    {
        $testGisLayerId = $this->fixtures->getReference('testGisLayer1');

        $singleLayer = $this->sut->getSingleGis($testGisLayerId);

        static::assertTrue(is_array($singleLayer));
        static::assertTrue(19 <= sizeof($singleLayer));

        $this->checkArrayStructure($singleLayer);
    }

    public function testGetSingleGisWithEmptyIdents()
    {
        $ident = '';

        $singleLayer = $this->sut->getSingleGis($ident);

        static::assertTrue(is_array($singleLayer));
        static::assertArrayHasKey('bplan', $singleLayer);
        static::assertTrue(is_bool($singleLayer['bplan']));
        static::assertArrayHasKey('xplan', $singleLayer);
        static::assertTrue(is_bool($singleLayer['xplan']));
        static::assertArrayHasKey('print', $singleLayer);
        static::assertTrue(is_bool($singleLayer['print']));
        static::assertArrayHasKey('deleted', $singleLayer);
        static::assertTrue(is_bool($singleLayer['deleted']));
        static::assertArrayHasKey('visible', $singleLayer);
        static::assertTrue(is_bool($singleLayer['visible']));
        static::assertArrayHasKey('scope', $singleLayer);
        static::assertTrue(is_bool($singleLayer['scope']));
        static::assertArrayHasKey('default', $singleLayer);
        static::assertTrue(is_bool($singleLayer['default']));
    }

    public function testReOrderGis()
    {
        $testGisLayer1 = $this->fixtures->getReference('testGisLayer1');
        $testGisLayer2 = $this->fixtures->getReference('testGisLayer2');
        $testGisLayer4 = $this->fixtures->getReference('testGisLayer4');
        $testGisLayer5 = $this->fixtures->getReference('testGisLayer5');
        $testGisLayer6 = $this->fixtures->getReference('testGisLayer6');

        $idents = [
            $testGisLayer2->getIdent(),
            $testGisLayer1->getIdent(),
            $testGisLayer5->getIdent(),
            $testGisLayer6->getIdent(),
        ];
        $result = $this->sut->reOrder($idents);

        static::assertTrue($result);

        $gis2 = $this->sut->getSingleGis($testGisLayer2->getIdent());
        static::assertEquals(1, $gis2['order']);

        $gis1 = $this->sut->getSingleGis($testGisLayer1->getIdent());
        static::assertEquals(2, $gis1['order']);

        $gis5 = $this->sut->getSingleGis($testGisLayer5->getIdent());
        static::assertEquals(3, $gis5['order']);

        $gis6 = $this->sut->getSingleGis($testGisLayer6->getIdent());
        static::assertEquals(4, $gis6['order']);

        $gis4 = $this->sut->getSingleGis($testGisLayer4->getIdent());
        static::assertEquals($testGisLayer4->getOrder(), $gis4['order']);
    }

    public function testAddGlobalGis()
    {
        $data = [
            'type'      => 'base',
            'name'      => 'globale testkarte',
            'url'       => 'http://www.globaletestkarte.de',
            'Layer'     => '0',
            'pId'       => '',
            'globalId'  => null,
        ];

        $numberOfEntriesBefore = $this->countEntries(GisLayer::class);
        $this->sut->addGis($data);
        $numberOfEntriesAfter = $this->countEntries(GisLayer::class);

        // es werden Anzahl aller Einträge von vorher + den neuen global Eintrag + Anzahl der Verfahren erwartet.
        $numberOfProcedures = $this->countEntries(Procedure::class);
        static::assertEquals(
            $numberOfEntriesBefore + $numberOfProcedures + 1,
            $numberOfEntriesAfter
        );
    }

    public function testAddGisWmts()
    {
        // todo: activate this test and update it, now that we're using wmts
        self::markSkippedForCIIntervention();
        // WMTS Layer add needs external getCapabilities to be called

        // Data for new layer
        $data = [
            'type'          => 'base',
            'name'          => 'testkarte',
            'url'           => 'http://www.testkarte.de',
            'Layer'         => '0',
            'pId'           => $this->fixtures->getReference('testProcedure2')->getId(),
            'capabilities'  => 'capabilities',
            'serviceType'   => 'wmts',
            'tileMatrixSet' => 'tileMatrixSet',
        ];

        // check entries of DB
        $numberOfEntriesBefore = $this->countEntries(GisLayer::class);
        $singleLayer = $this->sut->addGis($data);
        $numberOfEntriesAfter = $this->countEntries(GisLayer::class);
        static::assertEquals($numberOfEntriesBefore + 1, $numberOfEntriesAfter);
        static::assertNotNull($singleLayer['category']);

        // check return value
        $singleLayer = $this->sut->addGis($data);
        static::assertTrue(is_array($singleLayer));
        static::assertTrue(19 <= sizeof($singleLayer));

        $this->checkArrayStructure($singleLayer);
    }

    public function testDeleteGlobalGis()
    {
        $data = [
            'type'     => 'base',
            'name'     => 'globale testkarte',
            'url'      => 'http://www.globaletestkarte.de',
            'Layer'    => '0',
            'pId'      => '',
            'globalId' => null,
        ];
        $globalLayer = $this->sut->addGis($data);

        $numberOfProcedures = $this->countEntries(Procedure::class);
        $numberOfEntriesBefore = $this->countEntries(GisLayer::class);
        $this->sut->deleteGis($globalLayer['ident']);
        $numberOfEntriesAfter = $this->countEntries(GisLayer::class);

        static::assertEquals(
            $numberOfEntriesBefore - $numberOfProcedures - 1,
            $numberOfEntriesAfter
        );
    }

    public function testDeleteGis()
    {
        $gisLayer = $this->fixtures->getReferences();

        $toDelete = [
            $gisLayer['testGisLayer1']->getIdent(),
            $gisLayer['testGisLayer2']->getIdent(),
            $gisLayer['testGisLayer5']->getIdent(),
            $gisLayer['testGisLayer6']->getIdent(),
        ];

        $amountOfEntriesBefore = $this->countEntries(GisLayer::class);
        $result = $this->sut->deleteGis($toDelete);

        static::assertTrue($result);
        static::assertEquals(
            $amountOfEntriesBefore - count($toDelete),
            $this->countEntries(GisLayer::class)
        );
    }

    /**
     * @param array $layer
     */
    protected function checkArrayStructure($layer)
    {
        static::assertArrayHasKey('ident', $layer);
        $this->checkId($layer['ident']);
        static::assertArrayHasKey('name', $layer);
        static::assertTrue(is_string($layer['name']));
        static::assertArrayHasKey('type', $layer);
        static::assertTrue(is_string($layer['type']));
        static::assertArrayHasKey('url', $layer);
        static::assertTrue(is_string($layer['url']));
        static::assertArrayHasKey('layers', $layer);
        static::assertTrue(is_string($layer['layers']));
        static::assertArrayHasKey('legend', $layer);
        static::assertArrayHasKey('opacity', $layer);
        static::assertTrue(is_int($layer['opacity']));
        static::assertArrayHasKey('bplan', $layer);
        static::assertTrue(is_bool($layer['bplan']));
        static::assertArrayHasKey('xplan', $layer);
        static::assertTrue(is_bool($layer['xplan']));
        static::assertArrayHasKey('print', $layer);
        static::assertTrue(is_bool($layer['print']));
        static::assertArrayHasKey('deleted', $layer);
        static::assertTrue(is_bool($layer['deleted']));
        static::assertArrayHasKey('visible', $layer);
        static::assertTrue(is_bool($layer['visible']));
        static::assertArrayHasKey('createdate', $layer);
        static::assertTrue(is_numeric($layer['createdate']));
        static::assertTrue(0 < $layer['createdate']);
        static::assertArrayHasKey('modifydate', $layer);
        static::assertTrue(is_numeric($layer['modifydate']));
        static::assertTrue(0 < $layer['modifydate']);
        static::assertArrayHasKey('order', $layer);
        static::assertTrue(is_int($layer['order']));
        static::assertArrayHasKey('scope', $layer);
        static::assertTrue(is_bool($layer['scope']));
        static::assertArrayHasKey('default', $layer);
        static::assertTrue(is_bool($layer['default']));
        static::assertArrayHasKey('pId', $layer);
        $this->checkId($layer['pId']);
        static::assertArrayHasKey('serviceType', $layer);
        static::assertTrue(is_string($layer['serviceType']));
        static::assertArrayHasKey('capabilities', $layer);
        static::assertTrue(
            is_string($layer['capabilities']) || is_null($layer['capabilities'])
        );
        static::assertArrayHasKey('tileMatrixSet', $layer);
        static::assertTrue(
            is_string($layer['tileMatrixSet']) || is_null(
                $layer['tileMatrixSet']
            )
        );
    }

    public function testCreateMapScreenshot()
    {
        self::markSkippedForCIIntervention();
        // Test takes too long to complete, please refactor.

        $statement = $this->getStatementReference('statement23WithPolygonAndMap');
        $procedure = $statement->getProcedure();

        $procedureId = $procedure->getId();
        $draftStatementOrStatementId = $statement->getId();

        $result = $this->sut->createMapScreenshot($procedureId, $draftStatementOrStatementId);

        $parts = explode(':', $result);
        $hash = array_pop($parts);
        $this->checkId($hash);
        self::assertSame("Map_$draftStatementOrStatementId.png", implode(':', $parts));
    }

    /**
     * Test that copyright saved in procedureSettings is retrieved when getting GetMapOptions.
     */
    public function testGetMapOptions()
    {
        $procedureSettings = ProcedureSettingsFactory::createOne();
        $procedure = $procedureSettings->getProcedure();
        $mapOptions = $this->sut->getMapOptions($procedure->getId());
        self::assertSame($procedureSettings->getCopyright(), $mapOptions->getCopyright());
    }
}
