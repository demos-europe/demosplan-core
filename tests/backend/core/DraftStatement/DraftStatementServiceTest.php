<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\DraftStatement;

use DateTime;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Document\Elements;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementAttribute;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementMeta;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\DraftStatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementListUserFilter;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\DraftStatementResult;
use Exception;
use Tests\Base\FunctionalTestCase;

class DraftStatementServiceTest extends FunctionalTestCase
{
    /**
     * @var DraftStatementService
     */
    protected $sut;

    /**
     * @var DraftStatement
     */
    protected $testDraftStatement;

    protected $draftStatementArrayStructure = [
        'categories', 'createdDate', 'deleted', 'deletedDate', 'department', 'dId', 'dName', 'element', 'elementId',
        'elementTitle', 'externId', 'feedback', 'file', 'files', 'houseNumber', 'id', 'ident', 'lastModifiedDate', 'mapFile', 'miscData', 'negativ',
        'number', 'oGatewayName', 'oId', 'oName', 'organisation', 'phase', 'pId', 'polygon', 'procedure',
        'publicAllowed', 'publicDraftStatement', 'publicUseName', 'rejected', 'rejectedDate', 'rejectedReason',
        'released', 'releasedDate', 'represents', 'showToAll', 'statementAttributes', /* 'statementAttributesObject', */
        'submitted', 'submittedDate', 'text', 'title', 'uCity', 'uEmail', 'uId', 'uName', 'uPostalCode',
        'user', 'uStreet',
    ];
    /**
     * @var \demosplan\DemosPlanCoreBundle\Logic\Document\ElementsService|object|null
     */
    protected $elementsService;

    /**
     * @var string
     */
    private $text = 'Mein Text';

