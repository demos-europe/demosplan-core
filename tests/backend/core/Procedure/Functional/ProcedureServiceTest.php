<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use Carbon\Carbon;
use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadCustomerData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureTypeData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureSettingsFactory;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Document\Paragraph;
use demosplan\DemosPlanCoreBundle\Entity\Document\ParagraphVersion;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocument;
use demosplan\DemosPlanCoreBundle\Entity\Document\SingleDocumentVersion;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Entity\ManualListSort;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayer;
use demosplan\DemosPlanCoreBundle\Entity\Map\GisLayerCategory;
use demosplan\DemosPlanCoreBundle\Entity\News\News;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Boilerplate;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateCategory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\BoilerplateGroup;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\HashedQuery;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\NotificationReceiver;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureSubscription;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\UserFilterSet;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Entity\Setting;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatementVersion;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\ProcedureNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\EntityHelper;
use demosplan\DemosPlanCoreBundle\Logic\FileService;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use Doctrine\ORM\ORMInvalidArgumentException;
use Exception;
use InvalidArgumentException;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class ProcedureServiceTest extends FunctionalTestCase
{
    /** @var ProcedureService */
    protected $sut;

    /** @var Procedure */
    protected $testProcedure;

    /** @var Session */
    protected $mockSession;

    /** @var MapService */
    protected $mapService;

    /** @var ReportService */
    protected $reportService;

    /** @var FileService|object|null */
    private $fileService;
    /**
     * @var EntityHelper
     */
    private $entityHelper;
    /**
     * @var GlobalConfigInterface
     */
    private $globalConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ProcedureService::class);
        $this->mapService = $this->getContainer()->get(MapService::class);
        $this->reportService = $this->getContainer()->get(ReportService::class);
        $this->fileService = $this->getContainer()->get(FileService::class);
        $this->globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);
        $this->testProcedure = $this->getTestProcedure();
        $this->entityHelper = new EntityHelper(new NullLogger());

        $this->loginTestUser();
    }

    public function testGetProcedureAdminList(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedureList = $this->sut->getProcedureAdminList(
            [],
            null,
            $user,
            null,
            false,
            true,
            false
        );
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals($this->countEntries(Procedure::class, ['master' => false]), $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));
        $procedureToTest = $procedureList['result'][0];
        $this->checkId($procedureToTest['ident']);
        static::assertArrayHasKey('externalName', $procedureToTest);
        static::assertArrayHasKey('settings', $procedureToTest);
        static::assertArrayHasKey('boundingBox', $procedureToTest['settings']);
        static::assertArrayHasKey('planningOffices', $procedureToTest);
        static::assertCount(1, $procedureToTest['planningOffices']);
        static::assertArrayHasKey('nameLegal', $procedureToTest['planningOffices'][0]);
    }

    /**
     * Test if the parameter $excludeArchived is working.
     */
    public function testProcedureListExcludeArchived(): void
    {
        $semiArchivedProcedureName = 'SemiArchivedProcedure';

        // Is there even one with both closed?
        $procedures = [];
        try {
            $procedures = $this->sut->getProcedureAdminList(
                [],
                null,
                $this->getUserReference(
                    LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY
                ),
                null,
                false,
                false,
                false
            );
        } catch (Exception $e) {
            $this->fail('Unable to test excludeArchived param on getProcedureAdminList(): '.$e);
        }
        $procedureToExcludeInFixturesExist = false;
        $semiArchivedProcedureExist = false;
        foreach ($procedures as $procedure) {
            if ('closed' === $procedure->getPhase() && 'closed' === $procedure->getPublicParticipationPhase()) {
                $procedureToExcludeInFixturesExist = true;
            }
            if ($procedure->getName() === $semiArchivedProcedureName) {
                $semiArchivedProcedureExist = true;
                $fixtureIsValidForTest = ('closed' === $procedure->getPhase() && 'configuration' === $procedure->getPublicParticipationPhase());
                $this->assertTrue($fixtureIsValidForTest, 'Fixture has been malformed.');
            }
        }
        $this->assertTrue($procedureToExcludeInFixturesExist, 'There is no procedure to exclude :D FAIL!!!');
        $this->assertTrue($semiArchivedProcedureExist, 'There is no semi archived procedure. Need this for this test.');

        // Do we actually exclude?
        // Also test default exclude :)
        $procedures = [];
        try {
            $procedures = $this->sut->getProcedureAdminList(
                [],
                null,
                $this->getUserReference(
                    LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY
                ),
                null,
                false,
                false
            );
        } catch (Exception $e) {
            $this->fail('Unable to test excludeArchived param on getProcedureAdminList() - Exception: '.$e);
        }
        $this->assertGreaterThan(0, count($procedures));
        $semiArchivedProcedureFound = false;
        foreach ($procedures as $procedure) {
            $areBothArchived = ('closed' === $procedure->getPhase() && 'closed' === $procedure->getPublicParticipationPhase());
            $this->assertFalse($areBothArchived, 'Procedure found, that should be excluded. Both phases are closed.');

            // Explicit check for a procedure with one closed. That one should be found
            if ($procedure->getName() === $semiArchivedProcedureName) {
                $semiArchivedProcedureFound = true;
            }
        }
        $this->assertTrue($semiArchivedProcedureFound, 'Procedures with one phase closed should be found, but are also excluded.');
    }

    public function testGetProcedureAdminListBlaupause(): void
    {
        $blaupause = $this->fixtures->getReference('masterBlaupause');
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedureList = $this->sut->getProcedureAdminList(
            [],
            null,
            $user,
            ['name' => 'desc'],
            true,
            true,
            false
        );
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);

        $numberOfMasterProcedures = $this->countEntries(Procedure::class, ['master' => true]);

        static::assertEquals($numberOfMasterProcedures, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));
        $procedureToTest = $procedureList['result'][count($procedureList['result']) - 1];
        $this->checkId($procedureToTest['ident']);
        static::assertArrayHasKey('externalName', $procedureToTest);
        static::assertEquals($procedureToTest['name'], $blaupause->getName());
        static::assertArrayHasKey('settings', $procedureToTest);
        static::assertArrayHasKey('boundingBox', $procedureToTest['settings']);
    }

    public function testGetProcedureAdminListPlanningOffice(): void
    {
        $user = $this->getUserReference('testUserPlanningOffice');
        $procedureList = $this->sut->getProcedureAdminList(
            [],
            null,
            $user,
            null,
            false,
            true,
            false
        );
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);

        foreach ($procedureList['result'] as $procedure) {
            $planningOfficeIds = $procedure['planningOfficesIds'];
            if (!in_array($user->getOrga()->getId(), $planningOfficeIds, true)) {
                $this->fail('Procedure found that is not assigned to this planning office.');
            }
        }
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));
        $procedureToTest = $procedureList['result'][0];
        $this->checkId($procedureToTest['ident']);
        static::assertArrayHasKey('externalName', $procedureToTest);
        static::assertArrayHasKey('settings', $procedureToTest);
        static::assertArrayHasKey('boundingBox', $procedureToTest['settings']);
    }

    public function testGetProcedureAdminListSearch(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedureList = $this->sut->getProcedureAdminList(
            [],
            'two',
            $user,
            null,
            false,
            true,
            false
        );
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(3, strlen($procedureList['search']));
        $procedureToTest = $procedureList['result'][0];
        $this->checkId($procedureToTest['ident']);
        static::assertArrayHasKey('externalName', $procedureToTest);
        static::assertArrayHasKey('settings', $procedureToTest);
        static::assertArrayHasKey('boundingBox', $procedureToTest['settings']);
    }

    public function testGetProcedureFullList(): void
    {
        $procedureList = $this->sut->getProcedureFullList();
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals($this->countEntries(Procedure::class, ['deleted' => false, 'master' => false]), $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));
        $procedureToTest = array_pop($procedureList['result']);
        $this->checkId($procedureToTest['ident']);
        static::assertArrayHasKey('externalName', $procedureToTest);
        static::assertArrayHasKey('settings', $procedureToTest);
        static::assertArrayHasKey('boundingBox', $procedureToTest['settings']);
    }

    public function testGetProcedurePublicList(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $filters = [
            'master'  => false, // keine Master Verfahren
            'deleted' => false, // keine gelöschten Verfahren
        ];
        $procedureList = $this->sut->getPublicList($filters, null, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));
        $procedureToTest = $procedureList['result'][0];
        $this->checkId($procedureToTest['ident']);
        static::assertArrayHasKey('externalName', $procedureToTest);
        static::assertArrayHasKey('settings', $procedureToTest);
        static::assertArrayHasKey('boundingBox', $procedureToTest['settings']);
    }

    public function testGetProcedurePublicListFilter(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $filters = [
            'master'                   => false, // keine Master Verfahren
            'deleted'                  => false, // keine gelöschten Verfahren
            'publicParticipationPhase' => ['participation'],
        ];
        $procedureList = $this->sut->getPublicList($filters, null, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));

        $filters = [
            'master'                   => false, // keine Master Verfahren
            'deleted'                  => false, // keine gelöschten Verfahren
            'publicParticipationPhase' => ['participation', 'earlyparticipation'],
        ];
        $procedureList = $this->sut->getPublicList($filters, null, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));

        $filters = [
            'master'                   => false, // keine Master Verfahren
            'deleted'                  => false, // keine gelöschten Verfahren
            'publicParticipationPhase' => ['closed', 'earlyparticipation'],
        ];
        $procedureList = $this->sut->getPublicList($filters, null, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(0, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));

        $filters = [
            'master'        => false, // keine Master Verfahren
            'deleted'       => false, // keine gelöschten Verfahren
            'municipalCode' => $this->testProcedure->getMunicipalCode(),
        ];
        $procedureList = $this->sut->getPublicList($filters, null, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));

        $filters = [
            'master'        => false, // keine Master Verfahren
            'deleted'       => false, // keine gelöschten Verfahren
            'municipalCode' => $this->testProcedure->getMunicipalCode(),
        ];
        $procedureList = $this->sut->getPublicList($filters, null, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals(0, strlen($procedureList['search']));
    }

    public function testGetProcedurePublicListSearch(): void
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $filters = [
            'master'  => false, // keine Master Verfahren
            'deleted' => false, // keine gelöschten Verfahren
        ];
        $search = 'procedure';
        $procedureList = $this->sut->getPublicList($filters, $search, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals($search, $procedureList['search']);

        $search = 'not Existant';
        $procedureList = $this->sut->getPublicList($filters, $search, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(0, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals($search, $procedureList['search']);

        $search = $this->testProcedure->getMunicipalCode();
        $procedureList = $this->sut->getPublicList($filters, $search, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals($search, $procedureList['search']);

        $search = 'location';
        $procedureList = $this->sut->getPublicList($filters, $search, null);
        $this->checkListResultStructure($procedureList);
        static::assertArrayHasKey('total', $procedureList);
        static::assertEquals(1, $procedureList['total']);
        static::assertIsString($procedureList['search']);
        static::assertEquals($search, $procedureList['search']);
    }

    /** @throws Exception */
    public function testGetSingleProcedure(): void
    {
        $procedure = $this->sut->getProcedure(
            $this->testProcedure->getId()
        );

        static::assertObjectHasProperty('orgaId', $procedure);
        static::assertIsString($procedure->getOrgaId());
        static::assertEquals($this->testProcedure->getOrgaId(), $procedure->getOrgaId());
        static::assertInstanceOf('\DateTime', $procedure->getClosedDate());
        static::assertIsNotString($procedure->getClosedDate());
        static::assertInstanceOf('\DateTime', $procedure->getPublicParticipationStartDate());
        static::assertIsNotString($procedure->getPublicParticipationStartDate());
        static::assertInstanceOf('\DateTime', $procedure->getPublicParticipationEndDate());
        static::assertIsNotString($procedure->getPublicParticipationEndDate());

        static::assertIsObject($procedure->getOrga());
        static::assertIsString($procedure->getOrga()->getName());

        static::assertIsObject($procedure->getSettings());
        static::assertIsString($procedure->getSettings()->getId());
        static::assertObjectHasProperty('planDrawPDF', $procedure->getSettings());
        static::assertObjectHasProperty('planPara1PDF', $procedure->getSettings());
        static::assertObjectHasProperty('planPara2PDF', $procedure->getSettings());
        static::assertObjectHasProperty('planPDF', $procedure->getSettings());

        static::assertIsIterable($procedure->getPlanningOffices());
        static::assertIsObject($procedure->getPlanningOffices()->first());
        $planningOffice = $procedure->getPlanningOffices()->first();
        static::assertInstanceOf(Orga::class, $planningOffice);
        static::assertIsString($planningOffice->getIdent());
        static::assertObjectHasProperty('name', $planningOffice);
        static::assertIsString($planningOffice->getNameLegal());

        static::assertObjectHasProperty('dataInputOrganisations', $procedure);
        $dataInputOrgas = $procedure->getDataInputOrganisations()->toArray();
        static::assertCount(1, $dataInputOrgas);
        static::assertEquals($this->fixtures->getReference('dataInputOrga')->getId(), $dataInputOrgas[0]->getId());
    }

    public function testGetSingleProcedureNotExistant(): void
    {
        $procedure = $this->sut->getSingleProcedure('I am not existant');
        // lustige legacy Rückgabewerte für ein nicht vorhandenes Verfahren
        static::assertIsArray($procedure);
        static::assertCount(4, $procedure);
        static::assertFalse($procedure['closed']);
        static::assertFalse($procedure['deleted']);
        static::assertFalse($procedure['master']);
        static::assertFalse($procedure['publicParticipation']);
    }

    /**
     * @throws Exception
     */
    public function testGetSingleProcedureOrgas(): void
    {
        $procedure = $this->sut->getSingleProcedure(
            $this->testProcedure->getId()
        );

        static::assertArrayHasKey('orgaId', $procedure);
        static::assertIsString($procedure['orgaId']);
        static::assertEquals($this->testProcedure->getOrgaId(), $procedure['orgaId']);
        static::assertInstanceOf('\DateTime', $procedure['closedDate']);
        static::assertIsNotString($procedure['closedDate']);

        static::assertIsArray($procedure['organisation']);
        static::assertIsString($procedure['organisation'][0]);

        static::assertIsArray($procedure['settings']);
        static::assertIsString($procedure['settings']['id']);
        static::assertArrayHasKey('planDrawPDF', $procedure['settings']);
        static::assertArrayHasKey('planPara1PDF', $procedure['settings']);
        static::assertArrayHasKey('planPara2PDF', $procedure['settings']);
        static::assertArrayHasKey('planPDF', $procedure['settings']);

        static::assertIsArray($procedure['planningOffices']);
        static::assertIsArray($procedure['planningOffices'][0]);
        $planningOffice = $procedure['planningOffices'][0];

        /** @var Orga $relatedPlanningoffice */
        $relatedPlanningoffice = $this->testProcedure->getPlanningOffices()[0];
        static::assertArrayHasKey('ident', $planningOffice);
        static::assertSame($relatedPlanningoffice->getId(), $planningOffice['ident']);

        static::assertArrayHasKey('name', $planningOffice);
        static::assertSame($relatedPlanningoffice->getName(), $planningOffice['name']);

        static::assertArrayHasKey('nameLegal', $planningOffice);
        static::assertSame($relatedPlanningoffice->getNameLegal(), $planningOffice['nameLegal']);
    }

    /**
     * Test general REsult structure.
     */
    protected function checkListResultStructure($procedureList)
    {
        static::assertIsArray($procedureList);
        static::assertCount(5, $procedureList);
        static::assertArrayHasKey('result', $procedureList);
        static::assertIsArray($procedureList['result']);
    }

    public function testAddProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedureMaster = $this->sut->getSingleProcedure(
            $this->fixtures->getReference('masterBlaupause')
        );

        $dateTime = new DateTime();
        $microTimestamp = $dateTime->getTimestamp() * 1000;
        $procedure = [
            'copymaster'                    => $this->fixtures->getReference('masterBlaupause')->getId(),
            'desc'                          => '',
            'startDate'                     => '01.02.2012',
            'endDate'                       => '01.02.2012',
            'externalName'                  => 'testAdded',
            'name'                          => 'testAdded',
            'master'                        => false,
            'orgaId'                        => $this->testProcedure->getOrgaId(),
            'orgaName'                      => $this->testProcedure->getOrga()->getName(),
            'logo'                          => 'some:logodata:string',
            'publicParticipationPhase'      => 'configuration',
            'procedureType'                 => $this->getReferenceProcedureType(LoadProcedureTypeData::BRK),
        ];
        $resultProcedure = $this->sut->addProcedureEntity(
            $procedure,
            $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId()
        );

        static::assertNotEquals($procedureMaster['startDate'], $resultProcedure->getStartDate());
        static::assertNotEquals($procedureMaster['endDate'], $resultProcedure->getEndDate());
        static::assertFalse($resultProcedure->getMaster());
        static::assertFalse($resultProcedure->isMasterTemplate());
        static::assertEquals('testAdded', $resultProcedure->getName());
        static::assertEquals('some:logodata:string', $resultProcedure->getLogo());
        static::assertEquals('configuration', $resultProcedure->getPublicParticipationPhase());
        // nearly current timestamp?
        static::assertTrue(3000 > ($resultProcedure->getStartDateTimestamp() - $microTimestamp));
        static::assertEquals('', $resultProcedure->getSettings()->getLinks());

        // Check, dass Blaupause noch da ist und nicht durch das neue Verfahren
        // versehentlich überschrieben wurde
        $procedureMaster = $this->sut->getProcedure(
            $this->fixtures->getReference('masterBlaupause')->getId()
        );

        static::assertEquals('Master', $procedureMaster->getName());

        $this->checkRelatedNews($resultProcedure->getId());
        $this->checkRelatedGis($resultProcedure->getId());
    }

    public function testCopyTagsFromBlueprint(): void
    {
        self::markSkippedForCIIntervention();

        $blueprint = $this->fixtures->getReference('masterBlaupause');

        $tags = $blueprint->getTags();
        $topics = $blueprint->getTopics();
        $tagsBefore = $tags->getValues();
        $topicsBefore = $topics->getValues();

        $procedure = [
            'copymaster'                => $blueprint,
            'desc'                      => '',
            'startDate'                 => '01.02.2012',
            'endDate'                   => '01.02.2012',
            'externalName'              => 'testAdded',
            'name'                      => 'testAdded',
            'master'                    => false,
            'orgaId'                    => $this->testProcedure->getOrgaId(),
            'orgaName'                  => $this->testProcedure->getOrga()->getName(),
            'logo'                      => 'some:logodata:string',
            'shortUrl'                  => 'myShortUrl',
            'publicParticipationPhase'  => 'configuration',
            'procedureType'             => $this->getReferenceProcedureType(LoadProcedureTypeData::BRK),
        ];

        $resultProcedure = $this->sut->addProcedureEntity($procedure, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());

        $resultTopics = $resultProcedure->getTopics()->getValues();
        static::assertCount($topics->count(), $resultTopics);

        $resultTags = $resultProcedure->getTags()->getValues();
        static::assertCount($tags->count(), $resultTags);

        static::assertEquals($topics->getValues(), $resultTopics);
        static::assertEquals($tags->getValues(), $resultTags);

        static::assertCount($blueprint->getTags()->count(), $tagsBefore);
        static::assertCount($blueprint->getTopics()->count(), $topicsBefore);
    }

    private function checkRelatedNews($procedureId)
    {
        $news = $this->sut->getDoctrine()->getRepository(News::class)
            ->findBy(['pId' => $procedureId]);

        $news3 = $this->fixtures->getReference('news3');

        static::assertEquals(2, sizeof($news));
        static::assertEquals($news3->getTitle(), $news[0]->getTitle());
        static::assertEquals($news3->getDescription(), $news[0]->getDescription());
        static::assertEquals($procedureId, $news[0]->getPId());
        static::assertEquals($news3->getPicture(), $news[0]->getPicture());
        static::assertEquals($news3->getPicTitle(), $news[0]->getPicTitle());
        static::assertEquals($news3->getPdf(), $news[0]->getPdf());
        static::assertEquals($news3->getPdfTitle(), $news[0]->getPdfTitle());
        static::assertEquals($news3->getEnabled(), $news[0]->getEnabled());
        static::assertEquals($news3->getDeleted(), $news[0]->getDeleted());
        static::assertEquals(sizeof($news3->getRoles()), sizeof($news[0]->getRoles()));
        static::assertTrue($this->isCurrentTimestamp($news[0]->getCreateDate()->getTimestamp() * 1000));
        static::assertTrue($this->isCurrentTimestamp($news[0]->getModifyDate()->getTimestamp() * 1000));
        static::assertTrue($this->isCurrentTimestamp($news[0]->getDeleteDate()->getTimestamp() * 1000));
    }

    private function checkRelatedGis($procedureId)
    {
        $gis = $this->sut->getDoctrine()->getRepository(GisLayer::class)
            ->findBy(['procedureId' => $procedureId]);

        $gisLayer4 = $this->fixtures->getReference('gisLayer4');

        static::assertEquals(2, sizeof($gis));
        static::assertEquals($gisLayer4->getName(), $gis[0]->getName());
        static::assertEquals($gisLayer4->getLayers(), $gis[0]->getLayers());
        static::assertEquals($procedureId, $gis[0]->getProcedureId());
        static::assertEquals($gisLayer4->getGId(), $gis[0]->getGId());
        static::assertEquals($gisLayer4->getGlobalLayerId(), $gis[0]->getGlobalLayerId());
        static::assertEquals($gisLayer4->getOpacity(), $gis[0]->getOpacity());
        static::assertEquals($gisLayer4->getOrder(), $gis[0]->getOrder());
        static::assertEquals($gisLayer4->getType(), $gis[0]->getType());
        static::assertEquals($gisLayer4->getUrl(), $gis[0]->getUrl());
        static::assertTrue($this->isCurrentTimestamp($gis[0]->getCreateDate()->getTimestamp() * 1000));
        static::assertTrue($this->isCurrentTimestamp($gis[0]->getModifyDate()->getTimestamp() * 1000));
        static::assertTrue($this->isCurrentTimestamp($gis[0]->getDeleteDate()->getTimestamp() * 1000));
    }

    /**
     * Known to fail.
     */
    public function testAuthorizedUsersOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();
        $relatedAuthorizedUserIds = $procedure->getAuthorizedUserIds();
        $amountBefore = count($relatedAuthorizedUserIds);

        $this->sut->purgeProcedure($procedureId);

        $users = $this->getEntriesWhereInIds(User::class, [$relatedAuthorizedUserIds]);

        // check for users are not deleted from system:
        static::assertCount($amountBefore, $users);
    }

    /**
     * Known to fail.
     */
    public function testNotificationReceiversOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();

        $notificationReceiverIds = $procedure->getNotificationReceivers()
            ->map(static function (NotificationReceiver $receiver) {
                return $receiver->getId();
            })->toArray();
        $amountBefore = count($notificationReceiverIds);

        // delete Procedure:
        $this->sut->purgeProcedure($procedureId);

        $notificationReceivers = $this->getEntriesWhereInIds(NotificationReceiver::class, [$notificationReceiverIds]);

        // check for notificationReceivers are not deleted from system:
        static::assertCount($amountBefore, $notificationReceivers);
    }

    /**
     * Known to fail.
     */
    public function testOrganisationOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();

        $organisation = $procedure->getOrga();
        $organisationId = $procedure->getOrgaId();
        static::assertInstanceOf(Orga::class, $organisation);

        $this->sut->purgeProcedure($procedureId);

        $foundOrganisations = $this->getEntries(Orga::class, ['id' => $organisationId]);
        static::assertIsArray($foundOrganisations);
        static::assertCount(1, $foundOrganisations);
        static::assertInstanceOf(Orga::class, $foundOrganisations[0]);
        static::assertEquals($organisationId, $foundOrganisations[0]->getId());
    }

    /**
     * TODO: known to fail.
     *
     * @throws Exception
     */
    public function testStatementsOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();

        $procedure->setDeleted(true);
        $this->loginTestUser();
        $this->sut->updateProcedureObject($procedure);

        $statementsOfProcedure = $this->getEntries(Statement::class, ['procedure' => $procedureId]);
        $amountOfAllStatements = $this->countEntries(Statement::class);
        $amountOfStatementsOfProcedure = $this->countEntries(Statement::class, ['procedure' => $procedureId]);
        $statementIds = $this->entityHelper->extractIds($statementsOfProcedure);

        $this->sut->purgeProcedure($procedureId);

        $foundStatements = $this->getEntries(Statement::class, ['id' => $statementIds]);
        static::assertIsArray($foundStatements);
        static::assertEmpty($foundStatements);

        // check total amount of Statements:
        static::assertEquals(
            $amountOfAllStatements - $amountOfStatementsOfProcedure,
            $amountOfAllStatements = $this->countEntries(Statement::class)
        );
    }

    /**
     * Known to fail.
     */
    public function testOriginalStatementsDeletedOnPurgeProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();

        /** @var Statement[] $originalStatementsOfProcedure */
        $originalStatementsOfProcedure = $this->getEntries(
            Statement::class,
            ['procedure' => $procedureId, 'original' => null]
        );
        static::assertIsArray($originalStatementsOfProcedure);
        static::assertInstanceOf(Statement::class, $originalStatementsOfProcedure[0]);
        foreach ($originalStatementsOfProcedure as $statement) {
            static::assertTrue($statement->isOriginal());
        }
        $statementIds = $this->entityHelper->extractIds($originalStatementsOfProcedure);

        $this->sut->purgeProcedure($procedureId);

        $foundStatements = $this->getEntriesWhereInIds(Statement::class, $statementIds);
        static::assertIsArray($foundStatements);
        static::assertEmpty($foundStatements);
    }

    /**
     * Known to fail.
     */
    public function testClusterStatementsDeletedOnPurgeProcedure(): void
    {
        self::markSkippedForCIIntervention();

        // todo: enhance test fixture data (statementCluster needed)
        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();

        /** @var Statement[] $originalStatementsOfProcedure */
        $clusterStatementsOfProcedure = $this->getEntries(
            Statement::class,
            ['procedure' => $procedureId, 'clusterStatement' => true]
        );
        static::assertIsArray($clusterStatementsOfProcedure);
        $statementIds = $this->entityHelper->extractIds($clusterStatementsOfProcedure);

        $this->sut->purgeProcedure($procedureId);

        $foundStatements = $this->getEntriesWhereInIds(Statement::class, $statementIds);
        static::assertIsArray($foundStatements);
        static::assertEmpty($foundStatements);
    }

    /**
     * Known to fail.
     *
     * huge tests to cover all relations of a proceudre. Incl. relations of relations
     * highest prio of this test is to check if there are related Entries which will WRONGLY deleted.
     */
    public function testPurgeProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = $this->sut->getProcedure($this->testProcedure->getId());
        $procedureId = $procedure->getId();
        static::assertInstanceOf(Procedure::class, $procedure);

        $relatedReports = $this->sut->getDoctrine()
            ->getRepository(ReportEntry::class)
            ->findBy(['identifier' => $procedure->getId()]);
        $relatedTopics = $this->getEntries(TagTopic::class, ['procedure' => $procedureId]);
        $relatedTags = $this->getEntries(Tag::class, ['procedure' => $procedureId]);
        $relatedSettings = $this->getEntries(Setting::class, ['procedure' => $procedureId]);

        $allRelatedStatements = $this->getEntries(Statement::class, ['procedure' => $procedureId]);
        $relatedOriginalStatements = $this->getEntries(
            Statement::class,
            ['procedure' => $procedureId, 'original' => null]
        );
        $relatedManualStatements = $this->getEntries(Statement::class, ['procedure' => $procedureId, 'manual' => true]);
        $relatedClusterStatements = $this->getEntries(
            Statement::class,
            ['procedure' => $procedureId, 'clusterStatement' => true]
        );
        $relatedPlaceholderStatements = $this->getEntries(
            Statement::class,
            ['procedure' => $procedureId, 'movedStatement' => !null]
        );
        $relatedStatements = $this->getEntries(Statement::class, ['procedure' => $procedureId]);
        $relatedDraftStatements = $this->getEntries(DraftStatement::class, ['procedure' => $procedureId]);
        $relatedDraftStatementsVersions = $this->getEntries(
            DraftStatementVersion::class,
            ['procedure' => $procedureId]
        );
        $relatedElements = $this->getEntries(Elements::class, ['procedure' => $procedureId]);
        $relatedReports = $this->getEntries(ReportEntry::class, ['identifier' => $procedureId]);
        $relatedManualSorts = $this->getEntries(ManualListSort::class, ['pId' => $procedureId]);
        $relatedParagraphs = $this->getEntries(Paragraph::class, ['procedure' => $procedureId]);
        $relatedParagraphVersions = $this->getEntries(ParagraphVersion::class, ['procedure' => $procedureId]);
        $relatedDocuments = $this->getEntries(SingleDocument::class, ['procedure' => $procedureId]);
        $relatedDocumentVersions = $this->getEntries(SingleDocumentVersion::class, ['procedure' => $procedureId]);
        $relatedNews = $this->getEntries(News::class, ['pId' => $procedureId]);
        $relatedGis = $this->getEntries(GisLayer::class, ['procedureId' => $procedureId]);
        $relatedGisCategories = $this->getEntries(GisLayerCategory::class, ['procedure' => $procedureId]);
        $relatedBoilerplates = $this->getEntries(Boilerplate::class, ['procedure' => $procedureId]);
        $relatedBoilerplateCategories = $this->getEntries(BoilerplateCategory::class, ['procedure' => $procedureId]);
        $relatedFilterSets = $this->getEntries(HashedQuery::class, ['procedure' => $procedureId]);
        $relatedUserFilterSets = $this->getEntries(UserFilterSet::class, ['procedure' => $procedureId]);
        $relatedStatementFragments = $this->getEntries(StatementFragment::class, ['procedure' => $procedureId]);

        // check cluster
        // check headstatement
        // check placeholderstatement
        // check parent statement
        // check originalstatement
        // check movedStatement
        // check user + orga of statement
        // check element of statement
        // check assignee of statement

        // check orgas, users, amount of counties, usw...

        $relatedPriorityAreas = [];
        $relatedCounties = [];
        $relatedMunicipalities = [];

        foreach ($relatedStatements as $relatedStatement) {
            $relatedPriorityAreas = $relatedStatement->getPriorityAreas();
            $relatedCounties = $relatedStatement->getCounties();
            $relatedMunicipalities = $relatedStatement->getMunicipalities();
        }

        static::assertNotEmpty($relatedPriorityAreas);
        static::assertNotEmpty($relatedCounties);
        static::assertNotEmpty($relatedMunicipalities);
        static::assertNotEmpty($relatedElements);
        static::assertNotEmpty($relatedReports);
        static::assertNotEmpty($relatedStatements);
        static::assertNotEmpty($relatedDraftStatements);
        static::assertNotEmpty($relatedDraftStatementsVersions);
        static::assertNotEmpty($relatedManualSorts);
        static::assertNotEmpty($relatedParagraphs);
        static::assertNotEmpty($relatedParagraphVersions);
        static::assertNotEmpty($relatedDocuments);
        static::assertNotEmpty($relatedDocumentVersions);
        static::assertNotEmpty($relatedGis);
        static::assertNotEmpty($relatedNews);

        $this->sut->purgeProcedure($procedureId);

        $em = self::$container->get('doctrine.orm.default_entity_manager');
        $em->clear();

        $procedureDeleted = $this->sut->getSingleProcedure($procedureId);
        static::assertIsArray($procedureDeleted);
        static::assertCount(4, $procedureDeleted);
        static::assertFalse($procedureDeleted['closed']);
        static::assertFalse($procedureDeleted['deleted']);
        static::assertFalse($procedureDeleted['master']);
        static::assertFalse($procedureDeleted['publicParticipation']);

        $relatedElementsAfter = $this->getEntries(Elements::class, ['procedure' => $procedureId]);
        $relatedReportsAfter = $this->getEntries(ReportEntry::class, ['identifier' => $procedureId]);
        $relatedDraftStatementsAfter = $this->getEntries(DraftStatement::class, ['procedure' => $procedureId]);
        $relatedDraftStatementsVersionsAfter = $this->getEntries(
            DraftStatementVersion::class,
            ['procedure' => $procedureId]
        );
        $relatedManualSortsAfter = $this->getEntries(ManualListSort::class, ['pId' => $procedureId]);
        $relatedParagraphsAfter = $this->getEntries(Paragraph::class, ['procedure' => $procedureId]);
        $relatedParagraphVersionsAfter = $this->getEntries(ParagraphVersion::class, ['procedure' => $procedureId]);
        $relatedDocumentsAfter = $this->getEntries(SingleDocument::class, ['procedure' => $procedureId]);
        $relatedDocumentVersionsAfter = $this->getEntries(SingleDocumentVersion::class, ['procedure' => $procedureId]);
        $relatedGisAfter = $this->getEntries(GisLayer::class, ['procedureId' => $procedureId]);
        $relatedGisCategoriesAfter = $this->getEntries(GisLayerCategory::class, ['procedure' => $procedureId]);
        $relatedNewsAfter = $this->getEntries(News::class, ['pId' => $procedureId]);
        $relatedStatementsAfter = $this->getEntries(Statement::class, ['procedure' => $procedureId]);
        $relatedBoilerplatesAfter = $this->getEntries(Boilerplate::class, ['procedure' => $procedureId]);
        $relatedBoilerplateCategoriesAfter = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $procedureId]
        );
        $relatedFilterSetsAfter = $this->getEntries(HashedQuery::class, ['procedure' => $procedureId]);
        $relatedUserFilterSetsAfter = $this->getEntries(UserFilterSet::class, ['procedure' => $procedureId]);
        $relatedStatementFragmentsAfter = $this->getEntries(StatementFragment::class, ['procedure' => $procedureId]);

        $relatedPriorityAreasAfter = [];
        $relatedCountiesAfter = [];
        $relatedMunicipalitiesAfter = [];

        foreach ($relatedStatementsAfter as $relatedStatementAfter) {
            $relatedPriorityAreasAfter = $relatedStatementAfter->getPriorityAreas();
            $relatedCountiesAfter = $relatedStatementAfter->getCounties();
            $relatedMunicipalitiesAfter = $relatedStatementAfter->getMunicipalities();
        }

        static::assertEmpty($relatedPriorityAreasAfter);
        static::assertEmpty($relatedCountiesAfter);
        static::assertEmpty($relatedMunicipalitiesAfter);
        static::assertEmpty($relatedElementsAfter);
        static::assertEmpty($relatedReportsAfter);
        static::assertEmpty($relatedStatementsAfter);
        static::assertEmpty($relatedDraftStatementsAfter);
        static::assertEmpty($relatedDraftStatementsVersionsAfter);
        static::assertEmpty($relatedManualSortsAfter);
        static::assertEmpty($relatedParagraphsAfter);
        static::assertEmpty($relatedParagraphVersionsAfter);
        static::assertEmpty($relatedDocumentsAfter);
        static::assertEmpty($relatedDocumentVersionsAfter);
        static::assertEmpty($relatedGisAfter);
        static::assertEmpty($relatedNewsAfter);
    }

    public function testDeleteProcedure(): void
    {
        $testProcedure = $this->fixtures->getReference(LoadProcedureData::TEST_PROCEDURE_2);
        $procedure = $this->sut->getSingleProcedure(
            $testProcedure->getId()
        );
        static::assertArrayHasKey('orgaId', $procedure);
        static::assertIsString($procedure['orgaId']);

        $this->sut->deleteProcedure([$procedure['id']]);

        $procedureDeleted = $this->sut->getSingleProcedure($procedure['id']);
        static::assertIsArray($procedureDeleted);
        static::assertTrue($procedureDeleted['deleted']);
        static::assertFalse($procedureDeleted['closed']);
    }

    /**
     * @dataProvider exceptionDeleteProvider
     *
     * @param string $id ID of the {@link Procedure} to purge
     *
     * @throws Exception
     */
    public function testDeleteProcedureFail(?string $id): void
    {
        $this->expectException(ProcedureNotFoundException::class);

        $this->sut->purgeProcedure($id);
        fail('Expected exception');
    }

    public function exceptionDeleteProvider()
    {
        return [
            [''],
            ['abcde'],
        ];
    }

    public function testAddProcedureDataMissing(): void
    {
        self::markSkippedForCIIntervention();

        $procedure = [
            'copymaster'                => $this->fixtures->getReference('masterBlaupause'),
            'master'                    => false,
            'externalName'              => 'testAdded',
            'name'                      => 'testAdded',
            'orgaId'                    => $this->testProcedure->getOrgaId(),
            'orgaName'                  => $this->testProcedure->getOrga()->getName(),
            'publicParticipationPhase'  => 'configuration',
            'procedureType'             => $this->getReferenceProcedureType(LoadProcedureTypeData::BRK),
        ];
        $resultProcedure = $this->sut->addProcedureEntity($procedure, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
        static::assertEquals('testAdded', $resultProcedure->getName());
    }

    /**
     * @dataProvider exceptionAddProvider
     *
     * @throws Exception
     */
    public function testAddProcedureException($data)
    {
        $this->expectException(Exception::class);

        $this->sut->addProcedureEntity($data, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
    }

    public function exceptionAddProvider()
    {
        return [
            [[]],
            [['copymaster' => '']],
            [['copymaster' => null]],
            [['copymaster' => false]],
            [['copymaster' => []]],
        ];
    }

    /**
     * @throws Exception
     */
    public function testUpdateProcedureObject(): void
    {
        self::markSkippedForCIIntervention();

        $currentDate = new DateTime();
        $settings = $this->testProcedure->getSettings();
        $settings
            ->setProcedure($this->testProcedure)
            ->setCoordinate('577380.68163195,5949764.0961163')
            ->setBoundingBox('5.3,3.54,5.6,5.6')
            ->setEmailCc('a@b.de, b@c.de')
            ->setEmailTitle('newEmail Title')
            ->setEmailText('newEmail Text')
            ->setPlanDrawText('planDraw Text')
            ->setPlanDrawPDF('{planDrawPDF}')
            ->setPlanText('08.01.2015')
            ->setPlanPDF('{planPDF}')
            ->setPlanPara1PDF('{planPara1PDF}')
            ->setPlanPara2PDF('{planPara2PDF}')
            ->setLinks('<a href="about:blank">link</a>');

        $procedureToUpdate = $this->testProcedure;
        $procedureToUpdate
            ->setName('Ein neues Testverfahren 1')
            ->setDesc('')
            ->setPhase('participation')
            ->setClosed(false)
            ->setStartDate($currentDate)
            ->setEndDate($currentDate)
            ->setExternalName('Ein neues Testverfahren')
            ->setExternalDesc('Es geht um die Neubebauung von Hoisbüttel')
            ->setPublicParticipationContact(
                'Frau Musterfrau
Tel.
Email:'
            )
            ->setLocationName('Ammersbek')
            ->setLocationPostCode('k.A.')
            ->setPublicParticipationPhase('earlyparticipation')
            ->setPublicParticipationPhase(true)
            ->setPublicParticipationStartDate($currentDate)
            ->setPublicParticipationEndDate($currentDate)
            ->setMunicipalCode('01062')
            ->setSettings($settings);

        $procedure = $this->sut->updateProcedureObject($procedureToUpdate);
        static::assertIsString($procedure->getOrgaId());
        static::assertSame('Ammersbek', $procedure->getLocationName());
        $resultProcedureSettings = $procedure->getSettings();
        static::assertSame('5.3,3.54,5.6,5.6', $resultProcedureSettings->getBoundingBox());
        static::assertSame('577380.68163195,5949764.0961163', $resultProcedureSettings->getCoordinate());
        static::assertSame('a@b.de, b@c.de', $resultProcedureSettings->getEmailCc());
        static::assertSame('newEmail Title', $resultProcedureSettings->getEmailTitle());
        static::assertSame('newEmail Text', $resultProcedureSettings->getEmailText());
        static::assertSame('planDraw Text', $resultProcedureSettings->getPlanDrawText());
        static::assertSame('{planDrawPDF}', $resultProcedureSettings->getPlanDrawPDF());
        static::assertSame('08.01.2015', $resultProcedureSettings->getPlanText());
        static::assertSame('{planPDF}', $resultProcedureSettings->getPlanPDF());
        static::assertSame('{planPara1PDF}', $resultProcedureSettings->getPlanPara1PDF());
        static::assertSame('{planPara2PDF}', $resultProcedureSettings->getPlanPara2PDF());
        static::assertSame('<a href="about:blank">link</a>', $resultProcedureSettings->getLinks());
    }

    public function testUpdateProcedure(): void
    {
        $data = [
            'ident'                        => $this->testProcedure->getId(),
            'name'                         => 'Ein neues Testverfahren 1',
            'desc'                         => '',
            'phase'                        => 'participation',
            'closed'                       => false,
            'startDate'                    => '05.02.2015',
            'endDate'                      => '26.02.2015',
            'externalName'                 => 'Ein neues Testverfahren',
            'externalDesc'                 => 'Es geht um die Neubebauung von Hoisbüttel',
            'publicParticipationContact'   => 'Frau Musterfrau
Tel.
Email:',
            'locationName'                 => 'Ammersbek',
            'locationPostCode'             => 'k.A.',
            'publicParticipationPhase'     => 'earlyparticipation',
            'publicParticipation'          => true,
            'publicParticipationStartDate' => '',
            'publicParticipationEndDate'   => '',
            'settings'                     => [
                'coordinate'   => '577380.68163195,5949764.0961163',
                'boundingBox'  => '5.3,3.54,5.6,5.6',
                'emailCc'      => 'a@b.de, b@c.de',
                'emailTitle'   => 'newEmail Title',
                'emailText'    => 'newEmail Text',
                'planDrawText' => 'planDraw Text',
                'planDrawPDF'  => '{planDrawPDF}',
                'planText'     => '08.01.2015',
                'planPDF'      => '{planPDF}',
                'planPara1PDF' => '{planPara1PDF}',
                'planPara2PDF' => '{planPara2PDF}',
                'links'        => '<a href="about:blank">link</a>',
            ],
            'municipalCode'                => '01062',
        ];

        $procedure = $this->sut->updateProcedure($data);

        static::assertIsArray($procedure);
        static::assertArrayHasKey('orgaId', $procedure);
        static::assertIsString($procedure['orgaId']);
        static::assertArrayHasKey('locationName', $procedure);
        static::assertEquals('Ammersbek', $procedure['locationName']);
        static::assertArrayHasKey('settings', $procedure);
        static::assertIsArray($procedure['settings']);
        static::assertArrayHasKey('boundingBox', $procedure['settings']);
        static::assertEquals('5.3,3.54,5.6,5.6', $procedure['settings']['boundingBox']);
        static::assertArrayHasKey('coordinate', $procedure['settings']);
        static::assertEquals('577380.68163195,5949764.0961163', $procedure['settings']['coordinate']);
        static::assertArrayHasKey('emailCc', $procedure['settings']);
        static::assertEquals('a@b.de, b@c.de', $procedure['settings']['emailCc']);
        static::assertArrayHasKey('emailTitle', $procedure['settings']);
        static::assertEquals('newEmail Title', $procedure['settings']['emailTitle']);
        static::assertArrayHasKey('emailText', $procedure['settings']);
        static::assertEquals('newEmail Text', $procedure['settings']['emailText']);
        static::assertArrayHasKey('planDrawText', $procedure['settings']);
        static::assertEquals('planDraw Text', $procedure['settings']['planDrawText']);
        static::assertArrayHasKey('planDrawPDF', $procedure['settings']);
        static::assertEquals('{planDrawPDF}', $procedure['settings']['planDrawPDF']);
        static::assertArrayHasKey('planText', $procedure['settings']);
        static::assertEquals('08.01.2015', $procedure['settings']['planText']);
        static::assertArrayHasKey('planPDF', $procedure['settings']);
        static::assertEquals('{planPDF}', $procedure['settings']['planPDF']);
        static::assertArrayHasKey('planPara1PDF', $procedure['settings']);
        static::assertEquals('{planPara1PDF}', $procedure['settings']['planPara1PDF']);
        static::assertArrayHasKey('planPara2PDF', $procedure['settings']);
        static::assertEquals('{planPara2PDF}', $procedure['settings']['planPara2PDF']);
        static::assertArrayHasKey('links', $procedure['settings']);
        static::assertEquals('<a href="about:blank">link</a>', $procedure['settings']['links']);
    }

    public function testUpdateTerritory(): void
    {
        $data = [
            'ident'    => $this->testProcedure->getId(),
            'settings' => [
                'territory' => 'POINT(ABCDE)',
            ],
        ];
        $procedure = $this->sut->updateProcedure($data);
        static::assertIsArray($procedure);
        static::assertArrayHasKey('orgaId', $procedure);
        static::assertIsString($procedure['orgaId']);
        static::assertArrayHasKey('settings', $procedure);
        static::assertIsArray($procedure['settings']);
        static::assertEquals('POINT(ABCDE)', $procedure['settings']['territory']);
    }

    public function testUpdateProcedureException(): void
    {
        self::markSkippedForCIIntervention();

        $this->expectException(Exception::class);

        // $data['ident'] is missing
        $data = [
            'settings' => [
                'territory' => 'POINT(ABCDE)',
            ],
        ];
        $this->sut->updateProcedure($data);
    }

    public function testUserinputTimeConverter(): void
    {
        $expected = DateTime::createFromFormat('d.m.Y H:i:s', '01.02.2012 02:00:00');
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('1.2.12')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('1.2.12')));
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('01.2.12')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('01.2.12')));
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('01.02.12')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('01.02.12')));
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('01.02.2012')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('01.02.2012')));
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('1.2.2012')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('1.2.2012')));
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('1.02.2012')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('1.02.2012')));
        static::assertEquals('01.02.2012 02:00:00', $this->sut->internalMakeUserInputDateTestable('1.02.12')->format('d.m.Y H:i:s'));
        static::assertEquals(0, $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('1.02.12')));

        $expected = DateTime::createFromFormat('d.m.Y H:i:s', '01.02.2012 04:00:00');
        static::assertEquals(
            '01.02.2012 04:00:00',
            $this->sut->internalMakeUserInputDateTestable('1.2.12', '04:00:00')->format('d.m.Y H:i:s')
        );
        static::assertEquals(
            0,
            $this->getSecondsDiff($expected, $this->sut->internalMakeUserInputDateTestable('1.2.12', '04:00:00'))
        );

        static::assertNull($this->sut->internalMakeUserInputDateTestable('02.12'));
        static::assertNull($this->sut->internalMakeUserInputDateTestable('1.02.12.4'));
        static::assertNull($this->sut->internalMakeUserInputDateTestable('001.02.12'));
        static::assertNull($this->sut->internalMakeUserInputDateTestable(''));
        static::assertNull($this->sut->internalMakeUserInputDateTestable(null));
        static::assertNull($this->sut->internalMakeUserInputDateTestable(false));
        static::assertNull($this->sut->internalMakeUserInputDateTestable([]));
    }

    protected function getSecondsDiff(DateTime $a, DateTime $b)
    {
        return strtotime($a->format('d.m.Y H:i:s')) - strtotime($b->format('d.m.Y H:i:s'));
    }

    /**
     * @throws Exception
     */
    public function testAddOrgaToProcedure(): void
    {
        /** @var Procedure $testBlueprint */
        $testBlueprint = $this->fixtures->getReference('masterBlaupause');
        /** @var Orga $testOrganisation */
        $testOrganisation = $this->fixtures->getReference('testOrgaPB');

        $this->sut->addOrganisations($testBlueprint, [$testOrganisation]);
        // if no exception was thrown we're fine

        // to successfully test execution, at least one assertion is required:
        static::assertContains($testOrganisation->getId(), $testBlueprint->getOrganisationIds());
    }

    /**
     * @throws Exception
     */
    public function testDeleteOrgaFromProcedure(): void
    {
        /** @var Procedure $testBlueprint */
        $testBlueprint = $this->fixtures->getReference('masterBlaupause');

        $this->sut->addOrganisations($testBlueprint, [$this->testProcedure->getOrga()]);

        $deleted = $this->sut->detachOrganisation($testBlueprint, $this->testProcedure->getOrga());
        static::assertTrue($deleted);
    }

    public function testAddSingleDataInputOrgaToProcedure(): void
    {
        $data = [
            'ident'         => $this->testProcedure->getId(),
            'dataInputOrga' => [
                $this->fixtures->getReference('dataInputOrga')->getId(),
            ],
        ];

        $procedure = $this->sut->updateProcedure($data);
        static::assertArrayHasKey('dataInputOrganisations', $procedure);
        $dataInputOrgas = $procedure['dataInputOrganisations']->toArray();
        static::assertCount(1, $dataInputOrgas);
        static::assertEquals($this->fixtures->getReference('dataInputOrga')->getId(), $dataInputOrgas[0]->getId());
        static::assertArrayHasKey('dataInputOrgaIds', $procedure);
        static::assertCount(1, $procedure['dataInputOrgaIds']);
        static::assertEquals(
            $this->fixtures->getReference('dataInputOrga')->getId(),
            $procedure['dataInputOrgaIds'][0]
        );
    }

    public function testAddMultipleDataInputOrgaToProcedure(): void
    {
        $data = [
            'ident'         => $this->testProcedure->getId(),
            'dataInputOrga' => [
                $this->fixtures->getReference('dataInputOrga')->getId(),
                $this->fixtures->getReference('dataInputOrga2')->getId(),
            ],
        ];

        $procedure = $this->sut->updateProcedure($data);
        static::assertArrayHasKey('dataInputOrganisations', $procedure);
        $dataInputOrgas = $procedure['dataInputOrganisations']->toArray();
        static::assertCount(2, $dataInputOrgas);
        static::assertEquals($this->fixtures->getReference('dataInputOrga')->getId(), $dataInputOrgas[0]->getId());
        static::assertEquals($this->fixtures->getReference('dataInputOrga2')->getId(), $dataInputOrgas[1]->getId());
    }

    public function testDeleteOneDataInputOrgaFromProcedure(): void
    {
        $data = [
            'ident'         => $this->testProcedure->getId(),
            'dataInputOrga' => [
                $this->fixtures->getReference('dataInputOrga')->getId(),
                $this->fixtures->getReference('dataInputOrga2')->getId(),
            ],
        ];

        $procedure = $this->sut->updateProcedure($data);
        static::assertArrayHasKey('dataInputOrganisations', $procedure);
        static::assertCount(2, $procedure['dataInputOrganisations']->toArray());

        $data = [
            'ident'         => $this->testProcedure->getId(),
            'dataInputOrga' => [
                $this->fixtures->getReference('dataInputOrga2')->getId(),
            ],
        ];

        $procedure = $this->sut->updateProcedure($data);
        static::assertArrayHasKey('dataInputOrganisations', $procedure);
        $dataInputOrgas = $procedure['dataInputOrganisations']->toArray();
        static::assertCount(1, $dataInputOrgas);
        static::assertEquals($this->fixtures->getReference('dataInputOrga2')->getId(), $dataInputOrgas[0]->getId());
    }

    public function testDeleteAllDataInputOrgasFromProcedure(): void
    {
        $data = [
            'ident'         => $this->testProcedure->getId(),
            'dataInputOrga' => [
                $this->fixtures->getReference('dataInputOrga')->getId(),
                $this->fixtures->getReference('dataInputOrga2')->getId(),
            ],
        ];

        $procedure = $this->sut->updateProcedure($data);
        static::assertArrayHasKey('dataInputOrganisations', $procedure);
        static::assertCount(2, $procedure['dataInputOrganisations']->toArray());

        $data = [
            'ident'         => $this->testProcedure->getId(),
            'dataInputOrga' => [],
        ];

        $procedure = $this->sut->updateProcedure($data);
        static::assertArrayHasKey('dataInputOrganisations', $procedure);
        $dataInputOrgas = $procedure['dataInputOrganisations']->toArray();
        static::assertCount(0, $dataInputOrgas);
    }

    public function testGetDataInputOrgaAllowedProcedures(): void
    {
        $procedureList = $this->sut->getProceduresForDataInputOrga(
            $this->getOrgaReference(LoadUserData::DATA_INPUT_ORGA)->getId()
        );
        static::assertCount(1, $procedureList);
        static::assertEquals($this->getProcedureReference(LoadProcedureData::TESTPROCEDURE)->getId(), $procedureList[0]->getId());
    }

    public function testGetDataInputOrgaAllowedProceduresNone(): void
    {
        $procedureList = $this->sut->getProceduresForDataInputOrga(
            $this->getOrgaReference(LoadUserData::TEST_ORGA_PB)->getId()
        );
        static::assertCount(0, $procedureList);
    }

    public function testAddInvitableInstitutionMail(): void
    {
        self::markSkippedForCIIntervention();

        $this->sut->addInstitutionMail(
            $this->testProcedure->getId(),
            $this->testProcedure->getOrgaId(),
            'participation'
        );
    }

    public function testGetInvitableInstitutionMail(): void
    {
        $this->sut->addInstitutionMail(
            $this->testProcedure->getId(),
            $this->testProcedure->getOrgaId(),
            'participation'
        );

        $invitableInstitutionMailList = $this->sut->getInstitutionMailList(
            $this->testProcedure->getId(),
            'participation'
        );
        $this->checkListResultStructure($invitableInstitutionMailList);
        static::assertArrayHasKey('result', $invitableInstitutionMailList);
        $entry = $invitableInstitutionMailList['result'][0];
        static::assertArrayHasKey('createdDate', $entry);
        static::assertArrayHasKey('ident', $entry);
        static::assertArrayHasKey('procedure', $entry);
        static::assertArrayHasKey('procedurePhase', $entry);
    }

    public function testGetInvitableInstitutionMailFail(): void
    {
        $this->sut->addInstitutionMail(
            $this->testProcedure->getId(),
            $this->testProcedure->getOrgaId(),
            'participation'
        );

        $invitableInstitutionMailList = $this->sut->getInstitutionMailList('', 'participation');
        $this->checkListResultStructure($invitableInstitutionMailList);
        static::assertArrayHasKey('result', $invitableInstitutionMailList);
        static::assertCount(0, $invitableInstitutionMailList['result']);

        $invitableInstitutionMailList = $this->sut->getInstitutionMailList(null, 'participation');
        $this->checkListResultStructure($invitableInstitutionMailList);
        static::assertArrayHasKey('result', $invitableInstitutionMailList);
        static::assertCount(0, $invitableInstitutionMailList['result']);

        $invitableInstitutionMailList = $this->sut->getInstitutionMailList([], 'participation');
        $this->checkListResultStructure($invitableInstitutionMailList);
        static::assertArrayHasKey('result', $invitableInstitutionMailList);
        static::assertCount(0, $invitableInstitutionMailList['result']);

        $invitableInstitutionMailList = $this->sut->getInstitutionMailList($this->testProcedure->getId(
        ),
            ''
        );
        $this->checkListResultStructure($invitableInstitutionMailList);
        static::assertArrayHasKey('result', $invitableInstitutionMailList);
        static::assertCount(0, $invitableInstitutionMailList['result']);

        $invitableInstitutionMailList = $this->sut->getInstitutionMailList($this->testProcedure->getId(
        ),
            null
        );
        $this->checkListResultStructure($invitableInstitutionMailList);
        static::assertArrayHasKey('result', $invitableInstitutionMailList);
        static::assertCount(0, $invitableInstitutionMailList['result']);
    }

    public function testGetListOfProceduresEndingSoon(): void
    {
        $daysToGo = 3;
        $limitForPhase = date('d.m.Y', time() + 60 * 60 * 24 * $daysToGo);

        $data = [
            'ident'   => $this->fixtures->getReference('testProcedure')->getId(),
            'endDate' => $limitForPhase,
        ];
        // update procedure that participation phase end in given time
        $this->sut->updateProcedure($data);

        // test the result
        $procedureList = $this->sut->getListOfProceduresEndingSoon($daysToGo);
        static::assertIsArray($procedureList);
        static::assertCount(1, $procedureList);
        static::assertInstanceOf(Procedure::class, $procedureList[0]);
        static::assertEquals(
            $this->fixtures->getReference('testProcedure')->getId(),
            $procedureList[0]->getId()
        );
        $procedureList = $this->sut->getListOfProceduresEndingSoon($daysToGo, false);
        static::assertCount(0, $procedureList);
    }

    public function testGetListOfPublicProceduresEndingSoon(): void
    {
        $daysToGo = 3;
        $limitForPhase = date('d.m.Y', time() + 60 * 60 * 24 * $daysToGo);

        $data = [
            'ident'                      => $this->fixtures->getReference('testProcedure')->getId(),
            'publicParticipationEndDate' => $limitForPhase,
        ];
        // update procedure that participation phase end in given time
        $this->sut->updateProcedure($data);

        // test the result
        $procedureList = $this->sut->getListOfProceduresEndingSoon($daysToGo, false);
        static::assertIsArray($procedureList);
        static::assertCount(1, $procedureList);
        static::assertInstanceOf(Procedure::class, $procedureList[0]);
        static::assertEquals(
            $this->fixtures->getReference('testProcedure')->getId(),
            $procedureList[0]->getId()
        );

        $procedureList = $this->sut->getListOfProceduresEndingSoon($daysToGo, true);
        static::assertCount(0, $procedureList);
    }

    public function testGetSubscriptionList(): void
    {
        self::markSkippedForCIIntervention();

        /*
                array (
                    'total' => 7,
                    'result' =>
                        array (
                            0 =>
                                array (
                                    'ident' => '52f36327-079f-423b-ae1d-85ed96ae5cfe',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '24103',
                                    'city' => 'Kiel',
                                    'distance' => 5,
                                    'deleted' => false,
                                    'createdDate' => 1424857419000,
                                    'deletedDate' => 1424857482000,
                                    'modifiedDate' => 1424857482000,
                                ),
                            1 =>
                                array (
                                    'ident' => '910af02d-9bdb-4682-81e7-96e96657fc4e',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '20038',
                                    'city' => 'Hamburg',
                                    'distance' => 5,
                                    'deleted' => false,
                                    'createdDate' => 1424692845000,
                                    'deletedDate' => 1424692905000,
                                    'modifiedDate' => 1424692905000,
                                ),
                            2 =>
                                array (
                                    'ident' => '9c614dd2-ed1c-42f6-911f-b976f1b6ed45',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '12345',
                                    'city' => 'refe',
                                    'distance' => 5,
                                    'deleted' => false,
                                    'createdDate' => 1453890560000,
                                    'deletedDate' => 1453890580000,
                                    'modifiedDate' => 1453890580000,
                                ),
                            3 =>
                                array (
                                    'ident' => '9dc1f942-4382-4f62-bed1-405748593248',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '12345',
                                    'city' => 'refe',
                                    'distance' => 5,
                                    'deleted' => false,
                                    'createdDate' => 1453890515000,
                                    'deletedDate' => 1453890535000,
                                    'modifiedDate' => 1453890535000,
                                ),
                            4 =>
                                array (
                                    'ident' => 'a8bce36f-2b4b-4775-9395-93e15ef17e1b',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '24937',
                                    'city' => 'Flensburg',
                                    'distance' => 50,
                                    'deleted' => false,
                                    'createdDate' => 1424857427000,
                                    'deletedDate' => 1424857490000,
                                    'modifiedDate' => 1424857490000,
                                ),
                            5 =>
                                array (
                                    'ident' => 'bd223d49-c1e4-422f-bb97-d89a50b325ff',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '12345',
                                    'city' => 'refe',
                                    'distance' => 5,
                                    'deleted' => false,
                                    'createdDate' => 1453891428000,
                                    'deletedDate' => 1453891448000,
                                    'modifiedDate' => 1453891448000,
                                ),
                            6 =>
                                array (
                                    'ident' => 'c4ce8657-fe8f-47c8-97f7-c60b260d9a48',
                                    'userId' => '64d9ebd1-4877-4440-97b0-da687fab5e4f',
                                    'userEmail' => 'bernhardt@binary-objects.de',
                                    'postcode' => '12345',
                                    'city' => 'Kuckucksheim',
                                    'distance' => 5,
                                    'deleted' => false,
                                    'createdDate' => 1453885984000,
                                    'deletedDate' => 1453886003000,
                                    'modifiedDate' => 1453886003000,
                                ),
                        ),
                )
                    */
        $filter = ['user' => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)];
        $result = $this->sut->getSubscriptionList($filter);

        static::assertIsArray($result);
        static::assertArrayHasKey('total', $result);
        static::assertArrayHasKey('result', $result);
        static::assertIsArray($result['result']);
        static::assertCount(1, $result['result']);
        static::assertCount(11, $result['result'][0]);
        static::assertArrayHasKey('ident', $result['result'][0]);
        $this->checkId($result['result'][0]['ident']);
        static::assertArrayHasKey('userId', $result['result'][0]);
        $this->checkId($result['result'][0]['userId']);
        static::assertArrayHasKey('userEmail', $result['result'][0]);
        static::assertIsString($result['result'][0]['userEmail']);
        static::assertArrayHasKey('postcode', $result['result'][0]);
        static::assertIsString($result['result'][0]['postcode']);
        static::assertEquals(5, strlen($result['result'][0]['postcode']));
        static::assertArrayHasKey('city', $result['result'][0]);
        static::assertIsString($result['result'][0]['city']);
        static::assertArrayHasKey('distance', $result['result'][0]);
        static::assertTrue(is_integer($result['result'][0]['distance']));
        static::assertArrayHasKey('deleted', $result['result'][0]);
        static::assertIsBool($result['result'][0]['deleted']);
        static::assertArrayHasKey('createdDate', $result['result'][0]);
        $this->isTimestamp($result['result'][0]['createdDate']);
        static::assertArrayHasKey('deletedDate', $result['result'][0]);
        $this->isTimestamp($result['result'][0]['deletedDate']);
        static::assertArrayHasKey('modifiedDate', $result['result'][0]);
        $this->isTimestamp($result['result'][0]['modifiedDate']);
    }

    public function testGetSubscriptionListNoResult(): void
    {
        $result = $this->sut->getSubscriptionList(['user' => 'notExistantUserId']);

        static::assertIsArray($result);
        static::assertArrayHasKey('total', $result);
        static::assertEquals(0, $result['total']);
        static::assertArrayHasKey('result', $result);
        static::assertIsArray($result['result']);
        static::assertCount(0, $result['result']);
    }

    public function testAddSubscription(): void
    {
        $postcode = '12345';
        $city = 'teststadt';
        $distance = '5';
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $numberOfEntriesBefore = $this->countEntries(ProcedureSubscription::class);

        $result = $this->sut->addSubscription($postcode, $city, $distance, $user);
        $numberOfEntriesAfter = $this->countEntries(ProcedureSubscription::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore + 1);

        $this->checkId($result->getId());
        $this->checkId($result->getUserId());
        static::assertIsString($result->getUserEmail());
        static::assertEquals($postcode, $result->getPostcode());
        static::assertEquals($city, $result->getCity());
        static::assertEquals($distance, $result->getDistance());
        static::assertIsBool($result->getDeleted());
    }

    public function testAddSubscriptionWithEmptyParameters(): void
    {
        $this->expectException(ORMInvalidArgumentException::class);

        $this->sut->addSubscription('', '', '', new User());
    }

    public function testDeleteSubscription(): void
    {
        $ident = $this->fixtures->getReference('testProcedureSubscription2')->getIdent();
        $numberOfEntriesBefore = $this->countEntries(ProcedureSubscription::class);
        $result = $this->sut->deleteSubscription($ident);
        $numberOfEntriesAfter = $this->countEntries(ProcedureSubscription::class);
        static::assertEquals($numberOfEntriesAfter + 1, $numberOfEntriesBefore);
        static::assertTrue($result);
    }

    public function testDeleteSubscriptionWithEmptyParameters(): void
    {
        $result = $this->sut->deleteSubscription('');
        static::assertFalse($result);
    }

    public function testSetEndDate(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure4');
        $endDate = $procedure->getPublicParticipationEndDate();

        static::assertNotNull($endDate);
        $this->isCurrentTimestamp($endDate);

        $procedure->setPublicParticipationEndDate(new DateTime());
        $this->sut->updateProcedureObject($procedure);

        $newEndDate = $procedure->getPublicParticipationEndDate();
        static::assertNotNull($newEndDate);
        $this->isCurrentTimestamp($newEndDate);
        static::assertNotEquals($newEndDate, $endDate);
    }

    public function testSetAutoSwitch(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure4');
        $procedureSettings = $procedure->getSettings();

        $designatedPublicPhase = $procedureSettings->getDesignatedPublicPhase();
        static::assertNull($designatedPublicPhase);

        $designatedPhase = $procedureSettings->getDesignatedPhase();
        static::assertNull($designatedPhase);

        static::assertNull($procedureSettings->getDesignatedPublicSwitchDate());
        static::assertNull($procedureSettings->getDesignatedSwitchDate());

        $date1 = Carbon::now();
        $endDate = new DateTime();

        $phase = 'configuration';
        $date1->setDate(1999, 4, 4);
        $endDate->setDate(1999, 5, 5);

        $procedureData = $this->setAndUpdateAutoSwitchPublic(['id' => $procedure->getId()], $date1, $phase);
        $updatedProcedure = $this->sut->getProcedure($procedureData['id']);

        $setPhase = $updatedProcedure->getPublicParticipationPhaseObject()->getDesignatedPhase();
        static::assertSame($setPhase, $updatedProcedure->getSettings()->getDesignatedPublicPhase());
        $setSwitchDate = $updatedProcedure->getPublicParticipationPhaseObject()->getDesignatedSwitchDate();
        static::assertSame($setSwitchDate, $updatedProcedure->getSettings()->getDesignatedPublicSwitchDate());

        static::assertTrue($date1->isSameYear($setSwitchDate));
        static::assertTrue($date1->isSameMonth($setSwitchDate));
        static::assertTrue($date1->isSameDay($setSwitchDate));
        static::assertTrue($date1->isSameHour($setSwitchDate));
        static::assertTrue($date1->isSameSecond($setSwitchDate));
        static::assertEquals($phase, $setPhase);

        $date2 = Carbon::now();
        $phases = $this->getContainer()->get(GlobalConfigInterface::class)->getInternalPhaseKeys('write');
        $phase = $phases[0];
        $date2->setDate(1999, 3, 3);
        $endDate->setDate(1999, 4, 4);

        $updatedProcedure = $this->setAndUpdateAutoSwitch(['id' => $procedure->getId()], $date2, $phase);
        $updatedProcedure = $this->sut->getProcedure($updatedProcedure['id']);

        $setPhase = $updatedProcedure->getPhaseObject()->getDesignatedPhase();
        static::assertSame($setPhase, $updatedProcedure->getSettings()->getDesignatedPhase());
        $setSwitchDate = $updatedProcedure->getPhaseObject()->getDesignatedSwitchDate();
        static::assertSame($setSwitchDate, $updatedProcedure->getSettings()->getDesignatedSwitchDate());

        static::assertTrue($date2->isSameYear($setSwitchDate));
        static::assertTrue($date2->isSameMonth($setSwitchDate));
        static::assertTrue($date2->isSameDay($setSwitchDate));
        static::assertTrue($date2->isSameHour($setSwitchDate));
        static::assertTrue($date2->isSameSecond($setSwitchDate));
        static::assertEquals($phase, $setPhase);

        $updatedProcedure = $this->setAndUpdateAutoSwitchPublic(['id' => $procedure->getId()], null, null);
        $updatedProcedure = $this->sut->getProcedure($updatedProcedure['id']);
        $updatedProcedureSettings = $updatedProcedure->getSettings();
        static::assertEquals($procedure, $updatedProcedure);
        static::assertNull($updatedProcedureSettings->getDesignatedPublicSwitchDate());
        static::assertNull($updatedProcedureSettings->getDesignatedPublicPhase());
        static::assertFalse($this->sut->isAutoSwitchOfPublicPhasePossible($updatedProcedure));

        $updatedProcedure = $this->setAndUpdateAutoSwitch(['id' => $procedure->getId()], null, null);
        $updatedProcedure = $this->sut->getProcedure($updatedProcedure['id']);
        $updatedProcedureSettings = $updatedProcedure->getSettings();
        static::assertEquals($procedure, $updatedProcedure);
        static::assertNull($updatedProcedureSettings->getDesignatedSwitchDate());
        static::assertNull($updatedProcedureSettings->getDesignatedPhase());
        static::assertFalse($this->sut->isAutoSwitchOfPhasePossible($updatedProcedure));
    }

    public function testReportEntryOnSetAutoSwitch(): void
    {
        $procedure = $this->getProcedureReference('testProcedure4');
        $procedureSettings = $procedure->getSettings();

        $designatedPublicPhase = $procedureSettings->getDesignatedPublicPhase();
        static::assertNull($designatedPublicPhase);
        static::assertNull($procedureSettings->getDesignatedPhase());
        static::assertNull($procedureSettings->getDesignatedPublicSwitchDate());
        static::assertNull($procedureSettings->getDesignatedSwitchDate());

        $phase = 'configuration';
        $date4 = Carbon::create(new DateTime());
        $date4->setDate(2029, 9, 9);

        static::assertNotEquals($procedure->getPublicParticipationPhaseObject()->getDesignatedPhase(), $phase);

        $updatedProcedureArray = $this->setAndUpdateAutoSwitchPublic(
            ['id' => $procedure->getId()],
            $date4->toDateTime(),
            $phase
        );
        $updatedProcedure = $this->sut->getProcedure($updatedProcedureArray['id']);

        static::assertEquals($procedure, $updatedProcedure);
        static::assertEquals($phase, $updatedProcedure->getSettings()->getDesignatedPublicPhase());

        static::assertTrue($date4->isSameYear($updatedProcedure->getSettings()->getDesignatedPublicSwitchDate()));
        static::assertTrue($date4->isSameMonth($updatedProcedure->getSettings()->getDesignatedPublicSwitchDate()));
        static::assertTrue($date4->isSameDay($updatedProcedure->getSettings()->getDesignatedPublicSwitchDate()));
        static::assertTrue($date4->isSameHour($updatedProcedure->getSettings()->getDesignatedPublicSwitchDate()));
        static::assertTrue($date4->isSameSecond($updatedProcedure->getSettings()->getDesignatedPublicSwitchDate()));

        static::assertEquals($updatedProcedure->getPublicParticipationPhaseObject()->getDesignatedPhase(), $phase);

        /** @var ReportEntry[] $entries */
        $entries = $this->getEntries(
            ReportEntry::class,
            [
                'category'       => ReportEntry::CATEGORY_UPDATE,
                'group'          => ReportEntry::GROUP_PROCEDURE,
                'identifierType' => ReportEntry::IDENTIFIER_TYPE_PROCEDURE,
                'identifier'     => $updatedProcedure->getId(),
            ],
        );

        static::assertCount(1, $entries);
        $message = $entries[0]->getMessageDecoded(false);
        $loggedDate = Carbon::createFromTimestamp($message['newDesignatedCitizenSwitchDate']);
        $loggedPhase = $message['newDesignatedCitizenPhase'];

        static::assertTrue($date4->isSameYear($loggedDate));
        static::assertTrue($date4->isSameMonth($loggedDate));
        static::assertTrue($date4->isSameDay($loggedDate));
        static::assertTrue($date4->isSameHour($loggedDate));
        static::assertTrue($date4->isSameSecond($loggedDate));
    }

    public function testSetPublicAutoSwitchInvalidPhase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure4');

        static::assertNull($procedure->getPublicParticipationPhaseObject()->getDesignatedPhase());
        static::assertNull($procedure->getPublicParticipationPhaseObject()->getDesignatedSwitchDate());

        $invalidPhase = 'blalbllbalab';
        $validDate = new DateTime();
        $validDate->setDate(1999, 4, 4);
        $validEndDate = new DateTime();
        $validEndDate->setDate(1999, 5, 5);
        $this->setAndUpdateAutoSwitch(['id' => $procedure->getId()], $validDate, $invalidPhase);
    }

    public function testSetAutoSwitchInvalidPhase(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure4');

        static::assertNull($procedure->getPhaseObject()->getDesignatedPhase());
        static::assertNull($procedure->getPhaseObject()->getDesignatedSwitchDate());

        $invalidPhase = 'blalbllbalab';
        $validDate = new DateTime();
        $validDate->setDate(1999, 4, 4);
        $validEndDate = new DateTime();
        $validEndDate->setDate(1999, 5, 5);
        $this->setAndUpdateAutoSwitchPublic(['id' => $procedure->getId()], $validDate, $invalidPhase);
    }

    public function testSetAutoSwitchInvalidDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure4');

        static::assertNull($procedure->getPhaseObject()->getDesignatedPhase());
        static::assertNull($procedure->getPhaseObject()->getDesignatedSwitchDate());

        $validPhase = 'configure';
        $invalidDate = 'someDate';
        $validEndDate = new DateTime();
        $validEndDate->setDate(1999, 5, 5);
        $this->setAndUpdateAutoSwitchPublic(['id' => $procedure->getId()], $invalidDate, $validPhase);
    }

    public function testSetPublicAutoSwitchInvalidDate(): void
    {
        $this->expectException(InvalidArgumentException::class);
        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure4');

        static::assertNull($procedure->getPublicParticipationPhaseObject()->getDesignatedPhase());
        static::assertNull($procedure->getPublicParticipationPhaseObject()->getDesignatedSwitchDate());

        $validPhase = 'configure';
        $invalidDate = 'someDate';
        $validEndDate = new DateTime();
        $validEndDate->setDate(1999, 5, 5);
        $this->setAndUpdateAutoSwitchPublic(['id' => $procedure->getId()], $validPhase, $invalidDate, $validEndDate, $this->mockSession);
    }

    public function testIsAutoSwitchPossible(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->getTestProcedure();
        static::assertTrue($this->sut->isAutoSwitchOfPhasePossible($procedure));
        static::assertTrue($this->sut->isAutoSwitchOfPublicPhasePossible($procedure));

        /** @var Procedure $procedure */
        $procedure4 = $this->fixtures->getReference('testProcedure4');
        static::assertFalse($this->sut->isAutoSwitchOfPhasePossible($procedure4));
        static::assertFalse($this->sut->isAutoSwitchOfPublicPhasePossible($procedure4));
    }

    /**
     * do not test the cronjob task, but the function.
     */
    public function testExecuteSwitchPhaseOfProcedure(): void
    {
        $procedure = $this->getTestProcedure();
        $procedureSettings = $procedure->getSettings();
        static::assertTrue($this->sut->isAutoSwitchOfPublicPhasePossible($procedure));
        static::assertTrue($this->sut->isAutoSwitchOfPhasePossible($procedure));
        $designatedPhase = $procedureSettings->getDesignatedPhase();
        $designatedPublicPhase = $procedureSettings->getDesignatedPublicPhase();

        $this->sut->switchToDesignatedPhase($procedure);
        $this->sut->switchToDesignatedPublicPhase($procedure);

        static::assertNull($procedureSettings->getDesignatedPhase());
        static::assertNull($procedureSettings->getDesignatedPublicPhase());
        static::assertFalse($this->sut->isAutoSwitchOfPhasePossible($procedure));
        static::assertFalse($this->sut->isAutoSwitchOfPublicPhasePossible($procedure));

        $updatedProcedure = $this->sut->getProcedure($procedure->getId());
        static::assertEquals($designatedPhase, $updatedProcedure->getPhase());
        static::assertEquals($designatedPublicPhase, $updatedProcedure->getPublicParticipationPhase());
    }

    public function testGetProceduresToSwitchOnDay(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->getTestProcedure();

        // check setup:
        $designatedSwitchDate = new Carbon($procedure->getSettings()->getDesignatedSwitchDate());
        $designatedEndDate = new Carbon($procedure->getSettings()->getDesignatedEndDate());
        static::assertSame(06, $designatedSwitchDate->hour);
        static::assertSame(30, $designatedSwitchDate->minute);
        static::assertSame(35, $designatedSwitchDate->second);
        static::assertSame(10, $designatedEndDate->hour);
        static::assertSame(0, $designatedEndDate->minute);
        static::assertSame(35, $designatedEndDate->second);

        $designatedPublicSwitchDate = new Carbon($procedure->getSettings()->getDesignatedPublicSwitchDate());
        $designatedEndPublicSwitchDate = new Carbon($procedure->getSettings()->getDesignatedPublicEndDate());
        static::assertSame(12, $designatedPublicSwitchDate->hour);
        static::assertSame(15, $designatedPublicSwitchDate->minute);
        static::assertSame(40, $designatedPublicSwitchDate->second);
        static::assertSame(17, $designatedEndPublicSwitchDate->hour);
        static::assertSame(30, $designatedEndPublicSwitchDate->minute);
        static::assertSame(40, $designatedEndPublicSwitchDate->second);

        $listOfProcedures = $this->sut->getProceduresToSwitchUntilNow();

        // check structure of result
        static::assertIsArray($listOfProcedures);
        static::assertCount(1, $listOfProcedures);
        static::assertEquals($procedure, $listOfProcedures[0]);

        $autoSwitchDate = Carbon::create(2002, 12, 15, 9, 30, 45);
        $autoSwitchPublicDate = Carbon::create(2002, 11, 12, 7, 20, 10);

        // reset autoswitch?!
        $procedure = $this->sut->getProcedure($procedure->getId());

        $this->setAndUpdateAutoSwitchPublic(['id' => $procedure->getId()], $autoSwitchPublicDate, 'configuration');
        $this->setAndUpdateAutoSwitch(['id' => $procedure->getId()], $autoSwitchDate, 'participation');

        $listOfProcedures = $this->sut->getProceduresToSwitchUntilNow();

        // check structure of result
        static::assertIsArray($listOfProcedures);
        static::assertCount(1, $listOfProcedures);
        static::assertEquals($procedure, $listOfProcedures[0]);

        static::assertEquals($autoSwitchPublicDate->toDateTime(), $procedure->getSettings()->getDesignatedPublicSwitchDate());
        $setDesignatedPublicSwitchDate = new Carbon($procedure->getSettings()->getDesignatedPublicSwitchDate());
        static::assertEquals(07, $setDesignatedPublicSwitchDate->hour);
        static::assertEquals($autoSwitchPublicDate->hour, $setDesignatedPublicSwitchDate->hour);
        static::assertEquals(20, $setDesignatedPublicSwitchDate->minute);
        static::assertEquals($autoSwitchPublicDate->minute, $setDesignatedPublicSwitchDate->minute);
        static::assertEquals(10, $setDesignatedPublicSwitchDate->second);
        static::assertEquals($autoSwitchPublicDate->second, $setDesignatedPublicSwitchDate->second);
        static::assertEquals('configuration', $procedure->getSettings()->getDesignatedPublicPhase());

        static::assertEquals($autoSwitchDate->toDateTime(), $procedure->getSettings()->getDesignatedSwitchDate());
        $setDesignatedSwitchDate = new Carbon($procedure->getSettings()->getDesignatedSwitchDate());
        static::assertEquals(9, $setDesignatedSwitchDate->hour);
        static::assertEquals($autoSwitchDate->hour, $setDesignatedSwitchDate->hour);
        static::assertEquals(30, $setDesignatedSwitchDate->minute);
        static::assertEquals($autoSwitchDate->minute, $setDesignatedSwitchDate->minute);
        static::assertEquals(45, $setDesignatedSwitchDate->second);
        static::assertEquals($autoSwitchDate->second, $setDesignatedSwitchDate->second);

        static::assertEquals('participation', $procedure->getSettings()->getDesignatedPhase());
    }

    /**
     * This test is created with T22944 and test the accuracy of designated switch time. (minute instead of day accuracy).
     */
    public function testGetProceduresToSwitchUntilNow(): void
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');

        $testProcedure = $this->getTestProcedure();
        $designatedSwitchDate = Carbon::now()->subMinutes(10)->subSeconds(45);
        $designatedPublicSwitchDate = Carbon::now()->addMinutes(30)->addSeconds(45);

        // should be switch:
        $testProcedure->getSettings()->setDesignatedSwitchDate($designatedSwitchDate->toDateTime());

        // should not be switch:
        $testProcedure->getSettings()->setDesignatedPublicSwitchDate($designatedPublicSwitchDate->toDateTime());
        $this->loginTestUser();
        $this->sut->updateProcedureObject($testProcedure);

        /** @var Procedure $testProcedure */
        $testProcedure = $this->find(Procedure::class, $testProcedure->getId());

        static::assertEquals($designatedSwitchDate, $testProcedure->getSettings()->getDesignatedSwitchDate());
        static::assertEquals($designatedPublicSwitchDate, $testProcedure->getSettings()->getDesignatedPublicSwitchDate());

        $proceduresToSwitch = $this->sut->getProceduresToSwitchUntilNow();

        // check structure of result
        static::assertIsArray($proceduresToSwitch);
        static::assertCount(1, $proceduresToSwitch);
        static::assertEquals($testProcedure, $proceduresToSwitch[0]);
    }

    public function testGetFullIdList(): void
    {
        $procedureRepository = $this->sut->getDoctrine()->getManager()->getRepository(Procedure::class);
        $procedures = $procedureRepository->getFullList(null, true);

        static::assertIsArray($procedures);
        static::assertIsString($procedures[0]);
    }

    public function testGetFullList(): void
    {
        $procedureRepository = $this->sut->getDoctrine()->getManager()->getRepository(Procedure::class);
        $procedures = $procedureRepository->getFullList();

        static::assertIsArray($procedures);
        static::assertInstanceOf(Procedure::class, $procedures[0]);
    }

    /**
     * @throws Exception
     */
    public function testCopyGisLayerCategoriesOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $procedureMaster */
        $procedureMaster2 = $this->fixtures->getReference('masterBlaupause2');
        $numberOfGisLayerCategoriesBefore = $this->countEntries(GisLayerCategory::class);
        $numberOfGisLayerBefore = $this->countEntries(GisLayer::class);

        // check setup:
        $rootGisLayerCategoryOfMaster = $this->mapService->getRootLayerCategory($procedureMaster2->getId());
        $numberOfGisLayersOfRootBefore = $rootGisLayerCategoryOfMaster->getGisLayers()->count();
        $numberOfChildrenOfRootBefore = $rootGisLayerCategoryOfMaster->getChildren()->count();
        static::assertCount(1, $rootGisLayerCategoryOfMaster->getChildren()[0]->getChildren());
        static::assertCount(1, $rootGisLayerCategoryOfMaster->getChildren()[0]->getChildren()[0]->getChildren());

        $numberOfGisLayerCategoriesOfMasterBefore =
            $this->countEntries(
                GisLayerCategory::class,
                ['procedure' => $procedureMaster2->getId()]
            );

        $numberOfGisLayerOfMasterBefore =
            $this->countEntries(
                GisLayer::class,
                ['procedureId' => $procedureMaster2->getId()]
            );

        $procedureData = [
            'copymaster'   => $procedureMaster2,
            'desc'         => '',
            'startDate'    => '01.02.2012',
            'endDate'      => '01.02.2012',
            'externalName' => 'testAdded',
            'name'         => 'testAdded',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
        ];

        // on addProcedure() GisLayer and GisLayerCategories will be copied
        $createdProcedure = $this->sut->addProcedureEntity(
            $procedureData,
            $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId()
        );

        $procedureMaster2 = $this->sut->getProcedure($procedureMaster2->getId());

        // CHECK GLOBAL:
        $numberOfGisLayerCategoriesAfter = $this->countEntries(GisLayerCategory::class);
        static::assertEquals($numberOfGisLayerCategoriesBefore + 4, $numberOfGisLayerCategoriesAfter);
        $numberOfGisLayerAfter = $this->countEntries(GisLayer::class);
        static::assertEquals($numberOfGisLayerBefore + 2, $numberOfGisLayerAfter);

        // CHECK MASTER:
        $rootGisLayerCategoryOfMaster = $this->mapService->getRootLayerCategory($procedureMaster2->getId());
        static::assertInstanceOf(GisLayerCategory::class, $rootGisLayerCategoryOfMaster);

        $numberOfRelatedCategoriesToMasterAfter =
            $this->countEntries(GisLayerCategory::class, ['procedure' => $procedureMaster2->getId()]);
        static::assertEquals(
            $numberOfGisLayerCategoriesOfMasterBefore,
            $numberOfRelatedCategoriesToMasterAfter
        );

        $numberOfRelatedGisLayersToMasterAfter =
            $this->countEntries(GisLayer::class, ['procedureId' => $procedureMaster2->getId()]);
        static::assertEquals(
            $numberOfGisLayerOfMasterBefore,
            $numberOfRelatedGisLayersToMasterAfter
        );

        $rootGisLayerCategoryOfMaster = $this->mapService->getRootLayerCategory($procedureMaster2->getId());
        static::assertInstanceOf(GisLayerCategory::class, $rootGisLayerCategoryOfMaster);

        // -------assert no differences on categories and blueprints of master procedure:
        static::assertCount($numberOfGisLayersOfRootBefore, $rootGisLayerCategoryOfMaster->getGisLayers());
        static::assertCount($numberOfChildrenOfRootBefore, $rootGisLayerCategoryOfMaster->getChildren());
        static::assertCount(1, $rootGisLayerCategoryOfMaster->getChildren());
        static::assertCount(0, $rootGisLayerCategoryOfMaster->getGisLayers());

        // -------------lvl1---------------------
        /** @var GisLayerCategory $category1 */
        $category1 = $rootGisLayerCategoryOfMaster->getChildren()[0];
        static::assertEquals($procedureMaster2->getId(), $category1->getProcedure()->getId());
        static::assertCount(0, $category1->getGisLayers());
        static::assertCount(1, $category1->getChildren());
        static::assertEquals($category1->getId(), $category1->getChildren()[0]->getParentId());
        static::assertEquals($rootGisLayerCategoryOfMaster->getId(), $category1->getParentId());

        // -------------lvl2---------------------
        /** @var GisLayerCategory $category2 */
        $category2 = $category1->getChildren()[0];
        static::assertEquals($procedureMaster2->getId(), $category2->getProcedure()->getId());
        static::assertCount(1, $category2->getGisLayers());
        static::assertEquals($category2->getId(), $category2->getGisLayers()[0]->getCategory()->getId());
        static::assertEquals($procedureMaster2->getId(), $category2->getGisLayers()[0]->getProcedureId());
        static::assertCount(1, $category2->getChildren());
        static::assertEquals($category2->getId(), $category2->getChildren()[0]->getParentId());
        static::assertEquals($category1->getId(), $category2->getParentId());

        // -------------lvl3---------------------
        /** @var GisLayerCategory $category3 */
        $category3 = $category2->getChildren()[0];
        static::assertEquals($procedureMaster2->getId(), $category3->getProcedure()->getId());
        static::assertCount(1, $category3->getGisLayers());
        static::assertEquals($category3->getId(), $category3->getGisLayers()[0]->getCategory()->getId());
        static::assertEquals($procedureMaster2->getId(), $category3->getGisLayers()[0]->getProcedureId());
        static::assertCount(0, $category3->getChildren());
        static::assertEquals($category2->getId(), $category3->getParentId());

        // check new created procedure and his gis and category

        $rootGisLayerCategoryOfNewProcedure = $this->mapService->getRootLayerCategory($createdProcedure->getId());
        static::assertInstanceOf(GisLayerCategory::class, $rootGisLayerCategoryOfNewProcedure);

        $numberOfRelatedCategoriesToNewProcedure =
            $this->countEntries(GisLayerCategory::class, ['procedure' => $createdProcedure->getId()]);
        static::assertEquals(4, $numberOfRelatedCategoriesToNewProcedure);

        $numberOfRelatedGisLayersToNewProcedure =
            $this->countEntries(GisLayer::class, ['procedureId' => $createdProcedure->getId()]);
        static::assertEquals(2, $numberOfRelatedGisLayersToNewProcedure);

        static::assertCount(1, $rootGisLayerCategoryOfNewProcedure->getChildren());
        static::assertCount(0, $rootGisLayerCategoryOfNewProcedure->getGisLayers());

        // -------------lvl1---------------------
        /** @var GisLayerCategory $category1 */
        $category1 = $rootGisLayerCategoryOfNewProcedure->getChildren()[0];
        static::assertEquals($createdProcedure->getId(), $category1->getProcedure()->getId());
        static::assertCount(0, $category1->getGisLayers());
        static::assertCount(1, $category1->getChildren());
        static::assertEquals($category1->getId(), $category1->getChildren()[0]->getParentId());
        static::assertEquals($rootGisLayerCategoryOfNewProcedure->getId(), $category1->getParentId());

        // -------------lvl2---------------------
        /** @var GisLayerCategory $category2 */
        $category2 = $category1->getChildren()[0];
        static::assertEquals($createdProcedure->getId(), $category2->getProcedure()->getId());
        static::assertCount(1, $category2->getGisLayers());
        static::assertEquals($category2->getId(), $category2->getGisLayers()[0]->getCategory()->getId());
        static::assertEquals($createdProcedure->getId(), $category2->getGisLayers()[0]->getProcedureId());
        static::assertCount(1, $category2->getChildren());
        static::assertEquals($category2->getId(), $category2->getChildren()[0]->getParentId());
        static::assertEquals($category1->getId(), $category2->getParentId());

        // -------------lvl3---------------------
        /** @var GisLayerCategory $category3 */
        $category3 = $category2->getChildren()[0];
        static::assertEquals($createdProcedure->getId(), $category3->getProcedure()->getId());
        static::assertCount(1, $category3->getGisLayers());
        static::assertEquals($category3->getId(), $category3->getGisLayers()[0]->getCategory()->getId());
        static::assertEquals($createdProcedure->getId(), $category3->getGisLayers()[0]->getProcedureId());
        static::assertCount(0, $category3->getChildren());
        static::assertEquals($category2->getId(), $category3->getParentId());
    }

    public function testCopyGisLayerVisibilityGroupOnAddProcedure(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        /** @var GisLayer $invisibleGisLayer1 */
        $invisibleGisLayer1 = $this->fixtures->getReference('invisibleGisLayer1');
        $originalVisibilityGroupId1 = $invisibleGisLayer1->getVisibilityGroupId();
        /** @var GisLayer $visibleGisLayer1 */
        $visibleGisLayer1 = $this->fixtures->getReference('visibleGisLayer1');
        $originalVisibilityGroupId2 = $visibleGisLayer1->getVisibilityGroupId();

        // check setupdata (gislayer and visibilitygroups of $testProcedure2
        $gisLayersOfMasterProcedure =
            $this->mapService->getGisAdminList($testProcedure2->getId());

        static::assertNotEmpty($gisLayersOfMasterProcedure);

        $totalAmountOfGisLayersOfMaster = $this->countEntries(
            GisLayer::class,
            ['procedureId' => $testProcedure2->getId()]
        );

        $gisLayersOfMasterWithoutVisibilityGroup = $this->countEntries(
            GisLayer::class,
            [
                'procedureId'       => $testProcedure2->getId(),
                'visibilityGroupId' => null,
            ]
        );

        static::assertEquals($totalAmountOfGisLayersOfMaster, count($gisLayersOfMasterProcedure));
        $gisLayersWithVisibilityGroup = $totalAmountOfGisLayersOfMaster - $gisLayersOfMasterWithoutVisibilityGroup;
        static::assertEquals(4, $gisLayersWithVisibilityGroup);

        $procedureData = [
            'copymaster'   => $testProcedure2,
            'desc'         => 'zzzzzzzzzzzzzzzzzzzzzzzzzzzzz',
            'startDate'    => '01.02.2012',
            'endDate'      => '01.02.2012',
            'externalName' => 'testAdded',
            'name'         => 'zzzzzzzzzzzzzzzzzzzzzzzzzzz',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
        ];

        // on addProcedure() GisLayer and GisLayerCategories will be copied
        $createdProcedure = $this->sut->addProcedureEntity(
            $procedureData,
            $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId()
        );

        /** @var [][] $gisLayersOfCreatedProcedure */
        $gisLayersOfCreatedProcedure = $this->mapService->getGisAdminList($createdProcedure->getId());

        static::assertNotEmpty($gisLayersOfCreatedProcedure);
        static::assertEquals($totalAmountOfGisLayersOfMaster, count($gisLayersOfCreatedProcedure));

        $gisLayersOfNewProcedureWithoutVisibilityGroup = $this->countEntries(
            GisLayer::class,
            [
                'procedureId'       => $createdProcedure->getId(),
                'visibilityGroupId' => null,
            ]
        );

        static::assertEquals($gisLayersOfMasterWithoutVisibilityGroup, $gisLayersOfNewProcedureWithoutVisibilityGroup);
        $gisLayersWithVisibilityGroup = $totalAmountOfGisLayersOfMaster - $gisLayersOfNewProcedureWithoutVisibilityGroup;
        static::assertEquals(4, $gisLayersWithVisibilityGroup);

        // visibilityGroupIds of copied Gislayers have to be different to visibilityGroupIds of original GisLayers!
        foreach ($gisLayersOfCreatedProcedure as $gisLayer) {
            static::assertNotEquals($originalVisibilityGroupId1, $gisLayer['visibilityGroupId']);
            static::assertNotEquals($originalVisibilityGroupId2, $gisLayer['visibilityGroupId']);
        }
    }

    public function testSetAuthorizedUserOfProcedure(): void
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        /** @var User $testUser */
        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $testUser = $this->sut->getPublicUserService()->getSingleUser($testUser->getId());
        /** @var User $testUser2 */
        $testUser2 = $this->fixtures->getReference(LoadUserData::TEST_USER_CITIZEN);
        $testUser2 = $this->sut->getPublicUserService()->getSingleUser($testUser2->getId());

        $users = $testProcedure2->getAuthorizedUsers();
        static::assertEmpty($users);

        $updateData = [
            'ident'           => $testProcedure2->getId(),
            'authorizedUsers' => [$testUser2, $testUser],
        ];

        $this->sut->updateProcedure($updateData);

        $updatedProcedure = $this->sut->getProcedure($testProcedure2->getId());

        static::assertCount(2, $updatedProcedure->getAuthorizedUsers());

        // check for ids only, because of checking authorized users contains object, will fail
        $userId1 = $updatedProcedure->getAuthorizedUsers()[0]->getId();
        $userId2 = $updatedProcedure->getAuthorizedUsers()[1]->getId();
        $containedUserIds = [$userId1, $userId2];
        static::assertContains($testUser2->getId(), $containedUserIds);
        static::assertContains($testUser->getId(), $containedUserIds);

        $updateData = [
            'ident'           => $testProcedure2->getId(),
            'authorizedUsers' => [],
        ];

        $this->sut->updateProcedure($updateData);
        $updatedProcedure = $this->sut->getProcedure($testProcedure2->getId());
        static::assertCount(0, $updatedProcedure->getAuthorizedUsers());
    }

    public function testGetAuthorizedUsers(): void
    {
        $testProcedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $testUser = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $authorizedUsers = $this->sut->getAuthorizedUsers($testProcedure->getId(), $testUser);
        self::assertCount(2, $authorizedUsers);
    }

    public function testCreateReportEntryOnChangeAuthorizedUserOfProcedure(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        /** @var User $testUser */
        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var User $testUser2 */
        $testUser2 = $this->fixtures->getReference('testUser2');
        $numberOfReportEntriesBefore = $this->countEntries(ReportEntry::class);

        $testUserFullName = $testUser->getFullname();
        $testUserFullName2 = $testUser2->getFullname();

        $users = $testProcedure2->getAuthorizedUsers();
        static::assertEmpty($users);

        $updateData = [
            'ident'           => $testProcedure2->getId(),
            'authorizedUsers' => [$testUser2, $testUser],
        ];

        // change authorizedUser:
        $this->sut->updateProcedure($updateData);
        $numberOfReportEntriesAfter = $this->countEntries(ReportEntry::class);
        static::assertEquals($numberOfReportEntriesBefore + 1, $numberOfReportEntriesAfter);

        /** @var ReportEntry[] $entries */
        $entries = $this->getEntries(
            ReportEntry::class,
            [
                'identifier' => $testProcedure2->getId(),
                'group'      => 'procedure',
                'category'   => 'update',
            ]
        );

        static::assertCount(1, $entries);

        // "{'oldAuthorizedUsers':'','newAuthorizedUsers':'functionaltestuser@demos-deutschland.de, user3@demos-deutschland.de'}"
        $expectedMessage = '{"oldAuthorizedUsers":"","newAuthorizedUsers":"'.$testUserFullName2.', '.$testUserFullName.'"}';
        static::assertEquals($expectedMessage, $entries[0]->getMessage());
    }

    public function testCreateReportEntryOnChangeNameOfProcedure(): void
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        $numberOfReportEntriesBefore = $this->countEntries(ReportEntry::class);

        $oldName = $testProcedure2->getName();

        // change authorizedUser:
        $updateData = [
            'ident' => $testProcedure2->getId(),
            'name'  => 'updatedName',
        ];
        $this->sut->updateProcedure($updateData);

        $numberOfReportEntriesAfter = $this->countEntries(ReportEntry::class);
        static::assertEquals($numberOfReportEntriesBefore + 1, $numberOfReportEntriesAfter);

        /** @var ReportEntry[] $entries */
        $entries = $this->getEntries(
            ReportEntry::class,
            [
                'identifier' => $testProcedure2->getId(),
                'group'      => 'procedure',
                'category'   => 'update',
            ]
        );

        static::assertCount(1, $entries);

        // "{'oldAuthorizedUsers':'','newAuthorizedUsers':'functionaltestuser@demos-deutschland.de, user3@demos-deutschland.de'}"
        $expectedMessage = '{"oldName":"'.$oldName.'","newName":"'.$updateData['name'].'"}';
        static::assertEquals($expectedMessage, $entries[0]->getMessage());
    }

    public function testCreateReportEntryOnChangeNameOfProcedureObject(): void
    {
        self::markSkippedForCIIntervention();

        // this test will fail, because creating reportentry on updateProcedureObject() doesnt work yet:

        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        $numberOfReportEntriesBefore = $this->countEntries(ReportEntry::class);

        $testProcedure2->setName('updatedName');
        $this->sut->updateProcedureObject($testProcedure2);

        $numberOfReportEntriesAfter = $this->countEntries(ReportEntry::class);

        static::assertEquals($numberOfReportEntriesBefore + 1, $numberOfReportEntriesAfter);
    }

    public function testSetAuthorizedUserOfOrganisationOnCreateProcedureFromMaster(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $procedureMaster */
        $procedureMaster = $this->fixtures->getReference('masterBlaupause');
        /** @var User $testUser */
        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var User $testUser2 */
        $testUser2 = $this->fixtures->getReference('testUser2');

        $procedureMaster->setAuthorizedUsers([$testUser, $testUser2]);
        $this->sut->updateProcedureObject($procedureMaster);
        $procedureMaster = $this->sut->getProcedure($procedureMaster->getId());
        static::assertCount(2, $procedureMaster->getAuthorizedUsers());
        static::assertContains($testUser, $procedureMaster->getAuthorizedUsers());
        static::assertContains($testUser2, $procedureMaster->getAuthorizedUsers());

        $procedure = [
            'copymaster'   => $procedureMaster,
            'desc'         => '',
            'startDate'    => '01.02.2018',
            'endDate'      => '01.02.2019',
            'externalName' => 'test',
            'name'         => 'testSetAuthorizedUserOnCreateProcedureWithMaster',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
        ];

        $createdProcedure = $this->sut->addProcedureEntity($procedure, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());

        // T15644:
        // overwrite authorized users in case of used blueprint is a master-blueprint,
        // to avoid authorizing creators of masterblueprint to this new procedure:
        static::assertCount(count($testUser->getOrga()->getUsers()), $createdProcedure->getAuthorizedUsers());

        foreach ($testUser->getOrga()->getUsers() as $orgaUser) {
            static::assertContains($orgaUser, $createdProcedure->getAuthorizedUsers());
        }
    }

    /**
     * On create new procedure at least the current user has to be set as authorized user.
     * This should be happend in case of given blueprint is not master and has no authorized users selected.
     *
     * @throws Exception
     */
    public function testMinimumUserOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $procedureMaster */
        $procedureMaster = $this->fixtures->getReference('masterBlaupause');
        /** @var User $testUser */
        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $procedureMaster->setAuthorizedUsers([]);
        $this->sut->updateProcedureObject($procedureMaster);
        $procedureMaster = $this->sut->getProcedure($procedureMaster->getId());
        static::assertCount(0, $procedureMaster->getAuthorizedUsers());

        $procedure = [
            'copymaster'   => $procedureMaster,
            'desc'         => '',
            'startDate'    => '01.02.2018',
            'endDate'      => '01.02.2019',
            'externalName' => 'test',
            'name'         => 'testSetAuthorizedUserOnCreateProcedureWithMaster',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
        ];

        $createdProcedure = $this->sut->addProcedureEntity($procedure, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
        static::assertCount(1, $createdProcedure->getAuthorizedUsers());
        static::assertContains($testUser, $createdProcedure->getAuthorizedUsers());
    }

    /**
     * On create new procedure at least the current user has to be set as authorized user.
     * This should be happend in case of given blueprint is not master and has no authorized users selected.
     *
     * @throws Exception
     */
    public function testSetAuthorizedUsersOfBlueprintOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $procedureMaster */
        $procedureMaster = $this->fixtures->getReference('masterBlaupause');
        /** @var User $testUser */
        $testUser = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $procedureMaster->setAuthorizedUsers([]);
        $this->sut->updateProcedureObject($procedureMaster);
        $procedureMaster = $this->sut->getProcedure($procedureMaster->getId());
        static::assertCount(0, $procedureMaster->getAuthorizedUsers());

        $procedure = [
            'copymaster'   => $procedureMaster,
            'desc'         => '',
            'startDate'    => '01.02.2018',
            'endDate'      => '01.02.2019',
            'externalName' => 'test',
            'name'         => 'testSetAuthorizedUserOnCreateProcedureWithMaster',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
        ];

        $createdProcedure = $this->sut->addProcedureEntity($procedure, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());
        static::assertCount(1, $createdProcedure->getAuthorizedUsers());
        static::assertContains($testUser, $createdProcedure->getAuthorizedUsers());
    }

    public function testCopyBoilerplatesOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $blueprintProcedure */
        $blueprintProcedure = $this->fixtures->getReference('testmasterProcedureWithBoilerplates');
        /** @var Boilerplate $newsBoilerplate */
        $newsBoilerplate = $this->fixtures->getReference('testNewsBoilerplate');
        /** @var Boilerplate $mailBoilerplate */
        $mailBoilerplate = $this->fixtures->getReference('testMailBoilerplate');
        /** @var Boilerplate $mulityBoilerplate */
        $mulityBoilerplate = $this->fixtures->getReference('testMultipleBoilerplate');
        /** @var BoilerplateCategory $multiyBoilerplate */
        $mailCategory = $this->fixtures->getReference('testBoilerplatecategory2');
        /** @var BoilerplateCategory $multiyBoilerplate */
        $newsCategory = $this->fixtures->getReference('testBoilerplatecategory3');
        /** @var BoilerplateCategory $newsCategory */
        $emptyCategory = $this->fixtures->getReference('testBoilerplateEmptyCategory');

        // check setup:
        static::assertEquals($blueprintProcedure->getId(), $emptyCategory->getPId());
        static::assertCount(0, $emptyCategory->getBoilerplates());

        static::assertEquals($blueprintProcedure->getId(), $mailCategory->getPId());
        static::assertCount(3, $mailCategory->getBoilerplates());

        static::assertEquals($blueprintProcedure->getId(), $newsCategory->getPId());
        static::assertCount(4, $newsCategory->getBoilerplates());

        static::assertCount(1, $mailBoilerplate->getCategories());
        static::assertContains($mailCategory, $mailBoilerplate->getCategories());
        static::assertEquals($blueprintProcedure->getId(), $mailBoilerplate->getProcedureId());

        static::assertCount(1, $newsBoilerplate->getCategories());
        static::assertContains($newsCategory, $newsBoilerplate->getCategories());
        static::assertEquals($blueprintProcedure->getId(), $newsBoilerplate->getProcedureId());

        static::assertCount(2, $mulityBoilerplate->getCategories());
        static::assertContains($mailCategory, $mulityBoilerplate->getCategories());
        static::assertContains($newsCategory, $mulityBoilerplate->getCategories());
        static::assertEquals($blueprintProcedure->getId(), $mulityBoilerplate->getProcedureId());

        /** @var Boilerplate[] $boilerplates */
        $blueprintBoilerplates = $this->getEntries(Boilerplate::class, ['procedure' => $blueprintProcedure->getId()]);
        /** @var BoilerplateCategory[] $boilerplateCategories */
        $blueprintBoilerplateCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $blueprintProcedure->getId()]
        );

        static::assertCount(6, $blueprintBoilerplates);
        static::assertCount(3, $blueprintBoilerplateCategories);

        $procedure = [
            'copymaster'   => $blueprintProcedure,
            'desc'         => '',
            'startDate'    => '01.02.2018',
            'endDate'      => '01.02.2019',
            'externalName' => 'test',
            'name'         => 'testBoilerplatesAndBoilerplateCategories',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
        ];

        $newProcedure = $this->sut->addProcedureEntity($procedure, $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId());

        // check blueprint categories+boilerplates and new categories+boilerplates on DB-level:

        static::assertInstanceOf(Procedure::class, $newProcedure);

        // get boilerplates
        /** @var Boilerplate[] $boilerplates */
        $newNewsBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $newProcedure->getId(), 'title' => 'eine aktuelle Mitteilung']
        );
        /** @var Boilerplate[] $boilerplates */
        $blueprintNewsBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $blueprintProcedure->getId(), 'title' => 'eine aktuelle Mitteilung']
        );
        /** @var Boilerplate[] $boilerplates */
        $newMailBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $newProcedure->getId(), 'title' => 'mail']
        );
        /** @var Boilerplate[] $boilerplates */
        $blueprintMailBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $blueprintProcedure->getId(), 'title' => 'mail']
        );
        /** @var Boilerplate[] $boilerplates */
        $newMultyBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $newProcedure->getId(), 'title' => 'aktuelle Mitteilung und mail']
        );
        /** @var Boilerplate[] $boilerplates */
        $blueprintMultyBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $blueprintProcedure->getId(), 'title' => 'aktuelle Mitteilung und mail']
        );

        // get categories
        /** @var BoilerplateCategory[] $newMailCategories */
        $newMailCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $newProcedure->getId(), 'title' => 'email']
        );
        /** @var BoilerplateCategory[] $blueprintMailCategories */
        $blueprintMailCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $blueprintProcedure->getId(), 'title' => 'email']
        );
        /** @var BoilerplateCategory[] $newNewsCategories */
        $newNewsCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $newProcedure->getId(), 'title' => 'news.notes']
        );
        /** @var BoilerplateCategory[] $blueprintNewsCategories */
        $blueprintNewsCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $blueprintProcedure->getId(), 'title' => 'news.notes']
        );
        /** @var BoilerplateCategory[] $newMailCategories */
        $newEmptyCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $newProcedure->getId(), 'title' => 'empty']
        );
        /** @var BoilerplateCategory[] $blueprintMailCategories */
        $blueprintEmptyCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $blueprintProcedure->getId(), 'title' => 'empty']
        );
        /* @var BoilerplateCategory[] $newNewsCategories */

        // check blueprint boilerplates
        static::assertCount(1, $newNewsBoilerplates);
        static::assertCount(1, $newNewsBoilerplates[0]->getCategories());
        static::assertEquals($newProcedure->getId(), $newNewsBoilerplates[0]->getProcedureId());

        static::assertCount(1, $blueprintMailBoilerplates);
        static::assertCount(1, $blueprintMailBoilerplates[0]->getCategories());
        static::assertEquals($blueprintProcedure->getId(), $blueprintMailBoilerplates[0]->getProcedureId());

        static::assertCount(1, $blueprintMultyBoilerplates);
        static::assertCount(2, $blueprintMultyBoilerplates[0]->getCategories());
        static::assertEquals($blueprintProcedure->getId(), $blueprintMultyBoilerplates[0]->getProcedureId());

        // check new boilerplates
        static::assertCount(1, $blueprintNewsBoilerplates);
        static::assertCount(1, $blueprintNewsBoilerplates[0]->getCategories());
        static::assertEquals($blueprintProcedure->getId(), $blueprintNewsBoilerplates[0]->getProcedureId());

        static::assertCount(1, $newMailBoilerplates);
        static::assertCount(1, $newMailBoilerplates[0]->getCategories());
        static::assertEquals($newProcedure->getId(), $newMailBoilerplates[0]->getProcedureId());

        static::assertCount(1, $newMultyBoilerplates);
        static::assertCount(2, $newMultyBoilerplates[0]->getCategories());
        static::assertEquals($newProcedure->getId(), $newMultyBoilerplates[0]->getProcedureId());

        // check blueprint categories
        static::assertCount(1, $blueprintMailCategories);
        static::assertCount(3, $blueprintMailCategories[0]->getBoilerplates());
        static::assertEquals($blueprintProcedure->getId(), $blueprintMailCategories[0]->getPId());
        static::assertContains($blueprintMailBoilerplates[0], $blueprintMailCategories[0]->getBoilerplates());
        static::assertContains($blueprintMultyBoilerplates[0], $blueprintMailCategories[0]->getBoilerplates());

        static::assertCount(1, $blueprintNewsCategories);
        static::assertCount(4, $blueprintNewsCategories[0]->getBoilerplates());
        static::assertEquals($blueprintProcedure->getId(), $blueprintNewsCategories[0]->getPId());
        static::assertContains($blueprintNewsBoilerplates[0], $blueprintNewsCategories[0]->getBoilerplates());
        static::assertContains($blueprintMultyBoilerplates[0], $blueprintNewsCategories[0]->getBoilerplates());

        static::assertCount(1, $blueprintEmptyCategories);
        static::assertCount(0, $blueprintEmptyCategories[0]->getBoilerplates());
        static::assertEquals($blueprintProcedure->getId(), $blueprintEmptyCategories[0]->getPId());

        // check new categories:
        static::assertCount(1, $newMailCategories);
        static::assertCount(3, $newMailCategories[0]->getBoilerplates());
        static::assertEquals($newProcedure->getId(), $newMailCategories[0]->getPId());
        static::assertContains($newMailBoilerplates[0], $newMailCategories[0]->getBoilerplates());
        static::assertContains($newMultyBoilerplates[0], $newMailCategories[0]->getBoilerplates());

        static::assertCount(1, $newNewsCategories);
        static::assertCount(4, $newNewsCategories[0]->getBoilerplates());
        static::assertEquals($newProcedure->getId(), $newNewsCategories[0]->getPId());
        static::assertContains($newNewsBoilerplates[0], $newNewsCategories[0]->getBoilerplates());
        static::assertContains($newMultyBoilerplates[0], $newNewsCategories[0]->getBoilerplates());

        static::assertCount(1, $newEmptyCategories);
        static::assertCount(0, $newEmptyCategories[0]->getBoilerplates());
        static::assertEquals($newProcedure->getId(), $newEmptyCategories[0]->getPId());
    }

    public function testSetScales(): void
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        $testProcedure2->getSettings()->setScales([25000, 54686, 54633]);

        $updatedProcedure = $this->sut->updateProcedureObject($testProcedure2);

        static::assertInstanceOf(Procedure::class, $updatedProcedure);
        static::assertEquals([25000, 54686, 54633], $updatedProcedure->getSettings()->getScales());
    }

    public function testSetScalessOnArray(): void
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        $testProcedure2->getSettings()->setScales('25000, 54686, 54633');

        $updateData = [
            'ident'  => $testProcedure2->getId(),
            'scales' => [25000, 54686, 54633],
        ];

        $updatedProcedure = $this->sut->updateProcedure($updateData);

        static::assertIsArray($updatedProcedure);
        static::assertIsArray($updatedProcedure['settings']);
        static::assertEquals($updateData['scales'], $updatedProcedure['settings']['scales']);
    }

    public function testSetCopyRight(): void
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        $copyrightString = 'copyrightString1';
        $testProcedure2->getSettings()->setCopyright($copyrightString);

        $updatedProcedure = $this->sut->updateProcedureObject($testProcedure2);

        static::assertInstanceOf(Procedure::class, $updatedProcedure);
        static::assertEquals($copyrightString, $updatedProcedure->getSettings()->getCopyright());
    }

    public function testSetCopyRightOnArray(): void
    {
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        $copyrightString = 'copyrightString2';

        $updateData = [
            'ident'    => $testProcedure2->getId(),
            'settings' => ['copyright' => $copyrightString],
        ];

        $updatedProcedure = $this->sut->updateProcedure($updateData);

        static::assertIsArray($updatedProcedure);
        static::assertIsArray($updatedProcedure['settings']);
        static::assertEquals($copyrightString, $updatedProcedure['settings']['copyright']);
    }

    public function testGetPlanningArea(): void
    {
        /** @var Procedure $testProcedure */
        $testProcedure = $this->getTestProcedure();
        static::assertEquals('I', $testProcedure->getPlanningArea());

        // test empty Planning Area
        /** @var Procedure $testProcedure2 */
        $testProcedure2 = $this->fixtures->getReference('testProcedure2');
        static::assertEquals('all', $testProcedure2->getPlanningArea());
    }

    public function testGetEmptyBoilerplatesOfConsideration(): void
    {
        /** @var Procedure $testProcedure */
        $testProcedureId = $this->fixtures->getReference('testProcedure4')->getId();
        $exprectedBoilerplates = $this->getEntries(
            Boilerplate::class,
            ['procedure' => $testProcedureId, 'title' => 'consideration']
        );
        static::assertEmpty($exprectedBoilerplates);

        $boilerplates = $this->sut->getBoilerplatesOfCategory($testProcedureId, 'consideration');
        static::assertEquals($exprectedBoilerplates, $boilerplates);
    }

    public function testGetBoilerplatesOfConsideration(): void
    {
        /** @var Procedure $testProcedureId */
        $testProcedureId = $this->fixtures->getReference('testProcedure2')->getId();
        /** @var BoilerplateCategory[] $expectedBoilerplateCategories */
        $expectedBoilerplateCategories = $this->getEntries(
            BoilerplateCategory::class,
            ['procedure' => $testProcedureId, 'title' => 'consideration']
        );
        static::assertCount(1, $expectedBoilerplateCategories);

        $exprectedBoilerplates = $expectedBoilerplateCategories[0]->getBoilerplates()->toArray();
        static::assertNotEmpty($exprectedBoilerplates);

        $boilerplates = $this->sut->getBoilerplatesOfCategory($testProcedureId, 'consideration');
        static::assertEquals($exprectedBoilerplates, $boilerplates);
    }

    public function testCopyBoilerplatesWithReference(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $blueprintWit$blueprinthBoilerplates */
        $blueprintWithBoilerplates = $this->getReference('testmasterProcedureWithBoilerplates');

        $sourceBoilerplates = $this->getEntries(Boilerplate::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $sourceBoilerplateIds = $this->getEntryIds(Boilerplate::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $numberOfBoilerpltesOfBlueprintBefore = count($sourceBoilerplates);

        $sourceCategories = $this->getEntries(BoilerplateCategory::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $sourceCategoryIds = $this->getEntryIds(BoilerplateCategory::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $numberOfCategoriesOfBlueprintBefore = count($sourceCategories);

        $sourceGroups = $this->getEntries(BoilerplateGroup::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $sourceGroupIds = $this->getEntryIds(BoilerplateGroup::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $numberOfGroupsOfBlueprintBefore = count($sourceGroups);

        $numberOfAllBoilerplatesBefore = $this->countEntries(Boilerplate::class);
        $numberOfAllCategoriesBefore = $this->countEntries(BoilerplateCategory::class);
        $numberOfAllGroupesBefore = $this->countEntries(BoilerplateGroup::class);

        // create new + fresh testing procedure
        $procedureData = [
            'copymaster'   => $blueprintWithBoilerplates->getId(),
            'desc'         => '',
            'startDate'    => '01.02.2012',
            'endDate'      => '01.02.2012',
            'externalName' => 'testAdded',
            'name'         => 'testAdded',
            'master'       => false,
            'orgaId'       => $this->testProcedure->getOrgaId(),
            'orgaName'     => $this->testProcedure->getOrga()->getName(),
            'logo'         => 'some:logodata:string',
            'shortUrl'     => 'myShortUrl',
            'customer'     => $this->getCustomerReference(LoadCustomerData::DEMOS),
        ];
        $procedureRepository = $this->sut->getPublicProcedureRepository();
        $newProcedure = $procedureRepository->add($procedureData);
        $newProcedureId = $newProcedure->getId();
        $sourceProcedure = $blueprintWithBoilerplates;

        // copy boilerplates within relations:
        $this->sut->copyBoilerplates($blueprintWithBoilerplates->getId(), $newProcedure);

        // ensure no related Objects are lost form blueprint:
        static::assertCount($numberOfBoilerpltesOfBlueprintBefore, $this->getEntries(Boilerplate::class, ['procedure' => $blueprintWithBoilerplates->getId()]));
        static::assertCount($numberOfGroupsOfBlueprintBefore, $this->getEntries(BoilerplateGroup::class, ['procedure' => $blueprintWithBoilerplates->getId()]));
        static::assertCount($numberOfCategoriesOfBlueprintBefore, $this->getEntries(BoilerplateCategory::class, ['procedure' => $blueprintWithBoilerplates->getId()]));

        $newBoilerplates = $this->getEntries(Boilerplate::class, ['procedure' => $newProcedureId]);
        $newBoilerplateIds = $this->getEntryIds(Boilerplate::class, ['procedure' => $newProcedureId]);
        $newGroups = $this->getEntries(BoilerplateGroup::class, ['procedure' => $newProcedureId]);
        $newGroupIds = $this->getEntryIds(BoilerplateGroup::class, ['procedure' => $newProcedureId]);
        $newCategories = $this->getEntries(BoilerplateCategory::class, ['procedure' => $newProcedureId]);
        $newCategoryIds = $this->getEntryIds(BoilerplateCategory::class, ['procedure' => $newProcedureId]);

        static::assertCount($numberOfBoilerpltesOfBlueprintBefore, $newBoilerplates, 'Too many/few boilerplates are created.');
        static::assertCount($numberOfGroupsOfBlueprintBefore, $newGroups, 'Too many/few groups are created.');
        static::assertCount($numberOfCategoriesOfBlueprintBefore, $newCategories, 'Too many/few categories are created.');

        // check new created Boilerplates, Groups and Categories
        /** @var Boilerplate $newBoilerplate */
        foreach ($newBoilerplates as $newBoilerplate) {
            static::assertInstanceOf(Boilerplate::class, $newBoilerplate);
            static::assertEquals($newProcedure->getId(), $newBoilerplate->getProcedureId());
            static::assertNotContains($newBoilerplate->getId(), $sourceBoilerplateIds);
            static::assertContains($newBoilerplate->getId(), $newBoilerplateIds);

            $group = $newBoilerplate->getGroup();
            if ($newBoilerplate->hasGroup()) {
                static::assertInstanceOf(BoilerplateGroup::class, $group);
                static::assertEquals($newProcedure->getId(), $group->getProcedure()->getId());
                static::assertNotContains($group->getId(), $sourceGroupIds);
                static::assertContains($group->getId(), $newGroupIds);
            }

            $categories = $newBoilerplate->getCategories();
            /** @var BoilerplateCategory $newCategory */
            foreach ($categories as $newCategory) {
                static::assertInstanceOf(BoilerplateCategory::class, $newCategory);
                static::assertEquals($newProcedure->getId(), $newCategory->getPId());
                static::assertNotContains($newCategory->getId(), $sourceCategoryIds);
                static::assertContains($newCategory->getId(), $newCategoryIds);
            }
        }

        // check number of categories, groups and boilerplates of old and new procedure
        $sourceBoilerplatesAfter = $this->getEntries(Boilerplate::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $sourceCategoriesAfter = $this->getEntries(BoilerplateCategory::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        $sourceGroupsAfter = $this->getEntries(BoilerplateGroup::class, ['procedure' => $blueprintWithBoilerplates->getId()]);
        // check setup:
        static::assertCount($numberOfBoilerpltesOfBlueprintBefore, $sourceBoilerplatesAfter);
        static::assertCount($numberOfCategoriesOfBlueprintBefore, $sourceCategoriesAfter);
        static::assertCount($numberOfGroupsOfBlueprintBefore, $sourceGroupsAfter);

        // check old source Boilerplates, Groups and Categories
        /** @var Boilerplate $sourceBoilerplate */
        foreach ($sourceBoilerplatesAfter as $sourceBoilerplate) {
            static::assertInstanceOf(Boilerplate::class, $sourceBoilerplate);
            static::assertEquals($sourceProcedure->getId(), $sourceBoilerplate->getProcedureId());
            static::assertNotContains($sourceBoilerplate->getId(), $newBoilerplateIds);
            static::assertContains($sourceBoilerplate->getId(), $sourceBoilerplateIds);

            $sourceGroup = $sourceBoilerplate->getGroup();
            if ($sourceBoilerplate->hasGroup()) {
                static::assertInstanceOf(BoilerplateGroup::class, $sourceGroup);
                static::assertEquals($sourceProcedure->getId(), $sourceGroup->getProcedure()->getId());
                static::assertNotContains($sourceGroup->getId(), $newGroupIds);
                static::assertContains($sourceGroup->getId(), $sourceGroupIds);
            }

            $categories = $sourceBoilerplate->getCategories();
            /* @var BoilerplateCategory $newCategory */
            foreach ($categories as $sourceCategory) {
                static::assertInstanceOf(BoilerplateCategory::class, $sourceCategory);
                static::assertEquals($sourceProcedure->getId(), $sourceCategory->getPId());
                static::assertNotContains($sourceCategory->getId(), $newCategoryIds);
                static::assertContains($sourceCategory->getId(), $sourceCategoryIds);
            }
        }

        // blueprint was copied, therefore, new amount of boilerplates, categories and groups, has to be increased by amount of related objects of blueprint
        static::assertCount($numberOfAllBoilerplatesBefore + $numberOfBoilerpltesOfBlueprintBefore, $this->getEntries(Boilerplate::class));
        static::assertCount($numberOfAllCategoriesBefore + $numberOfCategoriesOfBlueprintBefore, $this->getEntries(BoilerplateCategory::class));
        static::assertCount($numberOfAllGroupesBefore + $numberOfGroupsOfBlueprintBefore, $this->getEntries(BoilerplateGroup::class));
    }

    public function testDeleteClusterStatementsOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();
    }

    public function testDeleteStatementsOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();
    }

    public function testCopyOrignalStatemntsOfForeignChildrenOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();
    }

    public function testDeleteChildStatementsOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();
    }

    public function testDeleteOriginalStatementsOnDeleteProcedure(): void
    {
        self::markSkippedForCIIntervention();

        // get procedure with different casess of statements
        /** @var Procedure $procedureToDelete */
        $procedureToDelete = $this->fixtures->getReference('procedureToDelete');
        static::assertInstanceOf(Procedure::class, $procedureToDelete);
        static::assertFalse($procedureToDelete->isDeleted());

        $numberOfStatementsBefore = $this->countEntries(Statement::class, ['procedure' => $procedureToDelete->getId()]);
        $originalStatementIdsOfThisProcedureBefore = collect($this->getEntryIds(Statement::class, ['procedure' => $procedureToDelete->getId(), 'original' => null]));
        $numberOfOriginalStatementsOfThisProcedureBefore = $originalStatementIdsOfThisProcedureBefore->count();

        // todo:
        // zähle originalSTNs die von einem fremden verfahren referenziert werden:
        // get all original STNs of this procedure
        // get all STNs, die nicht in dieser procedure sind
        $result = $this->getEntriesWhereInIds(Statement::class, $originalStatementIdsOfThisProcedureBefore->toArray());
        $childrenInForeignProcedures = [];

        /** @var Statement $statement */
        foreach ($result as $statement) {
            if ($statement->getProcedureId() != $procedureToDelete->getId()) {
                $childrenInForeignProcedures[] = $statement;
            }
        }
        static::assertSame(1, $childrenInForeignProcedures);

        static::assertNotSame(0, $numberOfOriginalStatementsOfThisProcedureBefore);
        static::assertNotSame(0, $numberOfStatementsBefore);

        $procedureToDelete->setDeleted(true);
        $result = $this->sut->deleteStatements($procedureToDelete);
        $numberOfStatementsAfter = $this->countEntries(Statement::class, ['procedure' => $procedureToDelete->getId()]);
        $numberOfOriginalStatementsOfThisProcedureAfter = $this->countEntries(Statement::class, ['procedure' => $procedureToDelete->getId(), 'original' => null]);
        static::assertLessThan($numberOfStatementsBefore, $numberOfStatementsAfter);
        static::assertLessThan($numberOfOriginalStatementsOfThisProcedureBefore, $numberOfOriginalStatementsOfThisProcedureAfter);
    }

    /**
     * @throws ProcedureNotFoundException
     */
    public function testResetMapHintBplan(): void
    {
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var Procedure $procedure */
        $procedure = $this->getReference(LoadProcedureData::TESTPROCEDURE);

        $defaultValue = 'This is a mapHintDefault of a procedureUiDefinition on a procedure.';
        $editedValueByPlanner = 'This is a procedure specific map hint which might have been edited by a planer.';
        self::assertSame('Bauleitplanung', $procedure->getProcedureType()->getName());

        // before change
        self::assertSame($defaultValue, $procedure->getProcedureUiDefinition()->getMapHintDefault());
        self::assertSame($editedValueByPlanner, $procedure->getSettings()->getMapHint());

        // change
        $this->sut->resetMapHint($procedure->getId(), $user);

        // after
        self::assertSame('Bauleitplanung', $procedure->getProcedureType()->getName());
        self::assertSame($defaultValue, $procedure->getProcedureUiDefinition()->getMapHintDefault());
        self::assertSame($defaultValue, $procedure->getSettings()->getMapHint());
    }

    /**
     * On create a new procedure, the related emailTitle for the new procedure will be generated and will contain
     * the name of the new procedure. Therefore the recipient of the email can easily dedicate the Email.
     *
     * This test covering the But, that the emailtitle of the global default master blueprint
     * will be changed on create a new procedure.
     *
     * (T15853, T10976)
     */
    public function testEmailTitleOfMasterBlueprintOnAddProcedureEntity(): void
    {
        self::markSkippedForCIIntervention();

        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $copyMasterId = $this->sut->calculateCopyMasterId(null);
        /** @var Procedure $copyMaster */
        $copyMaster = $this->find(Procedure::class, $copyMasterId);
        $emailTitleOfMasterBlueprintBefore = $copyMaster->getSettings()->getEmailTitle();

        $procedureData = [
            'name'                     => 'newName',
            'desc'                     => 'new description',
            'copymaster'               => $copyMaster,
            'settings'                 => ['emailTitle' => 'new EmailTitle Of new procedure'],
            'master'                   => false, // this method only creates procedures (no blueprints)
            'publicParticipationPhase' => 'configuration',
            'procedureType'            => $this->getReference(LoadProcedureTypeData::BPLAN),
        ];
        static::assertNotEquals($procedureData['settings']['emailTitle'], $copyMaster->getSettings()->getEmailTitle());
        static::assertNotEquals($procedureData['settings']['emailTitle'], $emailTitleOfMasterBlueprintBefore);

        $newProcedure = $this->sut->addProcedureEntity($procedureData, $user->getId());

        $copyMaster = $this->find(Procedure::class, $copyMasterId);
        static::assertEquals($emailTitleOfMasterBlueprintBefore, $copyMaster->getSettings()->getEmailTitle());
        static::assertEquals($procedureData['settings']['emailTitle'], $newProcedure->getSettings()->getEmailTitle());
    }

    /**
     * Test to cover that (Procedure->)Files are still on masterTemplate as well as
     * a copy on the newly created procedure.
     * Check amount and relations of all affected (Procedure->)Files.
     */
    public function testProcedureFilesOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $templateProcedure = $this->getProcedureReference('masterBlaupause');
        $amountOfFilesBefore = $templateProcedure->getFiles()->count();
        self::assertGreaterThan(0, $amountOfFilesBefore);

        $newProcedureData = $this->newProcedureData($templateProcedure);

        $newlyCreatedProcedure = $this->sut->addProcedureEntity($newProcedureData, $this->loginTestUser()->getId());
        self::assertCount($amountOfFilesBefore, $templateProcedure->getFiles());
        self::assertCount($amountOfFilesBefore, $newlyCreatedProcedure->getFiles());

        foreach ($newlyCreatedProcedure->getFiles() as $newlyCreatedFile) {
            self::assertInstanceOf(File::class, $newlyCreatedFile);
            self::assertSame($newlyCreatedFile->getProcedure()->getId(), $newlyCreatedProcedure->getId());
        }

        foreach ($templateProcedure->getFiles() as $fileOfTemplateProcedure) {
            self::assertInstanceOf(File::class, $fileOfTemplateProcedure);
            self::assertSame($fileOfTemplateProcedure->getProcedure()->getId(), $templateProcedure->getId());
        }
    }

    /**
     * Test to cover that (Procedure->)Elements are still on masterTemplate as well as
     * a copy on the newly created procedure.
     * Check for amount and relations of all affected (Procedure->)Elements.
     */
    public function testElementsOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $templateProcedure = $this->getProcedureReference('masterBlaupause');
        $amountOfElements = $templateProcedure->getElements()->count();
        self::assertGreaterThan(0, $amountOfElements);

        $newProcedureData = $this->newProcedureData($templateProcedure);

        $newlyCreatedProcedure = $this->sut->addProcedureEntity($newProcedureData, $this->loginTestUser()->getId());

        self::assertCount($amountOfElements, $templateProcedure->getElements());
        self::assertCount($amountOfElements, $newlyCreatedProcedure->getElements());

        foreach ($templateProcedure->getElements() as $elementOfTemplateProcedure) {
            self::assertInstanceOf(Elements::class, $elementOfTemplateProcedure);
            self::assertSame($elementOfTemplateProcedure->getProcedure()->getId(), $templateProcedure->getId());
        }

        foreach ($newlyCreatedProcedure->getElements() as $newlyCreatedElement) {
            self::assertInstanceOf(Elements::class, $newlyCreatedElement);
            self::assertSame($newlyCreatedElement->getProcedure()->getId(), $newlyCreatedProcedure->getId());
        }
    }

    /**
     * Test to cover that (Procedure->Elements->)SingleDocuments are still on masterTemplate as well as
     * a copy on the newly created procedure.
     * Check for amount and relations of all affected (Procedure->Elements->)SingleDocuments.
     */
    public function testSingleDocumentsOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $templateProcedure = $this->getProcedureReference('masterBlaupause');
        $amountOfSingleDocuments = $this->countEntries(SingleDocument::class, ['procedure' => $templateProcedure]);
        self::assertGreaterThan(0, $amountOfSingleDocuments);
        $newProcedureData = $this->newProcedureData($templateProcedure);
        $newlyCreatedProcedure = $this->sut->addProcedureEntity($newProcedureData, $this->loginTestUser()->getId());

        self::assertCount($amountOfSingleDocuments, $templateProcedure->getElements());
        self::assertCount($amountOfSingleDocuments, $newlyCreatedProcedure->getElements());

        /** @var Elements $elementOfTemplateProcedure */
        foreach ($templateProcedure->getElements() as $elementOfTemplateProcedure) {
            foreach ($elementOfTemplateProcedure->getDocuments() as $documentOfTemplateProcedure) {
                self::assertInstanceOf(SingleDocument::class, $documentOfTemplateProcedure);
                self::assertSame($documentOfTemplateProcedure->getProcedure()->getId(), $templateProcedure->getId());
            }
        }

        /** @var Elements $newlyCreatedElement */
        foreach ($newlyCreatedProcedure->getElements() as $newlyCreatedElement) {
            foreach ($newlyCreatedElement->getDocuments() as $newlyCreatedDocument) {
                self::assertInstanceOf(SingleDocument::class, $newlyCreatedDocument);
                self::assertSame($newlyCreatedDocument->getProcedure()->getId(), $newlyCreatedProcedure->getId());
            }
        }

        self::assertSame(
            $amountOfSingleDocuments,
            $this->countEntries(SingleDocument::class, ['procedure' => $templateProcedure])
        );

        self::assertSame(
            $amountOfSingleDocuments,
            $this->countEntries(SingleDocument::class, ['procedure' => $newlyCreatedProcedure])
        );
    }

    /**
     * Test to cover that (Procedure->Elements->SingleDocuments)->Files are still on masterTemplate as well as
     * a copy on the newly created procedure.
     * Check for amount and relations of all affected (Procedure->Elements->SingleDocuments)->Files.
     */
    public function testFilesOfSingleDocumentsOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $templateProcedure = $this->getProcedureReference('masterBlaupause');
        $singleDocumentFileStrings = $this->getFileStringsOfSingleDocuments($templateProcedure);
        self::assertGreaterThan(0, count($singleDocumentFileStrings));

        // check for necessary test-data:
        foreach ($singleDocumentFileStrings as $fileString) {
            $file = $this->fileService->getFileFromFileString($fileString);
            self::assertInstanceOf(File::class, $file);
            self::assertSame(
                '20131112_OSBA_Leitfaden_zur_Datensicherheit.pdf',
                $file->getFilename()
            );
            self::assertSame(
                'df055eb7-5405-425b-9e21-7faa63f67777',
                $file->getHash()
            );
            self::assertSame($file->getProcedure()->getId(), $templateProcedure->getId());
        }

        // actual test case:
        $procedureData = $this->newProcedureData($templateProcedure);
        $newlyCreatedProcedure = $this->sut->addProcedureEntity($procedureData, $this->loginTestUser()->getId());

        // 2. create new procedure (blueprint-procedureID)
        $newlyCreatedSingleDocumentFileStrings = $this->getFileStringsOfSingleDocuments($newlyCreatedProcedure);
        self::assertCount(count($singleDocumentFileStrings), $newlyCreatedSingleDocumentFileStrings);

        foreach ($newlyCreatedSingleDocumentFileStrings as $fileString) {
            $file = $this->fileService->getFileFromFileString($fileString);
            self::assertSame($newlyCreatedProcedure->getId(), $file->getProcedure()->getId());
            self::assertInstanceOf(File::class, $file);
            self::assertSame(
                '20131112_OSBA_Leitfaden_zur_Datensicherheit.pdf',
                $file->getFilename()
            );
        }

        // 3. blueprintProcedure->element->singleDocument->file === count(1) === ID
        $blueprintSingleDocumentFileStrings = $this->getFileStringsOfSingleDocuments($templateProcedure);
        self::assertCount(count($singleDocumentFileStrings), $blueprintSingleDocumentFileStrings);

        foreach ($blueprintSingleDocumentFileStrings as $fileString) {
            $file = $this->fileService->getFileFromFileString($fileString);
            self::assertSame($templateProcedure->getId(), $file->getProcedure()->getId());
            self::assertInstanceOf(File::class, $file);
            self::assertSame(
                '20131112_OSBA_Leitfaden_zur_Datensicherheit.pdf',
                $file->getFilename()
            );
        }
    }

    /**
     * Test to cover that (Procedure->Elements)->Files are still on masterTemplate as well as
     * a copy on the newly created procedure.
     * Check for amount and relations of all affected (Procedure->Elements)->Files.
     */
    public function testElementsFilesOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $templateProcedure = $this->getProcedureReference('masterBlaupause');
        $amountOfElements = $templateProcedure->getElements()->count();
        self::assertGreaterThan(0, $amountOfElements);

        $newProcedureData = $this->newProcedureData($templateProcedure);
        $newlyCreatedProcedure = $this->sut->addProcedureEntity($newProcedureData, $this->loginTestUser()->getId());

        self::assertCount($amountOfElements, $templateProcedure->getElements());
        self::assertCount($amountOfElements, $newlyCreatedProcedure->getElements());

        foreach ($templateProcedure->getElements() as $elementOfTemplateProcedure) {
            self::assertInstanceOf(Elements::class, $elementOfTemplateProcedure);
            self::assertSame($elementOfTemplateProcedure->getProcedure()->getId(), $templateProcedure->getId());
        }

        foreach ($newlyCreatedProcedure->getElements() as $newlyCreatedElement) {
            self::assertInstanceOf(Elements::class, $newlyCreatedElement);
            self::assertSame($newlyCreatedElement->getProcedure()->getId(), $newlyCreatedProcedure->getId());
        }
    }

    /**
     * Set designated external phase and designated date of a specific procedure.
     * Necessary to enable switch of phase of a specific procedure.
     * A cronjob will switch the external phase of the procedure
     * to the designatedPhase on the given date.
     *
     * @param array $procedureUpdateData - procedure, whose external designated phase and designated date will be set
     *
     * @throws Exception
     */
    protected function setAndUpdateAutoSwitchPublic(
        array $procedureUpdateData,
        $designatedSwitchDate,
        ?string $designatedPhase,
    ): array {
        try {
            if ($this->isValidDesignatedPhase($designatedPhase)) {
                $procedureUpdateData['settings']['designatedPublicPhase'] = $designatedPhase;
                $procedureUpdateData['settings']['designatedPublicSwitchDate'] = $designatedSwitchDate?->format('d.m.Y H:i:s');
            } else {
                throw new InvalidArgumentException('Invalid phasekey: '.$designatedPhase);
            }

            return $this->sut->updateProcedure($procedureUpdateData);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Set designated phase and designated date of a specific procedure.
     * Necessary to enable switch of phase of a specific procedure.
     * The cronjob will switch the phase of the procedure
     * to the designatedPhase on the given date.
     *
     * @param array $procedureData - procedure, whose internal designated phase and designated date will be set
     *
     * @throws Exception
     */
    protected function setAndUpdateAutoSwitch(
        array $procedureData,
        ?DateTime $designatedSwitchDate,
        ?string $designatedPhase,
    ): array {
        try {
            if ($this->isValidDesignatedPhase($designatedPhase)) {
                $procedureData['settings']['designatedPhase'] = $designatedPhase;
                $procedureData['settings']['designatedSwitchDate'] = $designatedSwitchDate?->format('d.m.Y H:i:s');
            } else {
                throw new InvalidArgumentException('Invalid phasekey: '.$designatedPhase);
            }

            return $this->sut->updateProcedure($procedureData);
        } catch (Exception $e) {
            throw $e;
        }
    }

    /**
     * Checks if given string is in procedurephases.yml listed as internalPhases and therefore a "valid" phasekey.
     * Null is also a "valid" phase as "designatedPhase".
     *
     * @param string $phaseName - name of the phase, which will be checked
     *
     * @return bool - true if the given $phaseName is null or in the list of internal procedurephases of this project
     */
    protected function isValidDesignatedPhase($phaseName)
    {
        return in_array($phaseName, $this->globalConfig->getInternalPhaseKeys()) || null === $phaseName;
    }

    private function getReferenceProcedureType(string $name): ProcedureType
    {
        return $this->fixtures->getReference($name);
    }

    private function getTestProcedure(): Procedure
    {
        return $this->fixtures->getReference('testProcedure');
    }

    private function newProcedureData(Procedure $templateProcedure): array
    {
        return [
            'copymaster'                => $templateProcedure->getId(),
            'desc'                      => '',
            'startDate'                 => '01.02.2023',
            'endDate'                   => '01.02.2024',
            'externalName'              => 'testAdded',
            'name'                      => 'testAdded',
            'master'                    => false,
            'orgaId'                    => $this->testProcedure->getOrgaId(),
            'orgaName'                  => $this->testProcedure->getOrga()->getName(),
            'logo'                      => 'some:logodata:string',
            'publicParticipationPhase'  => 'configuration',
            'procedureType'             => $this->getReferenceProcedureType(LoadProcedureTypeData::BRK),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function getFileStringsOfSingleDocuments(Procedure $templateProcedure): array
    {
        $fileStrings = [];
        /** @var Elements $element */
        foreach ($templateProcedure->getElements() as $element) {
            foreach ($element->getDocuments() as $singleDocument) {
                if ('' !== $singleDocument->getDocument()) {
                    $fileStrings[] = $singleDocument->getDocument(); // returns fileString
                }
            }
        }

        return $fileStrings;
    }

    public function testDeleteDefaultCustomerBlueprint(): void
    {
        $currentUser = $this->currentUserService->getUser();
        $currentCustomer = $currentUser->getCurrentCustomer();
        $blueprintSetting = ProcedureSettingsFactory::createOne([
            'procedure' => ProcedureFactory::createOne([
                'master' => true,
                'customer' => $currentCustomer,
                'orgaName' => $currentUser->getOrga()->getName(),
            ]),
        ]);
        $blueprint = $blueprintSetting->getProcedure();
        $currentCustomer->setDefaultProcedureBlueprint($blueprint);
        $customerBlueprint = $currentCustomer->getDefaultProcedureBlueprint();
        $customerBlueprintId = $customerBlueprint->getId();


        static::assertInstanceOf(Procedure::class, $customerBlueprint);
        static::assertTrue($customerBlueprint->isCustomerMasterBlueprint());

        $this->sut->deleteProcedure([$customerBlueprintId]);
        $blueprint = $this->find(Procedure::class, $customerBlueprintId);

        //Still there, but flagged as deleted
        static::assertInstanceOf(Procedure::class, $blueprint);
        static::assertTrue($blueprint->isDeleted());
        static::assertNull($currentCustomer->getDefaultProcedureBlueprint());
    }

    /**
     * Creation of new procedure/blueprint by using an deleted blueprint, should lead to an InvalidArgumentException.
     */
    public function testExceptionOnUsageOfDeletedBlueprint(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $currentUser = $this->currentUserService->getUser();
        $currentCustomer = $currentUser->getCurrentCustomer();
        $blueprintSetting = ProcedureSettingsFactory::createOne([
            'procedure' => ProcedureFactory::createOne([
                'name' => 'deletedBlueprint',
                'master' => true,
                'deleted' => true,
                'customer' => $currentCustomer,
                'orgaName' => $currentUser->getOrga()->getName(),
            ]),
        ]);
        $deletedBlueprint = $blueprintSetting->getProcedure();
        static::assertTrue($deletedBlueprint->getDeleted());

        $this->sut->addProcedureEntity(
            [
                'copymaster' => $deletedBlueprint->getId(),
                'desc' => '',
                'startDate' => '01.02.2023',
                'endDate' => '01.02.2024',
                'externalName' => 'testAdded',
                'name' => 'testAdded',
                'master' => false,
                'orgaId' => $currentUser->getOrganisationId(),
                'orgaName' => $currentUser->getOrgaName(),
                'logo' => 'some:logodata:string',
                'publicParticipationPhase' => 'configuration',
                'procedureType' => $this->getReferenceProcedureType(LoadProcedureTypeData::BRK),
            ],
            $currentUser->getId()
        );
    }
}
