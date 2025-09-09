<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementGeoService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use Tests\Base\FunctionalTestCase;

class StatementGeoServiceTest extends FunctionalTestCase
{
    /**
     * @var StatementGeoService
     */
    protected $sut;

    /** @var DraftStatementService */
    protected $serviceDraftStatement;

    /** @var User */
    protected $testUser;
    /**
     * @var StatementService
     */
    protected $statementService;

    public function setUp(): void
    {
        parent::setUp();

        $this->serviceDraftStatement = self::getContainer()->get(DraftStatementService::class);
        $this->statementService = self::getContainer()->get(StatementService::class);
        $this->testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->sut = self::getContainer()->get(StatementGeoService::class);
    }

    public function testAddGeoMultiPointData()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[554396.5475077746,5985505.971244295]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555093.2545886636,5984790.176298177]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555904.4888609317,5985257.828996307]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555904.4888609317,5985257.828996307]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR3_STE_027', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Steinburg', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count()); // Dies
        static::assertEquals('Willenscharen', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoSinglePointData()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[538579.1493374573,5996968.456863991]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR2_RDE_140', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Rendsburg-Eckernförde', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Osterstedt', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoSinglePointDataNoArea()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[506397.24790658086,6047930.290051134]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals(0, $updatedStatements[0]->getPriorityAreas()->count());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoPointDataTwoPriorityAreas()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[483013.7153814754,6072658.255889328]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[519526.28840662143,6064085.738744293]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR1_NFL_035', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals(2, $updatedStatements[0]->getPriorityAreas()->count());
        static::assertEquals('Nordfriesland', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(2, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Galmsbüll', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(2, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoLineDataOnePriorityArea()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"LineString","coordinates":[[484159.54049823957,6070162.936586284],[485556.54329224513,6071200.105327288]]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);
        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR1_NFL_035', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Nordfriesland', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Galmsbüll', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoLineDataOnePriorityAreaNoPointInArea()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"LineString","coordinates":[[538324.5649842509,5996615.10880806],[538782.673749767,5997311.81588895]]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);
        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR2_RDE_140', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Rendsburg-Eckernförde', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Osterstedt', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoPolygonUeberlappend()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[553346.7149201335,5985477.339446451],[553881.1751465689,5984570.6658480335],[555417.7482975709,5985000.142815704],[554129.3173945568,5986193.13439257],[553346.7149201335,5985477.339446451]]]},"properties":null},{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[554329.7399794701,5984017.117756368],[554387.0035751596,5985353.268322457],[555818.5934673975,5985391.444052916],[556066.7357153854,5984761.544500331],[554329.7399794701,5984017.117756368]]]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR3_STE_027', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Steinburg', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Brokstedt', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(2, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoLinieKreuzend()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"LineString","coordinates":[[484159.54049823957,6070162.936586284],[485556.54329224513,6071200.105327288]]},"properties":null},{"type":"Feature","geometry":{"type":"LineString","coordinates":[[483983.15125657216,6071383.5501386225],[485620.0434192454,6070148.82544695]]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR1_NFL_035', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Nordfriesland', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Galmsbüll', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoMultipleData()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[489318.74930934317,6076044.929733065]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[506701.9090756627,6073346.174335554]},"properties":null},{"type":"Feature","geometry":{"type":"LineString","coordinates":[[484371.0310805734,6075542.220394313],[483630.1962655704,6073743.050129306]]},"properties":null},{"type":"Feature","geometry":{"type":"LineString","coordinates":[[507971.9116156678,6067419.49581553],[505378.98976315744,6067684.079678032]]},"properties":null},{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[509162.53899692255,6069192.2076942865],[510670.6670131786,6065964.284571773],[511570.25214568217,6066916.786476778],[509162.53899692255,6069192.2076942865]]]},"properties":null},{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[512681.50436818664,6067075.536794278],[511940.66955318366,6065488.033619272],[514692.3417231947,6065196.991370521],[512681.50436818664,6067075.536794278]]]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR1_NFL_021', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals(8, $updatedStatements[0]->getPriorityAreas()->count());
        static::assertEquals('Nordfriesland', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(2, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Holm', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(8, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddGeoHugePolygonData()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[442341.88209994265,6101656.645604552],[471551.9405200595,6080066.602424465],[547752.0929203643,6072446.587184435],[576538.8171604795,6056783.222524372],[613792.2250006285,6032229.840084273],[641308.9467007385,6047893.204744336],[674752.3469208723,6018683.146324219],[604902.2072205929,5906076.254443769],[541402.0802203389,5916236.2747638095],[458005.24676000525,5971269.7181640295],[442341.88209994265,6101656.645604552]]]},"properties":null},{"type":"Feature","geometry":{"type":"Polygon","coordinates":[[[458005.24676000525,5971269.7181640295],[422445.17563986307,5993283.095524118],[418635.1680198478,6022916.488124236],[458005.24676000525,5971269.7181640295]]]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        // nur diejenigen, die in den Feixtures geladen werden
        static::assertEquals(13, $updatedStatements[0]->getPriorityAreas()->count());
        static::assertEquals(4, $updatedStatements[0]->getCounties()->count());
        // alle, auch neu angelegte
        static::assertEquals(1079, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testAddPolygonException()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals(0, $updatedStatements[0]->getPriorityAreas()->count());
        static::assertEquals(0, $updatedStatements[0]->getCounties()->count());
        static::assertEquals(0, $updatedStatements[0]->getMunicipalities()->count());

        $data['polygon'] = false;
        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals(0, $updatedStatements[0]->getPriorityAreas()->count());
        static::assertEquals(0, $updatedStatements[0]->getCounties()->count());
        static::assertEquals(0, $updatedStatements[0]->getMunicipalities()->count());
    }

    public function testProcessGetAdditionalGeodata()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[554396.5475077746,5985505.971244295]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555093.2545886636,5984790.176298177]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555904.4888609317,5985257.828996307]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555904.4888609317,5985257.828996307]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statements = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statementsFetched = $this->statementService->processScheduledFetchGeoData();
        static::assertEquals(1, $statementsFetched);

        $statementsFetched2 = $this->statementService->processScheduledFetchGeoData();
        static::assertEquals(0, $statementsFetched2);
    }

    public function testSavePriorityAreaByName()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'polygon' => '{"type":"FeatureCollection","features":[{"type":"Feature","geometry":{"type":"Point","coordinates":[554396.5475077746,5985505.971244295]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555093.2545886636,5984790.176298177]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555904.4888609317,5985257.828996307]},"properties":null},{"type":"Feature","geometry":{"type":"Point","coordinates":[555904.4888609317,5985257.828996307]},"properties":null}]}',
        ];
        $data = $this->addDefaultData($data);

        $draftStatement = $this->serviceDraftStatement->addDraftStatement($data);
        $statementsArray = $this->serviceDraftStatement->submitDraftStatement($draftStatement['id'], $this->testUser);
        $statements = [];
        foreach ($statementsArray as $statement) {
            $statements[] = $this->statementService->getStatement($statement['ident']);
        }
        $updatedStatements = $this->sut->saveStatementGeoData($statements);
        static::assertEquals('PR3_STE_027', $updatedStatements[0]->getPriorityAreas()->first()->getKey());
        static::assertEquals('Steinburg', $updatedStatements[0]->getCounties()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getCounties()->count());
        static::assertEquals('Willenscharen', $updatedStatements[0]->getMunicipalities()->first()->getName());
        static::assertEquals(1, $updatedStatements[0]->getMunicipalities()->count());
    }

    private function addDefaultData(array $data)
    {
        return array_merge($data,
            [
                'pId'   => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
                'text'  => 'Mein Text',
                'uId'   => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
                'uName' => 'ein ganz anderer',
                'dId'   => $this->fixtures->getReference(LoadUserData::TEST_DEPARTMENT)->getId(),
                'dName' => $this->fixtures->getReference(LoadUserData::TEST_DEPARTMENT)->getName(),
                'oId'   => $this->fixtures->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getId(),
                'oName' => $this->fixtures->getReference(LoadUserData::TEST_ORGA_PUBLIC_AGENCY)->getName(),
            ]
        );
    }
}