    /**
     * @var string
     */
    private $textNew = 'Ich bin der Neue';
    /**
     * @var string
     */
    private $otherUserName = 'ein ganz anderer';

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(DraftStatementService::class);
        $this->elementsService = self::getContainer()->get(ElementsService::class);
        $this->testDraftStatement = $this->fixtures->getReference('testDraftStatement');
    }

    public function testGetDraftStatementStructure()
    {
        self::markSkippedForCIIntervention();

        $draftStatement = $this->sut->getSingleDraftStatement(
            $this->testDraftStatement->getId()
        );
        $this->checkDraftStatementStructure($draftStatement);
        $this->checkId($draftStatement['ident']);
        static::assertEquals($this->testDraftStatement->getId(), $draftStatement['ident']);
    }

    public function testCopyDraftStatementVersion()
    {
        self::markSkippedForCIIntervention();

        $draftStatementVersions = $this->sut->getVersionList($this->testDraftStatement->getId());

        static::assertTrue(is_array($draftStatementVersions['result']));
        static::assertCount(0, $draftStatementVersions['result']);

        $data = [
            'ident' => $this->testDraftStatement->getId(),
            'text'  => $this->textNew,
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        $this->checkDraftStatementStructure($draftStatement);
        $this->checkId($draftStatement['ident']);
        static::assertEquals($this->testDraftStatement->getId(), $draftStatement['ident']);
        static::assertEquals($data['text'], $draftStatement['text']);

        $draftStatementVersions = $this->sut->getVersionList($this->testDraftStatement->getId());

        static::assertTrue(is_array($draftStatementVersions['result']));
        static::assertCount(1, $draftStatementVersions['result']);
        static::assertEquals($this->testDraftStatement->getId(), $draftStatementVersions['result'][0]['dsId']);
    }

    public function testUpdateDraftStatement()
    {
        self::markSkippedForCIIntervention();

        $oldDraftStatement = $this->sut->getSingleDraftStatement(
            $this->testDraftStatement->getId()
        );

        $data = [
            'ident' => $this->testDraftStatement->getId(),
            'text'  => $this->textNew,
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        $this->checkDraftStatementStructure($draftStatement);
        $this->checkId($draftStatement['ident']);
        static::assertEquals($this->testDraftStatement->getId(), $draftStatement['ident']);
        static::assertEquals($data['text'], $draftStatement['text']);

        static::assertEquals($oldDraftStatement['oName'], $draftStatement['oName']);
        static::assertNotSame($oldDraftStatement['text'], $draftStatement['text']);
    }

    public function testSetPriorityArea()
    {
        $draftStatement = $this->fixtures->getReference('testDraftStatement');
        $attributes = $draftStatement->getStatementAttributes();
        static::assertCount(0, $attributes);

        $data['statementAttributes']['priorityAreaKey'] = 'countyXY';
        $data['statementAttributes']['priorityAreaType'] = 'myType';
        $data['ident'] = $draftStatement->getId();

        $this->sut->updateDraftStatement($data);
        static::assertCount(2, $attributes);

        $this->sut->updateDraftStatement($data);
        static::assertCount(2, $attributes);
    }

    public function testUpdateDraftStatementFail()
    {
        $data = [
            'ident' => 'ich bin falsch',
        ];
        $updated = $this->sut->updateDraftStatement($data);
        static::assertFalse($updated);

        $updated = $this->sut->updateDraftStatement([]);
        static::assertFalse($updated);
    }

    /**
     * Test general REsult structure.
     */
    protected function checkDraftStatementStructure($draftStatement)
    {
        static::assertTrue(is_array($draftStatement));

        $addToAssertedStructure = [];

        // add additional keys to the asserted Structure:
        if (isset($draftStatement['documentId'])) {
            $addToAssertedStructure[] = 'documentId';
            $addToAssertedStructure[] = 'document';
            static::assertTrue(is_string($draftStatement['documentId']));
            static::assertTrue(is_array($draftStatement['document']));
        }

        if (isset($draftStatement['paragraphId'])) {
            $addToAssertedStructure[] = 'paragraphId';
            $addToAssertedStructure[] = 'paragraph';
            static::assertTrue(is_string($draftStatement['paragraphId']));
            static::assertTrue(is_array($draftStatement['paragraph']));
        }

        $assertedStructure = array_merge($this->draftStatementArrayStructure, $addToAssertedStructure);
        foreach ($assertedStructure as $key) {
            static::assertArrayHasKey($key, $draftStatement);
        }
        // check whether all keys are defined in assertedStructure

        // statementAttributesObject fehlt in assertedStructure
        static::assertEquals(count($assertedStructure), count($draftStatement));
        static::assertTrue(is_array($draftStatement['categories']));
        static::assertTrue(is_numeric($draftStatement['createdDate']));
        static::assertTrue(is_bool($draftStatement['deleted']));
        static::assertTrue(is_numeric($draftStatement['deletedDate']));

        static::assertTrue(is_array($draftStatement['element']));
        static::assertArrayHasKey('elementId', $draftStatement);
        static::assertArrayHasKey('feedback', $draftStatement);
        static::assertArrayHasKey('file', $draftStatement);
        static::assertArrayHasKey('files', $draftStatement);
        static::assertArrayHasKey('ident', $draftStatement);
        static::assertArrayHasKey('lastModifiedDate', $draftStatement);
        static::assertTrue(is_numeric($draftStatement['lastModifiedDate']));
        static::assertTrue(is_bool($draftStatement['negativ']));

        static::assertTrue(is_bool($draftStatement['publicAllowed']));
        static::assertTrue(is_bool($draftStatement['publicUseName']));
        static::assertTrue(is_bool($draftStatement['rejected']));
        static::assertTrue(is_bool($draftStatement['released']));
        static::assertTrue(is_numeric($draftStatement['releasedDate']));
        static::assertTrue(is_bool($draftStatement['showToAll']));
        static::assertTrue(is_bool($draftStatement['submitted']));
        static::assertTrue(is_numeric($draftStatement['submittedDate']));

        static::assertTrue(is_string($draftStatement['text']));
        static::assertTrue(is_string($draftStatement['title']));
        static::assertTrue(is_string($draftStatement['uId']));
        static::assertTrue(is_string($draftStatement['uName']));
        static::assertTrue(is_string($draftStatement['uPostalCode']));
        static::assertTrue(is_string($draftStatement['uStreet']));
        static::assertTrue(is_string($draftStatement['phase']));
        static::assertTrue(is_string($draftStatement['represents']));
        static::assertTrue(is_string($draftStatement['publicDraftStatement']));
        static::assertTrue(is_string($draftStatement['pId']));
        static::assertTrue(is_string($draftStatement['polygon']));
        static::assertTrue(is_string($draftStatement['elementId']));
        static::assertTrue(is_string($draftStatement['feedback']));
        static::assertTrue(is_string($draftStatement['ident']));
        static::assertTrue(is_string($draftStatement['file']));
        static::assertTrue(is_array($draftStatement['files']));
        static::assertTrue(is_string($draftStatement['oName']));
        static::assertTrue(is_string($draftStatement['dName']));
        static::assertTrue(is_string($draftStatement['dId']));

        static::assertTrue(is_bool($draftStatement['negativ']));
        static::assertTrue(is_numeric($draftStatement['lastModifiedDate']));
        static::assertTrue(is_numeric($draftStatement['number']));
    }

    public function testAddDraftStatement()
    {
        self::markSkippedForCIIntervention();

        $data = $this->getBaseDraftStatementData();
        $data['statementAttributes'] = ['noLocation' => 1];

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));

        // StatementAttributesObject is added to $draftStatement. pls check;
        $this->checkDraftStatementStructure($draftStatement);
        static::assertEquals($data['pId'], $draftStatement['pId']);
        static::assertEquals($data['text'], $draftStatement['text']);
        static::assertEquals($data['uId'], $draftStatement['uId']);
        static::assertEquals($data['uName'], $draftStatement['uName']);
        static::assertEquals($data['dId'], $draftStatement['dId']);
        static::assertEquals($data['dName'], $draftStatement['dName']);
        static::assertEquals($data['oId'], $draftStatement['oId']);
        static::assertEquals($data['oName'], $draftStatement['oName']);

        // prüfe Standardwerte
        static::assertFalse($draftStatement['deleted']);
        static::assertCount(20, $draftStatement['element']);
        static::assertTrue(is_array($draftStatement['element']));
        static::assertFalse($draftStatement['negativ']);
        static::assertEquals(1001, $draftStatement['number']);
        static::assertTrue(!isset($draftStatement['paragraph']));
        static::assertFalse($draftStatement['publicAllowed']);
        static::assertFalse($draftStatement['publicUseName']);
        static::assertFalse($draftStatement['rejected']);
        static::assertFalse($draftStatement['released']);
        static::assertFalse($draftStatement['showToAll']);
        static::assertFalse($draftStatement['submitted']);
        static::assertTrue(is_string($draftStatement['title']));
        static::assertEquals('', $draftStatement['title']);
        static::assertEquals('1', $draftStatement['statementAttributes']['noLocation']);
    }

    public function testAddDraftStatementMiscData()
    {
        self::markSkippedForCIIntervention();

        $data = $this->getBaseDraftStatementData();
        $overrides = [
            'text'                => 'Mein MiscData Test',
            'miscData'            => [
                'one'    => 'first',
                'two'    => true,
                'third'  => 3,
                'fourth' => [],
                'fifths' => ['one' => 'first'],
                'sixths' => '',
            ],
        ];
        $data = array_merge($data, $overrides);

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));

        static::assertEquals($data['miscData'], $draftStatement['miscData']);
        foreach ($data['miscData'] as $key => $value) {
            static::assertEquals($value, $draftStatement['miscData'][$key]);
        }
    }

    public function testAddDraftStatementParagraph()
    {
        self::markSkippedForCIIntervention();

        $data = $this->getBaseDraftStatementData();
        $overrides = [
            'paragraph' => $this->fixtures->getReference('testParagraph1'),
        ];
        $data = array_merge($data, $overrides);

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));
        $this->checkDraftStatementStructure($draftStatement);
        static::assertEquals($data['pId'], $draftStatement['pId']);
    }

    public function testAddDraftStatementSingleDocument()
    {
        self::markSkippedForCIIntervention();

        $document = $this->fixtures->getReference('testSingleDocument1');
        $data = [
            'documentId' => $document->getId(),
            'pId'        => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
            'elementId'  => $this->fixtures->getReference('testElement1')->getId(),
            'text'       => 'Mein Text zum SingleDocument',
            'uId'        => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
            'uName'      => $this->otherUserName,
            'dId'        => $this->fixtures->getReference('testDepartment')->getId(),
            'dName'      => $this->fixtures->getReference('testDepartment')->getName(),
            'oId'        => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            'oName'      => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
        ];

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));
        $this->checkDraftStatementStructure($draftStatement);
        static::assertEquals($data['pId'], $draftStatement['pId']);
    }

    public function testAddDraftStatementFile(): void
    {
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFileString();

        $data = $this->getBaseDraftStatementData();
        $overrides = [
            'statementAttributes'     => [
                'noLocation' => 1,
            ],
            'files'                   => [$fileString1],
            'miscData'                => [
                StatementMeta::SUBMITTER_ROLE    => StatementMeta::SUBMITTER_ROLE_PUBLIC_AGENCY,
                StatementMeta::USER_ORGANISATION => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            ],
        ];
        $data = array_merge($data, $overrides);

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));

        static::assertCount(1, $draftStatement['files']);
        static::assertEquals($fileString1, $draftStatement['files'][0]);
    }

    public function testAddDraftStatementFileAsString(): void
    {
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFileString();

        $data = $this->getBaseDraftStatementData();
        $overrides = [
            'statementAttributes'     => [
                'noLocation' => 1,
            ],
            'files'                   => $fileString1,
            'miscData'                => [
                StatementMeta::SUBMITTER_ROLE    => StatementMeta::SUBMITTER_ROLE_PUBLIC_AGENCY,
                StatementMeta::USER_ORGANISATION => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            ],
        ];
        $data = array_merge($data, $overrides);

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));

        static::assertCount(1, $draftStatement['files']);
        static::assertEquals($fileString1, $draftStatement['files'][0]);
    }

    public function testAddDraftStatementFiles(): void
    {
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFileString();
        $testFile2 = $this->getFileReference('testFile2');
        $fileString2 = $testFile2->getFileString();

        $data = $this->getBaseDraftStatementData();
        $overrides = [
            'statementAttributes'     => [
                'noLocation' => 1,
            ],
            'files'                   => [$fileString1, $fileString2],
            'miscData'                => [
                StatementMeta::SUBMITTER_ROLE    => StatementMeta::SUBMITTER_ROLE_PUBLIC_AGENCY,
                StatementMeta::USER_ORGANISATION => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            ],
        ];
        $data = array_merge($data, $overrides);

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));

        static::assertCount(2, $draftStatement['files']);
        static::assertEquals($fileString1, $draftStatement['files'][0]);
        static::assertEquals($fileString2, $draftStatement['files'][1]);
    }

    public function testUpdateDraftStatementFiles()
    {
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFileString();

        $data = [
            'ident' => $this->testDraftStatement->getId(),
            'text'  => $this->textNew,
            'files' => [$fileString1],
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertCount(1, $draftStatement['files']);
        static::assertEquals($fileString1, $draftStatement['files'][0]);
    }

    public function testAddStatementAttribute()
    {
        $before = $this->countEntries(StatementAttribute::class);

        /** @var DraftStatement $draftStatement */
        $draftStatement = $this->fixtures->getReference('testSubmittedDraftStatementOtherOrga4');
        $draftStatementId = $draftStatement->getId();
        static::assertCount(0, $draftStatement->getStatementAttributes());

        // create attribute relation by using updateDraftStatement:
        $data['statementAttributes'] = ['noLocation' => true];
        $data['ident'] = $draftStatementId;
        $this->sut->updateDraftStatement($data);

        // check attributes of object
        $draftStatementAttributes = $draftStatement->getStatementAttributes();
        static::assertNotEmpty($draftStatementAttributes);
        static::assertCount(1, $draftStatementAttributes);
        static::assertEquals($draftStatementId, $draftStatementAttributes[0]->getDraftStatement()->getId());

        // check amount entities of _statement_attributes in DB
        $after = $this->countEntries(StatementAttribute::class);
        static::assertEquals($before + 1, $after);
    }

    public function testDeleteDraftStatement()
    {
        self::markSkippedForCIIntervention();

        $attributesBefore = $this->countEntries(StatementAttribute::class);
        // todo: add attribute and all available relations to draftstatemt
        // to test relations are deleted after delete draftstatement

        /** @var DraftStatement $draftStatement */
        $draftStatement = $this->fixtures->getReference('testSubmittedDraftStatementOtherOrga4');
        $draftStatementId = $draftStatement->getId();
        static::assertCount(0, $draftStatement->getStatementAttributes());

        // create attribute relation by using updateDraftStatement:
        $data['statementAttributes'] = ['noLocation' => true];
        $data['ident'] = $draftStatementId;
        $this->sut->updateDraftStatement($data);

        $draftStatementAttributes = $draftStatement->getStatementAttributes();
        static::assertNotEmpty($draftStatementAttributes);
        static::assertCount(2, $draftStatementAttributes);

        $deleted = $this->sut->deleteDraftStatementById($draftStatement->getId());
        static::assertTrue($deleted);

        $deletedDraftStatement = $this->sut->getDraftStatement($draftStatementId);
        static::assertNull($deletedDraftStatement);

        foreach ($draftStatementAttributes as $draftStatementAttribute) {
            $relatedDraftStatement = $draftStatementAttribute->getDraftStatement();
            $id = is_null($relatedDraftStatement) ? null : $relatedDraftStatement->getId();
            static::assertNotEquals($draftStatementId, $id);
        }

        // is deleted from DB?
        $attributesAfter = $this->countEntries(StatementAttribute::class);
        static::assertEquals($attributesBefore, $attributesAfter);
    }

    public function testGetEigeneStellungnahmenEntwuerfe()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scope = 'own';
        $filters = new StatementListUserFilter();
        $filters->setReleased(false)->setSubmitted(false);
        $search = null;
        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);

        // test structure
        $this->listResultStructureTestResultNoActiveFilters($draftStatementList, 'department');
    }

    public function testGetDraftStatementListNoResult()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = 'notexistant';
        $scope = 'own';
        $filters = new StatementListUserFilter();
        $filters->setReleased(false)->setSubmitted(false);
        $search = null;
        $sort = null;
        $manualSortScope = null;
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $draftStatementListNothing = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);

        // test structure
        $this->listResultStructureTestNoResult($draftStatementListNothing);

        // test values
        static::assertFalse($draftStatementListNothing->getManuallySorted());
        static::assertCount(0, $draftStatementListNothing->getResult());
        static::assertEquals('', $draftStatementListNothing->getSearch());
        static::assertCount(0, $draftStatementListNothing->getSortingSet());
        static::assertEquals(0, $draftStatementListNothing->getTotal());
    }

    public function testSubmitDraftStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scopeOwn = 'own';
        $scopeGroup = 'group';
        $filtersDraft = new StatementListUserFilter();
        $filtersDraft->setReleased(false)->setSubmitted(false);
        $filtersSubmitted = new StatementListUserFilter();
        $filtersSubmitted->setReleased(true)->setSubmitted(true);
        $filtersReleased = new StatementListUserFilter();
        $filtersReleased->setReleased(true)->setSubmitted(false);
        $search = null;
        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // initial Drafts
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scopeOwn, $filtersDraft, $search, $sort, $user, $manualSortScope);
        $numDraftStatements = count($draftStatementList->getResult());
        static::assertTrue(1 <= $numDraftStatements);

        // initial Released
        $draftStatementListReleased = $this->sut->getDraftStatementList($procedureId, $scopeOwn, $filtersReleased, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsReleased = count($draftStatementListReleased->getResult());

        // initial Released group
        $draftStatementListReleasedGroup = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersReleased, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsReleasedGroup = count($draftStatementListReleasedGroup->getResult());

        // initial Submitted
        $draftStatementListSubmitted = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersSubmitted, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsSubmitted = count($draftStatementListSubmitted->getResult());

        // test submit
        $result = $this->sut->releaseDraftStatement([$draftStatementList->getResult()[0]['ident']]);
        static::assertTrue($result);
        $result = $this->sut->submitDraftStatement([$draftStatementList->getResult()[0]['ident']], $this->fixtures->getReference('testUserPlanningOffice'));
        static::assertTrue(is_array($result));
        static::assertCount(1, $result);

        // test Draft
        $draftStatementListAfter = $this->sut->getDraftStatementList($procedureId, $scopeOwn, $filtersDraft, $search, $sort, $user, $manualSortScope);
        static::assertTrue(is_array($draftStatementListAfter->getResult()));
        static::assertTrue(count($draftStatementListAfter->getResult()) == ($numDraftStatements - 1));

        // initial Released
        /* problematisch zu testen, weil doctrine mit sqlite keine sekundengenauen Datumsvergleich ermöglicht
                $draftStatementListReleased = $this->sut->getDraftStatementList($procedureId, $scopeOwn, $filtersReleased, $search, $sort, $user, $manualSortScope);
                static::assertTrue(count($draftStatementListReleased->getResult()) ==  $numDraftStatementsReleased);
        */
        // initial Released Group
        $draftStatementListReleasedGroup = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersReleased, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListReleasedGroup->getResult()) == $numDraftStatementsReleasedGroup);

        // initial Submitted
        $draftStatementListSubmitted = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersSubmitted, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListSubmitted->getResult()) == ($numDraftStatementsSubmitted + 1));
    }

    public function testSubmitDraftStatementMiscData()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $data = [
            'pId'                 => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
            'text'                => 'Mein MiscData Test',
            'uId'                 => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
            'uName'               => $this->otherUserName,
            'dId'                 => $this->fixtures->getReference('testDepartment')->getId(),
            'dName'               => $this->fixtures->getReference('testDepartment')->getName(
            ),
            'oId'                 => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            'oName'               => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            'elementId'           => $this->fixtures->getReference('testElement1')->getId(
            ),
            'statementAttributes' => [
                'noLocation' => 1,
            ],
            'miscData'            => [
                'userGroup'        => 'myGroup',
                'userOrganisation' => 'myOrga',
                'userPosition'     => 'myPosition',
                'userState'        => 'myState',
            ],
        ];

        $draftStatement = $this->sut->addDraftStatement($data);
        // test submit
        $result = $this->sut->releaseDraftStatement(
            [$draftStatement['id']]
        );
        static::assertTrue($result);
        $result = $this->sut->submitDraftStatement(
            [$draftStatement['id']],
            $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)
        );
        static::assertTrue(is_array($result));
        static::assertEquals($data['miscData'], $result[0]['meta']['miscData']);
        foreach ($data['miscData'] as $key => $value) {
            static::assertEquals($value, $result[0]['meta']['miscData'][$key]);
        }
    }

    public function testReleaseDraftStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scope = 'own';
        $scopeGroup = 'group';
        $filtersDraft = new StatementListUserFilter();
        $filtersDraft->setReleased(false)->setSubmitted(false);
        $filtersSubmitted = new StatementListUserFilter();
        $filtersSubmitted->setReleased(true)->setSubmitted(true);
        $filtersReleased = new StatementListUserFilter();
        $filtersReleased->setReleased(true)->setSubmitted(false);
        $search = null;
        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // initial Drafts
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filtersDraft, $search, $sort, $user, $manualSortScope);
        $numDraftStatements = count($draftStatementList->getResult());

        // initial Released
        $draftStatementListReleased = $this->sut->getDraftStatementList($procedureId, $scope, $filtersReleased, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsReleased = count($draftStatementListReleased->getResult());

        // initial Released Group
        $draftStatementListReleasedGroup = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersReleased, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsReleasedGroup = count($draftStatementListReleasedGroup->getResult());

        // initial Submitted
        $draftStatementListSubmitted = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersSubmitted, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsSubmitted = count($draftStatementListSubmitted->getResult());

        // test release
        $result = $this->sut->releaseDraftStatement([$draftStatementList->getResult()[0]['ident']]);
        static::assertTrue($result);

        // test Draft
        $draftStatementListAfter = $this->sut->getDraftStatementList($procedureId, $scope, $filtersDraft, $search, $sort, $user, $manualSortScope);
        static::assertTrue(is_array($draftStatementListAfter->getResult()));
        static::assertTrue(count($draftStatementListAfter->getResult()) == ($numDraftStatements - 1));

        // initial Released
        $draftStatementListReleased = $this->sut->getDraftStatementList($procedureId, $scope, $filtersReleased, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListReleased->getResult()) == ($numDraftStatementsReleased + 1));

        // initial Released Group
        $draftStatementListReleasedGroup = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersReleased, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListReleasedGroup->getResult()) == ($numDraftStatementsReleasedGroup + 1));

        // initial Submitted
        $draftStatementListSubmitted = $this->sut->getDraftStatementList($procedureId, $scopeGroup, $filtersSubmitted, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListSubmitted->getResult()) == $numDraftStatementsSubmitted);
    }

    public function testRejectDraftStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scope = 'own';
        $scopeSubmitted = 'group';
        $filtersDraft = new StatementListUserFilter();
        $filtersDraft->setReleased(false)->setSubmitted(false);
        $filtersSubmitted = new StatementListUserFilter();
        $filtersSubmitted->setReleased(true)->setSubmitted(true);
        $filtersReleased = new StatementListUserFilter();
        $filtersReleased->setReleased(true)->setSubmitted(false);
        $search = null;
        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // initial Drafts
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filtersDraft, $search, $sort, $user, $manualSortScope);
        $numDraftStatements = count($draftStatementList->getResult());

        // initial Released
        $draftStatementListReleased = $this->sut->getDraftStatementList($procedureId, $scope, $filtersReleased, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsReleased = count($draftStatementListReleased->getResult());

        // initial Submitted
        $draftStatementListSubmitted = $this->sut->getDraftStatementList($procedureId, $scope, $filtersSubmitted, $search, $sort, $user, $manualSortScope);
        $numDraftStatementsSubmitted = count($draftStatementListSubmitted->getResult());

        // release one
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filtersDraft, $search, $sort, $user, $manualSortScope);
        $draftStatementId = $draftStatementList->getResult()[0]['ident'];

        $result = $this->sut->releaseDraftStatement([$draftStatementId]);
        static::assertTrue($result);

        // test reject
        $rejectReason = 'I am the reason';
        $resultReject = $this->sut->rejectDraftStatement(
            $draftStatementId,
            $rejectReason
        );
        static::assertTrue($resultReject);
        $resultRejectStatement = $this->sut->getSingleDraftStatement(
            $draftStatementId
        );
        static::assertFalse(DateTime::createFromFormat('d.m.Y', '2.1.1970')->getTimestamp() * 1000 == $resultRejectStatement['rejectedDate']);
        static::assertEquals($rejectReason, $resultRejectStatement['rejectedReason']);
        static::assertTrue($resultRejectStatement['rejected']);
        static::assertFalse($resultRejectStatement['released']);

        // Draft
        $draftStatementListAfter = $this->sut->getDraftStatementList($procedureId, $scope, $filtersDraft, $search, $sort, $user, $manualSortScope);
        static::assertTrue(is_array($draftStatementListAfter->getResult()));
        static::assertTrue(count($draftStatementListAfter->getResult()) == $numDraftStatements);

        // Released own
        $draftStatementListReleased = $this->sut->getDraftStatementList($procedureId, $scope, $filtersReleased, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListReleased->getResult()) == $numDraftStatementsReleased);

        // Submitted
        $draftStatementListSubmitted = $this->sut->getDraftStatementList($procedureId, $scopeSubmitted, $filtersSubmitted, $search, $sort, $user, $manualSortScope);
        static::assertTrue(count($draftStatementListSubmitted->getResult()) == $numDraftStatementsSubmitted);
    }

    public function testGetOtherCompaniesPublicDraftStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $draftStatementId = $this->fixtures->getReference('testDraftStatementOtherOrga')->getId();

        $filters = new StatementListUserFilter();

        $search = null;
        $sort = null;
        $manualSortScope = null;
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        /** @var User $planningOfficeUer */
        $planningOfficeUer = $this->fixtures->getReference('testUserPlanningOffice');

        // Reiche die teststellungnahme erst einmal ein
        $result = $this->sut->releaseDraftStatement([$draftStatementId]);
        static::assertTrue($result);
        $result = $this->sut->submitDraftStatement([$draftStatementId], $planningOfficeUer);
        static::assertTrue(is_array($result));
        static::assertCount(1, $result);

        // initial Drafts
        $draftStatementOtherCompaniesList =
            $this->sut->getDraftStatementListFromOtherCompanies(
                $procedureId,
                $filters,
                $search,
                $sort,
                $user,
                $manualSortScope
            );

        static::assertCount(2, $draftStatementOtherCompaniesList->getResult());

        static::assertEquals(
            $this->fixtures->getReference('testDraftStatementOtherOrga')->getText(),
            $draftStatementOtherCompaniesList->getResult()[0]['text']
        );
    }

    public function testOwnReleaseDraftStatementIdenticalOnChangeByKoordinator()
    {
        self::markSkippedForCIIntervention();
        // Derzeit nicht testbar, weil ein Test auf dem sekundengenauen Vergleich von dsv.versionDate mit ds.releasedDate
        // arbeiten müsste. Dieses scheint in sqlite mit der notwendigen Fehlertoleranz von einigen Sekungen nicht möglich;
    }

    protected function listResultStructureTestNoResult(DraftStatementResult $result): void
    {
        $filterSet = $result->getFilterSet();
        static::assertIsArray($filterSet);
        static::assertArrayHasKey('limit', $filterSet);
        static::assertArrayHasKey('offset', $filterSet);
        static::assertArrayHasKey('total', $filterSet);
        static::assertIsArray($result->getResult());
        static::assertIsArray($result->getSortingSet());
    }

    protected function listResultStructureTestResultNoActiveFilters(DraftStatementResult $result, $filterNameMustExist): void
    {
        $this->listResultStructureTestNoResult($result);

        static::assertIsArray($result->getResult());
        static::assertIsArray($result->getResult()[0]);
        static::assertIsArray($result->getSortingSet());
    }

    public function testSearchEigeneStellungnahmenEntwuerfe()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scope = 'own';
        $filters = new StatementListUserFilter();
        $filters->setReleased(false)->setSubmitted(false);

        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $data = [
            'pId'       => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
            'text'      => 'Finde mich, ich bin hier!',
            'uId'       => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
            'uName'     => 'vollkommen egal',
            'dId'       => $this->fixtures->getReference('testDepartment')->getId(),
            'dName'     => $this->fixtures->getReference('testDepartment')->getName(),
            'oId'       => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            'oName'     => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            'elementId' => $this->fixtures->getReference('testElement1')->getId(),
        ];

        $draftStatement = $this->sut->addDraftStatement($data);
        static::assertTrue(is_array($draftStatement));

        $search = 'mich';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementList->getResult());
        static::assertTrue(false !== stripos($draftStatementList->getResult()[0]['text'], $search));
        unset($draftStatementList);

        $search = 'Finde mich';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementList->getResult());
        static::assertTrue(false !== stripos($draftStatementList->getResult()[0]['text'], $search));
        unset($draftStatementList);

        $search = 'ich bin hier!';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementList->getResult());
        static::assertTrue(false !== stripos($draftStatementList->getResult()[0]['text'], $search));
        unset($draftStatementList);

        $search = 'Statement';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(2, $draftStatementList->getResult());
        static::assertTrue(false !== stripos($draftStatementList->getResult()[0]['text'], $search));
        unset($draftStatementList);

        $search = 'mich gibt es keinesfalls!';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(0, $draftStatementList->getResult());
        unset($draftStatementList);
    }

    public function testSearchEigeneFreigaben()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scope = 'own';
        $filtersDraft = new StatementListUserFilter();
        $filtersDraft->setReleased(false)->setSubmitted(false);
        $filters = new StatementListUserFilter();
        $filters->setReleased(true)->setSubmitted(false);
        $search = null;
        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // initial Drafts
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filtersDraft, $search, $sort, $user, $manualSortScope);

        // test release
        $result = $this->sut->releaseDraftStatement([$draftStatementList->getResult()[0]['ident']]);
        static::assertTrue($result);
        unset($draftStatementList);

        $search = 'Text';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementList->getResult());
        static::assertTrue(false !== stripos($draftStatementList->getResult()[0]['text'], $search));
        unset($draftStatementList);

        $search = 'Ich bin';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementList->getResult());
        static::assertTrue(false !== stripos($draftStatementList->getResult()[0]['text'], $search));
        unset($draftStatementList);

        $search = '';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementList->getResult());
        unset($draftStatementList);

        $search = 'mich gibt es keinesfalls!';
        $draftStatementList = $this->sut->getDraftStatementList($procedureId, $scope, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(0, $draftStatementList->getResult());
        unset($draftStatementList);
    }

    public function testSearchStellungnahmenOtherCompanies()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        $scope = 'own';
        $filters = new StatementListUserFilter();
        $filters->setReleased(false)->setSubmitted(false);

        $sort = null;
        $manualSortScope = null;
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $search = 'anderen Orga';
        $draftStatementOtherCompaniesList = $this->sut->getDraftStatementListFromOtherCompanies($procedureId, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementOtherCompaniesList->getResult());
        static::assertTrue(false !== stripos($draftStatementOtherCompaniesList->getResult()[0]['text'], $search));
        unset($draftStatementOtherCompaniesList);

        $search = '';
        $draftStatementOtherCompaniesList = $this->sut->getDraftStatementListFromOtherCompanies($procedureId, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(1, $draftStatementOtherCompaniesList->getResult());
        unset($draftStatementOtherCompaniesList);

        $search = 'mich gbt es sicher nicht';
        $draftStatementOtherCompaniesList = $this->sut->getDraftStatementListFromOtherCompanies($procedureId, $filters, $search, $sort, $user, $manualSortScope);
        static::assertCount(0, $draftStatementOtherCompaniesList->getResult());
        unset($draftStatementOtherCompaniesList);
    }

    public function testGetDraftStatement()
    {
        $draftStatement = $this->sut->getDraftStatement($this->fixtures->getReference('testDraftStatement'));
        static::assertEquals($this->fixtures->getReference('testDraftStatement')->getId(), $draftStatement['ident']);
    }

    public function testGetDraftStatementObject()
    {
        $draftStatement = $this->sut->getDraftStatementObject($this->fixtures->getReference('testDraftStatement'));
        static::assertInstanceOf('\demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement', $draftStatement);
        static::assertEquals($this->fixtures->getReference('testDraftStatement')->getId(), $draftStatement->getId());
    }

    public function testSetNoLocation()
    {
        $data = [
            'ident'               => $this->testDraftStatement->getId(),
            'text'                => $this->textNew,
            'statementAttributes' => [
                'noLocation' => 1,
            ],
        ];

        static::assertCount(0, $this->testDraftStatement->getStatementAttributesByType('noLocation'));
        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayHasKey('noLocation', $draftStatement['statementAttributes']);
    }

    public function testUnsetNoLocationByPolygon()
    {
        $data = [
            'ident'               => $this->testDraftStatement->getId(),
            'text'                => $this->textNew,
            'statementAttributes' => [
                'noLocation' => 1,
            ],
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayHasKey('noLocation', $draftStatement['statementAttributes']);

        $data = [
            'ident'   => $this->testDraftStatement->getId(),
            'polygon' => 'ich bin ein polygonstring', ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayNotHasKey('noLocation', $draftStatement['statementAttributes']);
    }

    public function testUnsetNoLocationByPriorityArea()
    {
        self::markSkippedForCIIntervention();

        $data = [
            'ident'               => $this->testDraftStatement->getId(),
            'text'                => $this->textNew,
            'statementAttributes' => [
                'noLocation' => 1,
            ],
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayHasKey('noLocation', $draftStatement['statementAttributes']);

        $draftStatement = $this->sut->updateDraftStatement($data);

        $data = [
            'ident'               => $this->testDraftStatement->getId(),
            'statementAttributes' => [
                'priorityArea' => 'ich bin eine priorityArea',
            ],
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayNotHasKey('noLocation', $draftStatement['statementAttributes']);
    }

    public function testUnsetNoLocationByCounty()
    {
        $data = [
            'ident'               => $this->testDraftStatement->getId(),
            'text'                => $this->textNew,
            'statementAttributes' => [
                'noLocation' => 1,
            ],
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayHasKey('noLocation', $draftStatement['statementAttributes']);

        $draftStatement = $this->sut->updateDraftStatement($data);

        $data = [
            'ident'               => $this->testDraftStatement->getId(),
            'statementAttributes' => [
                'county' => 'ich bin eine county',
            ],
        ];

        $draftStatement = $this->sut->updateDraftStatement($data);
        static::assertArrayNotHasKey('noLocation', $draftStatement['statementAttributes']);
    }

    public function testGetAllStatements()
    {
        $allStatements = $this->sut->getAllDraftStatements();
        static::assertCount(8, $allStatements);
    }

    public function testResetAttributesOfReleasedDraftStatements()
    {
        $draftStatementIds = [];
        $draftStatementIds[] = $this->fixtures->getReference('testDraftStatement')->getId();
        $draftStatementIds[] = $this->fixtures->getReference('testDraftStatement2')->getId();
        $draftStatementIds[] = $this->fixtures->getReference('testDraftStatementOtherOrga')->getId();
        $draftStatementIds[] = $this->fixtures->getReference('testDraftStatementOtherOrga2')->getId();
        $draftStatementIds[] = $this->fixtures->getReference('testReleasedDraftStatementOtherOrga3')->getId();
        $draftStatementIds[] = $this->fixtures->getReference('testSubmittedDraftStatementOtherOrga4')->getId();

        $resetDraftStatementsSuccessfully = $this->sut->resetReleasedDraftStatements($draftStatementIds);
        static::assertTrue($resetDraftStatementsSuccessfully);

        foreach ($draftStatementIds as $id) {
            $draftStatement = $this->sut->getDraftStatementObject($id);
            static::assertFalse($draftStatement->isReleased());
            static::assertEquals('02.01.1970', $draftStatement->getReleasedDate()->format('d.m.Y')
            );
        }
    }

    public function testResetDraftStatementsOfOrganisation()
    {
        /** @var Orga $organisation */
        $organisation = $this->fixtures->getReference('testOrgaFP');

        $this->logIn($this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
        $result = $this->sut->resetDraftStatementsOfProceduresOfOrga($organisation);

        static::assertTrue($result);
    }

    public function testGetDraftStatementReleasedOwnList()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $testProcedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();

        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        $testDraftStatementId = $testDraftStatement->getId();

        /** @var DraftStatement $testDraftStatement2 */ // is not a statement of the user
        $testDraftStatement2 = $this->fixtures->getReference('testDraftStatement2');
        $testDraftStatementId2 = $testDraftStatement2->getId();

        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // check setup:
        static::assertFalse($testDraftStatement->isReleased());
        static::assertEquals($user->getId(), $testDraftStatement->getUser()->getId());
        static::assertEquals($testProcedureId, $testDraftStatement->getProcedure()->getId());

        static::assertFalse($testDraftStatement2->isReleased());
        static::assertNotEquals($user->getId(), $testDraftStatement2->getUser()->getId());
        static::assertEquals($testProcedureId, $testDraftStatement2->getProcedure()->getId());

        // execute method of interest:
        $list = $this->sut->getDraftStatementReleasedOwnList(
            $testProcedureId,
            ['released' => true],
            null,
            null,
            $user
        );

        // check results becase of no draftstatements are released
        static::assertEmpty($list->getResult());

        // create own released statement:
        $this->sut->releaseDraftStatement([$testDraftStatementId]);
        // will be released, but not shown in own list, because it isnt a draftstatement of the user
        $this->sut->releaseDraftStatement([$testDraftStatementId2]);

        $list = $this->sut->getDraftStatementReleasedOwnList(
            $testProcedureId,
            ['released' => true],
            null,
            null,
            $user
        );

        // because $testDraftStatement2 is not a draftStatement of the user
        static::assertEquals(1, $list->getTotal());
        static::assertEquals($testDraftStatement->getId(), $list->getResult()[0]['id']);
    }

    public function testResetReleasedDraftStatements()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        $testDraftStatementId = $testDraftStatement->getId();
        $testProcedureId = $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId();
        /** @var User $user */
        $user = $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        // check setup:
        static::assertFalse($testDraftStatement->isReleased());
        static::assertEquals($user->getId(), $testDraftStatement->getUser()->getId());
        static::assertEquals($testProcedureId, $testDraftStatement->getProcedure()->getId());

        $list = $this->sut->getDraftStatementReleasedOwnList(
            $testProcedureId,
            ['released' => true],
            null,
            null,
            $user
        );

        static::assertEmpty($list->getResult());

        // prepare:  release statement:
        $this->sut->releaseDraftStatement([$testDraftStatementId]);
        $list = $this->sut->getDraftStatementReleasedOwnList(
            $testProcedureId,
            ['released' => true],
            null,
            null,
            $user
        );

        // check preparation:
        static::assertEquals(1, $list->getTotal());
        static::assertEquals($testDraftStatement->getId(), $list->getResult()[0]['id']);

        // execute method of interest:
        $resetDraftStatementsSuccessfully = $this->sut->resetReleasedDraftStatements([$testDraftStatementId]);
        static::assertTrue($resetDraftStatementsSuccessfully);

        $list = $this->sut->getDraftStatementReleasedOwnList(
            $testProcedureId,
            ['released' => true],
            null,
            null,
            $user
        );

        // check results because of no draftstatements are released
        static::assertEmpty($list->getResult());
    }

    public function testDetermineStatementCategoryOnSubmitDraftStatement()
    {
        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        static::assertEquals('paragraph', $testDraftStatement->getElement()->getCategory());

        $data = ['r_ident' => $testDraftStatement->getId()];
        $determinedCategoryId = $this->sut->determineStatementCategory($testDraftStatement->getProcedure()->getId(), $data);
        $determinedCategory = $this->elementsService->getElementObject($determinedCategoryId);

        // assert no changes:$determinedCategory
        static::assertEquals($testDraftStatement->getElement()->getId(), $determinedCategory->getId());
        static::assertEquals($testDraftStatement->getElement()->getTitle(), $determinedCategory->getTitle());
    }

    public function testDetermineStatementCategoryNotExistent()
    {
        self::markSkippedForCIIntervention();

        static::assertEquals('paragraph', $this->testDraftStatement->getElement()->getCategory());

        $data = ['r_ident' => $this->testDraftStatement->getId()];
        $determinedCategoryId = $this->sut->determineStatementCategory('notexistant', $data);
        static::assertEquals($this->testDraftStatement->getElement()->getId(), $determinedCategoryId);
    }

    public function testSetElementIdOnDetermineStatementCategory()
    {
        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        static::assertEquals('paragraph', $testDraftStatement->getElement()->getCategory());

        // overwrite current elementID
        $data = [
            'r_ident'      => $testDraftStatement->getId(),
            'r_element_id' => 'irgendeine ElementID',
        ];

        $determinedCategory = $this->sut->determineStatementCategory($testDraftStatement->getProcedure()->getId(), $data);

        // assert no changes:$determinedCategory
        static::assertEquals($data['r_element_id'], $determinedCategory);
    }

    /**
     * @param $providerData
     *
     * @throws \Doctrine\Common\DataFixtures\OutOfBoundsException
     * @throws Exception
     */
    public function testSetNoElementIdOnDetermineStatementCategory()
    {
        self::markSkippedForCIIntervention();

        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        /** @var Elements $testElement */
        $testElement = $this->fixtures->getReference('testElement1');
        $procedureId = $testDraftStatement->getProcedure()->getId();

        static::assertEquals('paragraph', $testDraftStatement->getElement()->getCategory());
        // element on testDraftStatement is the 'testElement1':
        static::assertEquals($testElement->getId(), $testDraftStatement->getElement()->getId());

        // get Element from category 'statement' of this procedure:
        $gesamtStnElement = $this->elementsService
            ->getStatementElement($testDraftStatement->getProcedure()->getId());
        static::assertEquals('Gesamtstellungnahme', $gesamtStnElement->getTitle());

        // empty string and null as elementId via providerData, should result in category 'statement'
        $determinedElementId = $this->sut->determineStatementCategory($procedureId, $providerData);

        $determinedElement = $this->elementsService->getElementObject($determinedElementId);

        static::assertEquals($gesamtStnElement->getCategory(), $determinedElement->getCategory());
        static::assertEquals($gesamtStnElement->getId(), $determinedElement->getId());
    }

    /**
     * @param $providerData
     * dataProvider getDetermineStatementCategoryData
     */
    public function testDetermineStatementCategory(/* array $providerData */)
    {
        self::markSkippedForCIIntervention();

        static::assertTrue(true);
        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        $procedureId = $testDraftStatement->getProcedure()->getId();

        $determinedCategory = $this->sut->determineStatementCategory($procedureId, $providerData);
        $element = $this->elementsService->getElementObject($determinedCategory);
        static::assertEquals($providerData['assertTitle'], $element->getTitle());
    }

    public function testNegativeReportDetermineStatementCategory()
    {
        self::markSkippedForCIIntervention();

        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        static::assertEquals('paragraph', $testDraftStatement->getElement()->getCategory());

        $negativeReportElement = $this->elementsService
            ->getNegativeReportElement($testDraftStatement->getProcedure()->getId());
        static::assertEquals('Fehlanzeige', $negativeReportElement->getTitle());

        $data = [
            'r_ident'            => $testDraftStatement->getId(),
            'r_isNegativeReport' => 1,
        ];

        $determinedCategory = $this->sut->determineStatementCategory($testDraftStatement->getProcedure()->getId(), $data);
        static::assertEquals($negativeReportElement->getId(), $determinedCategory);
    }

    /**
     * @param $providerData
     * dataProvider getCreateDraftStatementData
     *
     * @throws \Doctrine\Common\DataFixtures\OutOfBoundsException
     * @throws Exception
     */
    public function testDetermineStatementCategoryOnCreateCategory(/* $providerData */)
    {
        self::markSkippedForCIIntervention();

        $testDraftStatement = $this->sut->addDraftStatement($providerData);
        $testDraftStatement = $this->sut->getDraftStatementObject($testDraftStatement['id']);
        $procedureId = $testDraftStatement->getProcedure()->getId();

        // create new DraftStatement with category
        if (array_key_exists('elementId', $providerData)) {
            $element = $this->elementsService->getElementObject($providerData['elementId']);
            $d = $testDraftStatement->getElement();
            static::assertEquals($element, $testDraftStatement->getElement());
            static::assertEquals($element->getCategory(), $testDraftStatement->getElement()->getCategory());

            $determinedCategoryId = $this->sut->determineStatementCategory($procedureId, $providerData);
            $element = $this->elementsService->getElementObject($determinedCategoryId);
            static::assertEquals('paragraph', $element->getCategory());
        } else {
            // create new draftstatement without category
            static::assertNull($testDraftStatement->getElement());
            $determinedCategoryId = $this->sut->determineStatementCategory($procedureId, $providerData);
            $element = $this->elementsService->getElementObject($determinedCategoryId);
            static::assertEquals('statement', $element->getCategory());
        }
    }

    /**
     * DataProvider.
     *
     * @return array
     *
     * @throws \Doctrine\Common\DataFixtures\OutOfBoundsException
     */
    public function getNoElementIdDetermineStatementCategoryData()
    {
        $testDraftStatementId = $this->fixtures->getReference('testDraftStatement')->getId();

        return [
            [['r_ident' => $testDraftStatementId, 'r_element_id' => '']],
            [['r_ident' => $testDraftStatementId, 'r_element_id' => null]],
        ];
    }

    /**
     * DataProvider.
     *
     * @return array
     *
     * @throws \Doctrine\Common\DataFixtures\OutOfBoundsException
     */
    public function getCreateDraftStatementData()
    {
        return [
            [[
                'pId'                 => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
                'text'                => $this->text,
                'uId'                 => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
                'uName'               => $this->otherUserName,
                'dId'                 => $this->fixtures->getReference('testDepartment')->getId(),
                'dName'               => $this->fixtures->getReference('testDepartment')->getName(),
                'oId'                 => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
                'oName'               => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
                // attention: on create new DraftStatement the repository expects 'elementId' or 'element', but not 'r_element_id'
                'elementId'           => $this->fixtures->getReference('testElement1')->getId(),
                'statementAttributes' => ['noLocation' => 1],
            ]],
            [[
                'pId'          => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
                'text'         => $this->text,
                'uId'          => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
                'uName'        => $this->otherUserName,
                'dId'          => $this->fixtures->getReference('testDepartment')->getId(),
                'dName'        => $this->fixtures->getReference('testDepartment')->getName(),
                'oId'          => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
                'oName'        => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
                'r_element_id' => '',
            ]],
        ];
    }

    /**
     * DataProvider.
     *
     * @return array
     *
     * @throws \Doctrine\Common\DataFixtures\OutOfBoundsException
     */
    public function getDetermineStatementCategoryData()
    {
        /** @var DraftStatement $testDraftStatement */
        $testDraftStatement = $this->fixtures->getReference('testDraftStatement');
        /** @var Elements $testMapElement */
        $testMapElement = $this->fixtures->getReference('testMapElement');
        /** @var Elements $testElementB */
        $testElementB = $this->fixtures->getReference('testReasonElement');
        /** @var Elements $testElementV */
        $testElementV = $this->fixtures->getReference('testRegulationElement');

        return [
            [[
                'r_ident'             => $testDraftStatement->getId(),
                'r_location'          => 'point',
                'r_location_geometry' => 'randomLocation',
                'assertTitle'         => $testMapElement->getTitle(),
            ]],
            [[
                'r_ident'       => $testDraftStatement->getId(),
                'r_document_id' => $testElementB->getId(),
                'r_element_id'  => $testElementB->getId(),
                'assertTitle'   => $testElementB->getTitle(),
            ]],
            [[
                'r_ident'      => $testDraftStatement->getId(),
                'r_documentID' => $testElementB->getId(),
                'r_elementID'  => $testElementB->getId(),
                'assertTitle'  => $testElementB->getTitle(),
            ]],
            [[
                'r_ident'        => $testDraftStatement->getId(),
                'r_paragraph_id' => $testElementV->getId(),
                'r_element_id'   => $testElementV->getId(),
                'assertTitle'    => $testElementV->getTitle(),
            ]],
            [[
                'r_ident'       => $testDraftStatement->getId(),
                'r_paragraphID' => $testElementV->getId(),
                'r_elementID'   => $testElementV->getId(),
                'assertTitle'   => $testElementV->getTitle(),
            ]],
        ];
    }

    public function testIsDraftStatementSubmitted()
    {
        self::markSkippedForCIIntervention();

        /** @var DraftStatement $testDraftStatement */
        $testSubmittedDraftStatement = $this->fixtures->getReference('testDraftStatementOtherOrga');
        /** @var DraftStatement $testUnSubmittedDraftStatement */
        $testUnSubmittedDraftStatement = $this->fixtures->getReference('testDraftStatementOtherOrga2');

        static::assertTrue($testSubmittedDraftStatement->isSubmitted());
        static::assertFalse($testUnSubmittedDraftStatement->isSubmitted());

        $isSubmitted = $this->sut->getPublicDoctrine()->getRepository(DraftStatement::class)
            ->isDraftStatementSubmitted($testSubmittedDraftStatement->getId());
        static::assertTrue($isSubmitted);

        $isSubmitted = $this->sut->getPublicDoctrine()->getRepository(DraftStatement::class)
            ->isDraftStatementSubmitted($testUnSubmittedDraftStatement->getId());
        static::assertFalse($isSubmitted);
    }

    public function testSetHouseNumberOnUpdateDraftStatement()
    {
        $houseNumber = 'b67';
        static::assertNotEquals($this->testDraftStatement->getHouseNumber(), $houseNumber);

        $data = [
            'ident'       => $this->testDraftStatement->getId(),
            'houseNumber' => $houseNumber,
        ];

        $draftStatement = $this->sut->updateDraftStatement($data, false);
        static::assertInstanceOf(DraftStatement::class, $draftStatement);
        static::assertEquals($houseNumber, $draftStatement->getHouseNumber());
    }

    public function testHouseNumberOnSubmitDraftStatement()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $houseNumber = 'b67';

        $data = [
            'ident'       => $this->testDraftStatement->getId(),
            'houseNumber' => $houseNumber,
        ];

        $draftStatement = $this->sut->updateDraftStatement($data, false);

        /** @var Statement[] $submittedStatements */
        $submittedStatements = $this->sut->submitDraftStatement(
            [$draftStatement->getId()],
            $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY),
            null, false, false
        );

        static::assertIsArray($submittedStatements);
        static::assertCount(1, $submittedStatements);
        static::assertEquals($houseNumber, $submittedStatements[0]->getMeta()->getHouseNumber());
    }

    private function getBaseDraftStatementData(): array
    {
        return [
            'pId'       => $this->fixtures->getReference(LoadProcedureData::TESTPROCEDURE)->getId(),
            'text'      => $this->text,
            'uId'       => $this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY)->getId(),
            'uName'     => $this->otherUserName,
            'dId'       => $this->fixtures->getReference('testDepartment')->getId(),
            'dName'     => $this->fixtures->getReference('testDepartment')->getName(),
            'oId'       => $this->fixtures->getReference('testOrgaInvitableInstitution')->getId(),
            'oName'     => $this->fixtures->getReference('testOrgaInvitableInstitution')->getName(),
            'elementId' => $this->fixtures->getReference('testElement1')->getId(),
        ];
    }
}
