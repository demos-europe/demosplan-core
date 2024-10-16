<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use Carbon\Carbon;
use Closure;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\GdprConsent;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidDataException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementCopier;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use demosplan\DemosPlanCoreBundle\ValueObject\Statement\StatementStatistic;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

/**
 * Tests for the Statement-Service
 * Class StatementServiceTest.
 */
class StatementServiceTest extends FunctionalTestCase
{
    use RefreshElasticsearchIndexTrait;

    /** @var StatementService */
    protected $sut;

    protected ?DraftStatement $testDraftStatement;
    private ?StatementCopier $statementCopier;

    /**
     * @var Session
     */
    protected $mockSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(StatementService::class);
        $this->testDraftStatement = $this->getDraftStatementReference('testDraftStatement');
        $this->statementCopier = self::$container->get(StatementCopier::class);

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);

        $this->mockSession = $this->setUpMockSession();
        $this->setElasticsearchIndexManager(self::$container->get('fos_elastica.index_manager'));
    }

    protected function setUpMockSession(string $userReferenceName = LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY): Session
    {
        $session = parent::setUpMockSession($userReferenceName);
        $permissions['feature_statement_assignment']['enabled'] = false;
        $permissions['feature_statement_cluster']['enabled'] = false;
        $permissions['feature_statement_content_changes_save']['enabled'] = true;
        $session->set('permissions', $permissions);

        return $session;
    }

    /**
     * @return Session
     */
    protected function getMockSession()
    {
        return $this->mockSession;
    }

    /**
     * @throws Exception
     */
    public function testGetStatementStructure()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        $testStatement = $this->getStatementReference('testStatement');
        $statement = $this->sut->getStatementByIdent($testStatement->getId());
        $this->checkStatementStructure($statement);

        static::assertEquals($statement['internId'], $testStatement->getInternId());
    }

    public function testGetStatementVersion()
    {
        $statement = $this->sut->getVersionFields($this->getStatementReference('testStatement')->getId());
        static::assertIsArray($statement['version']);
        static::assertArrayHasKey('total', $statement);
        static::assertCount(1, $statement['version']);
        $versionFieldToTest = $statement['version'][0];
        static::assertArrayHasKey('ident', $versionFieldToTest);
        static::assertArrayHasKey('statementIdent', $versionFieldToTest);
        static::assertArrayHasKey('userIdent', $versionFieldToTest);
        static::assertArrayHasKey('userName', $versionFieldToTest);
        static::assertArrayHasKey('sessionIdent', $versionFieldToTest);
        static::assertArrayHasKey('name', $versionFieldToTest);
        static::assertArrayHasKey('type', $versionFieldToTest);
        static::assertArrayHasKey('value', $versionFieldToTest);
        static::assertArrayHasKey('created', $versionFieldToTest);
        static::assertTrue($this->isTimestamp($versionFieldToTest['created']));
    }

    /**
     * @throws Exception
     */
    public function testGetStatementsByProcedureIdStructure()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $result = $this->sut->getStatementsByProcedureId(
            $this->getProcedureReference('testProcedure')->getId(),
            [],
            null,
            ''
        );
        $this->checkStatementStructure($result->getResult()[0]);
    }

    /**
     * @throws Exception
     */
    public function testGetStatementsByProcedureIdNotOriginal()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $filters = ['original' => 'IS NOT NULL'];
        $sort = null;
        $search = null;
        $procedure = $this->getProcedureReference('testProcedure');
        $result = $this->sut->getStatementsByProcedureId(
            $procedure,
            $filters,
            $search,
            $sort
        );
        $this->checkStatementStructure($result->getResult()[0]);
    }

    /**
     * @throws Exception
     */
    public function testGetStatementsByProcedureIdOnlyPublic()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $filters = ['publicVerified' =>  Statement::PUBLICATION_APPROVED];
        $sort = null;
        $search = null;
        $procedure = $this->getProcedureReference('testProcedure');
        $result = $this->sut->getStatementsByProcedureId(
            $procedure->getId(),
            $filters,
            $search,
            $sort
        );
        $this->checkStatementStructure($result->getResult()[0]);
    }

    public function testGetStatementsByProcedureIdOriginal()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $filters = ['original' => 'IS NULL'];
        $sort = null;
        $search = null;
        $procedure = $this->getProcedureReference('testProcedure');
        $result = $this->sut->getStatementsByProcedureId(
            $procedure->getId(),
            $filters,
            $search,
            $sort
        );
        $this->checkStatementStructure($result->getResult()[0]);
    }

    public function testGetNewestInternId()
    {
        $procedureId = $this->getStatementReference('testStatement')->getProcedureId();
        $result = $this->sut->getNewestInternId($procedureId);

        static::assertSame('11111111', $result);
    }

    /**
     * @throws Exception
     */
    public function testVoteStk()
    {
        $stmnt = $this->getStatementReference('testStatement');
        static::assertNull($stmnt->getVoteStk());
        $stmnt->setVoteStk('acknowledge');
        static::assertSame('acknowledge', $stmnt->getVoteStk());
        $stmnt->setVoteStk('partial');
        static::assertSame('partial', $stmnt->getVoteStk());
        $stmnt->setVoteStk('full');
        static::assertSame('full', $stmnt->getVoteStk());
        try {
            $stmnt->setVoteStk('doge');
        } catch (Exception $e) {
        }
        static::assertSame('full', $stmnt->getVoteStk());
        $stmnt->setVoteStk('partial');
        static::assertSame('partial', $stmnt->getVoteStk());
    }

    /**
     * @throws Exception
     */
    public function testVotePla()
    {
        $stmnt = $this->getStatementReference('testStatement');
        static::assertNull($stmnt->getVotePla());
        $stmnt->setVotePla('acknowledge');
        static::assertSame('acknowledge', $stmnt->getVotePla());
        $stmnt->setVotePla('partial');
        static::assertSame('partial', $stmnt->getVotePla());
        $stmnt->setVotePla('full');
        static::assertSame('full', $stmnt->getVotePla());
        try {
            $stmnt->setVotePla('doge');
        } catch (Exception $e) {
        }
        static::assertSame('full', $stmnt->getVotePla());
        $stmnt->setVotePla('partial');
        static::assertSame('partial', $stmnt->getVotePla());
    }

    public function testCreateStatementInternId()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->getProcedureReference('testProcedure2')->getId();
        $amountBefore = $this->countEntries(Statement::class);

        $data = [
            'text'           => '<p>zuzuzuzzu</p>',
            'phase'          => 'configuration',
            'submittedDate'  => '07.07.2016',
            'pId'            => $procedureId,
            'elementId'      => '-',
            'documentId'     => '',
            'paragraphId'    => '',
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'civic'          => true,
            'externId'       => 'M5526',
            'submitType'     => 'system',
        ];

        // first new Statement
        $created = $this->sut->newStatement($data);

        static::assertNotFalse($created);
        static::assertInstanceOf(Statement::class, $created);

        // simulating creation of Copy:
        $this->sut->copyStatementWithinProcedure($created->getId(), false);

        static::assertNull($created->getInternId());
        // ist STN und STN-Kopie vorhanden?
        static::assertSame(
            $amountBefore + 2,
            $this->countEntries(Statement::class)
        );

        // second new Statement
        $data['internId'] = '88793';
        $data['text'] = 'tralalalalla';
        $created = $this->sut->newStatement($data);
        // simulating creation of Copy:
        $this->sut->copyStatementWithinProcedure($created->getId(), false);

        static::assertSame($data['internId'], $created->getInternId());
        static::assertSame(
            $amountBefore + 4,
            $this->countEntries(Statement::class)
        );

        // third new Statement
        $data['text'] = 'Text of invalid STN because of non unique intern ID';
        $created = $this->sut->newStatement($data);
        // simulating creation of Copy:
        static::assertFalse($created);

        static::assertSame(
            $amountBefore + 4,
            $this->countEntries(Statement::class)
        );
    }

    public function testCreateStatementMiscData()
    {
        self::markSkippedForCIIntervention();

        $procedureId = $this->getProcedureReference('testProcedure2')->getId();
        $amountBefore = $this->countEntries(Statement::class);

        $data = [
            'civic'          => true,
            'documentId'     => '',
            'elementId'      => '-',
            'externId'       => 'M5527',
            'miscData'       => [
                'one'    => 'first',
                'two'    => true,
                'third'  => 3,
                'fourth' => [],
                'fifths' => ['one' => 'first'],
                'sixths' => '',
            ],
            'paragraphId'    => '',
            'phase'          => 'configuration',
            'pId'            => $procedureId,
            'publicCheck'    => 'no',
            'publicVerified' => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'submitType'     => 'system',
            'text'           => '<p>Test Data Array in misc Data</p>',
        ];

        // first new Statement
        $createdStatement = $this->sut->newStatement($data);

        static::assertNotFalse($createdStatement);
        static::assertInstanceOf(Statement::class, $createdStatement);
        // ist STN und STN-Kopie vorhanden?
        static::assertSame(
            $amountBefore + 1,
            $this->countEntries(Statement::class)
        );
        static::assertEquals($data['miscData'], $createdStatement->getMeta()->getMiscData());
        foreach ($data['miscData'] as $key => $value) {
            static::assertSame($value, $createdStatement->getMeta()->getMiscDataValue($key));
        }
        static::assertNull($createdStatement->getMeta()->getMiscDataValue('new'));
        $createdStatement->getMeta()->setMiscDataValue('new', 'newValue');
        static::assertSame('newValue', $createdStatement->getMeta()->getMiscDataValue('new'));
        static::assertTrue(Carbon::now()->isSameMinute($createdStatement->getSubmitObject()));
    }

    public function testGetTranslatedSubmitType()
    {
        $testStatement = $this->getStatementReference('testStatement2');
        static::assertNotEquals($testStatement->getSubmitType(), $testStatement->getSubmitTypeTranslated());
        static::assertEquals('Beteiligungsplattform', $testStatement->getSubmitTypeTranslated());

        // this does not yet work on newly created statements
    }

    /**
     * @throws \demosplan\DemosPlanCoreBundle\Exception\CopyException
     */
    public function testCopyStatementWithFragments()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $testStatement = $this->getStatementReference('testStatement');

        $createdCopy = $this->statementCopier->copyStatementObjectWithinProcedure($testStatement);
        static::assertInstanceOf(Statement::class, $createdCopy);

        static::assertEquals($testStatement->getFragments()->count(), $createdCopy->getFragments()->count());
    }

    // -----------------------------------Tag/Topic-----------------------------------------------------

    public function testDuplicateAddingTagToStatement()
    {
        $tagService = self::$container->get(TagService::class);
        $tag = $this->getTagReference('testFixtureTag_2');
        $statement = $this->getStatementReference('testStatement2');

        $resultTag = $tagService->getTag($tag->getId());
        $resultStatement = $this->sut->getStatement($statement->getId());

        $statementsOfTagBefore = $resultTag->getStatements();
        $tagsOfStatementBefore = $resultStatement->getTags();

        // Hinzuzufügenes Statement soll bereits vorhanden sein!
        static::assertTrue($statementsOfTagBefore->contains($statement));

        $this->sut->addTagToStatement($tag, $statement);

        $resultTag = $tagService->getTag($tag->getId());
        $resultStatement = $this->sut->getStatement($statement->getId());

        $statementsOfTagAfter = $resultTag->getStatements();
        $tagsOfStatementAfter = $resultStatement->getTags();

        static::assertEquals($statementsOfTagBefore, $statementsOfTagAfter);
        static::assertEquals($tagsOfStatementBefore, $tagsOfStatementAfter);
    }

    public function testDeleteTag()
    {
        $tagService = self::$container->get(TagService::class);
        $tag = $this->getTagReference('testFixtureTag_2');
        $statement = $this->getStatementReference('testStatement2');
        $topic = $this->getTagTopicReference('testFixtureTopic_1');
        $tagId = $tag->getId();
        $this->sut->addTagToStatement($tag, $statement);

        static::assertContains($tag, $topic->getTags());

        $tagService->deleteTag($tag);
        static::assertNull($tagService->getTag($tagId));
        static::assertNotContains($tag, $topic->getTags());
    }

    public function testAddTag()
    {
        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $testStatement2 = $this->getStatementReference('testStatement2');

        $statementOfTag1 = $testTag1->getStatements();
        $tagsOfStatement2 = $testStatement2->getTags();

        static::assertNotNull($testTag1->getTopic());

        $result = $this->sut->addTagToStatement($testTag1, $testStatement2);

        $tags = $testStatement2->getTags();
        static::assertNotNull($result);
        static::assertInstanceOf(Statement::class, $result);
        static::assertEquals(2, $tags->count());
        static::assertContains($testTag1, $tags);
    }

    public function testGetStatementById()
    {
        $testStatement2 = $this->getStatementReference('testStatement2');
        $result = $this->sut->getStatement($testStatement2->getId());

        static::assertNotNull($result);
        static::assertIsNotArray($result);
        static::assertEquals($testStatement2, $result);
    }

    /**
     * Helping method.
     */
    protected function checkStatementStructure($statement)
    {
        static::assertIsArray($statement);
        static::assertArrayHasKey('categories', $statement);
        static::assertIsArray($statement['categories']);
        static::assertArrayHasKey('created', $statement);
        static::assertTrue($this->isTimestamp($statement['created']));
        static::assertArrayHasKey('deleted', $statement);
        static::assertIsBool($statement['deleted']);
        if (isset($statement['documentId']) && null !== $statement['documentId']) {
            static::assertIsArray($statement['document']);
        } else {
            static::assertFalse(isset($statement['document']));
        }
        if (null !== $statement['elementId']) {
            static::assertIsArray($statement['element']);
            $this->checkElementStructure($statement['element']);
        } else {
            static::assertNull($statement['element']);
        }
        static::assertArrayHasKey('externId', $statement);
        static::assertArrayHasKey('internId', $statement);
        static::assertArrayHasKey('feedback', $statement);
        static::assertArrayHasKey('file', $statement);
        static::assertArrayHasKey('files', $statement);
        static::assertArrayHasKey('ident', $statement);
        static::assertArrayHasKey('modified', $statement);
        static::assertTrue($this->isTimestamp($statement['modified']));
        static::assertArrayHasKey('mapFile', $statement);
        static::assertArrayHasKey('memo', $statement);
        static::assertArrayHasKey('negativeStatement', $statement);
        static::assertIsBool($statement['negativeStatement']);
        static::assertArrayHasKey('oId', $statement);
        if (null !== $statement['originalId']) {
            static::assertInstanceOf(Statement::class, $statement['original']);
        } else {
            static::assertNull($statement['original']);
        }
        static::assertArrayHasKey('paragraph', $statement);
        static::assertArrayHasKey('paragraphId', $statement);
        if (null !== $statement['paragraphId']) {
            static::assertIsArray($statement['paragraph']);
            $this->checkParagraphStructure($statement['paragraph']);
        } else {
            static::assertNull($statement['paragraph']);
        }
        if (null !== $statement['parent']) {
            static::assertIsArray($statement['parent']);
            $this->checkStatementStructure($statement['parent']);
        } else {
            static::assertNull($statement['parent']);
        }
        static::assertArrayHasKey('phase', $statement);
        static::assertArrayHasKey('represents', $statement);
        static::assertArrayHasKey('representationCheck', $statement);
        static::assertArrayHasKey('planningDocument', $statement);
        static::assertArrayHasKey('polygon', $statement);
        static::assertArrayHasKey('priority', $statement);
        static::assertArrayHasKey('procedure', $statement);
        static::assertIsArray($statement['procedure']);
        static::assertIsArray($statement['procedure']['settings']);
        static::assertIsArray($statement['procedure']['organisation']);
        static::assertIsArray($statement['procedure']['planningOffices']);
        static::assertArrayHasKey('publicAllowed', $statement);
        static::assertIsBool(is_bool($statement['publicAllowed']));
        static::assertArrayHasKey('publicStatement', $statement);
        static::assertArrayHasKey('publicUseName', $statement);
        static::assertIsBool($statement['publicUseName']);
        static::assertArrayHasKey('publicVerified', $statement);
        static::assertArrayHasKey('reasonParagraph', $statement);
        static::assertArrayHasKey('recommendation', $statement);
        static::assertArrayHasKey('send', $statement);
        static::assertTrue($this->isTimestamp($statement['send']));
        static::assertArrayHasKey('sentAssessmentDate', $statement);
        static::assertTrue($this->isTimestamp($statement['sentAssessmentDate']));
        static::assertArrayHasKey('submit', $statement);
        static::assertTrue($this->isTimestamp($statement['submit']));
        static::assertArrayHasKey('status', $statement);
        static::assertArrayHasKey('tags', $statement);
        static::assertArrayHasKey('text', $statement);
        static::assertArrayHasKey('title', $statement);
        static::assertArrayHasKey('toSendPerMail', $statement);
        static::assertIsBool($statement['toSendPerMail']);
        static::assertArrayHasKey('uId', $statement);
        static::assertArrayHasKey('votes', $statement);
        static::assertIsArray($statement['votes']);
        static::assertArrayHasKey('priorityAreas', $statement);
    }

    /**
     * Helping method.
     */
    public function checkParagraphStructure($paragraph)
    {
        static::assertArrayHasKey('id', $paragraph);
        static::assertArrayHasKey('ident', $paragraph);
        static::assertArrayHasKey('elementId', $paragraph);
        static::assertArrayHasKey('category', $paragraph);
        static::assertArrayHasKey('title', $paragraph);
        static::assertArrayHasKey('text', $paragraph);
        static::assertArrayHasKey('order', $paragraph);
        static::assertArrayHasKey('deleted', $paragraph);
        static::assertArrayHasKey('visible', $paragraph);
        // static::assertArrayHasKey('versionDate',$paragraph);
        static::assertArrayHasKey('createDate', $paragraph); // kann hier anders sein als in der Originalentität, weil nicht genutzt und CamelCase ein Problem der gesamten Anwendung
        static::assertArrayHasKey('modifyDate', $paragraph); // kann hier anders sein als in der Originalentität, weil nicht genutzt und CamelCase ein Problem der gesamten Anwendung
        static::assertArrayHasKey('pId', $paragraph);
    }

    protected function checkElementStructure($element)
    {
        static::assertArrayHasKey('ident', $element);
        static::assertArrayHasKey('icon', $element);
        static::assertArrayHasKey('category', $element);
        static::assertArrayHasKey('children', $element);
        static::assertArrayHasKey('deleted', $element);
        static::assertArrayHasKey('documents', $element);
        static::assertArrayHasKey('createdate', $element); // kann hier anders sein als in der Originalentität, weil nicht genutzt und CamelCase ein Problem der gesamten Anwendung
        static::assertArrayHasKey('title', $element);
        static::assertArrayHasKey('text', $element);
        static::assertArrayHasKey('order', $element);
        static::assertArrayHasKey('organisation', $element);
        static::assertArrayHasKey('createdate', $element);
        static::assertArrayHasKey('pId', $element);
    }

    public function testUpdateStatementTags()
    {
        self::markSkippedForCIIntervention();
        // Will fail, because of diffrent object type (doctrine-Proxy)

        $relatedStatement = $this->sut->getStatement($this->getStatementReference('testFixtureStatement')->getId());
        $topic = $this->getTagTopicReference('testFixtureTopic_1');
        $tags = $topic->getTags();

        static::assertEmpty($relatedStatement->getTags());

        $data = ['ident' => $relatedStatement->getId(), 'tags' => [$tags[0]]];
        $this->sut->updateStatement($data, true);
        $updatedStatement = $this->sut->getStatement($this->getStatementReference('testFixtureStatement')->getId());
        static::assertCount(1, $updatedStatement->getTags());

        $data = ['ident' => $relatedStatement->getId(), 'tags' => [$tags[0], $tags[1]]];
        $this->sut->updateStatement($data, true);
        $updatedStatement = $this->sut->getStatement($this->getStatementReference('testFixtureStatement')->getId());
        static::assertCount(2, $updatedStatement->getTags());
    }

    public function testIsStatementLockedByAssignmentReturnsFalseWithDisabledFeature()
    {
        $statement = $this->getStatementReference('testStatement2');
        static::assertFalse($this->sut->isStatementAssignedToCurrentUser($statement));
    }

    public function testIsStatementLockedByAssignmentReturnsTrueWhenAssigned()
    {
        self::markSkippedForCIIntervention();

        $this->enableStatementAssignmentFeature();

        $statement = $this->getStatementReference('testStatement2');
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $statement->setAssignee($user);

        static::assertTrue($this->sut->isStatementObjectLockedByAssignment($statement));
    }

    public function testIsStatementLockedByAssignmentReturnsFalseWithOverride()
    {
        self::markSkippedForCIIntervention();

        $this->enableStatementAssignmentFeature();

        $statement = $this->getStatementReference('testStatement2');
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);

        $statement->setAssignee($user);

        static::assertFalse($this->sut->isStatementAssignedToCurrentUser($statement));
    }

    public function testAssigningOfStatement()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        $this->loginTestUser();

        $this->enableStatementAssignmentFeature();
        $this->enableStatementClusterFeature();

        $testStatement2 = $this->getStatementReference('testStatement2');
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        static::assertNull($testStatement2->getAssignee());

        // normal update should fail, because no assignee:
        $testStatement2->setTitle('updatedTitle2435');
        $result = $this->sut->updateStatementFromObject($testStatement2);
        static::assertFalse($result);

        // assign user with ignoring lock (normally via statementHandler->setAssigneeOfStatement())
        $testStatement2->setAssignee($user);
        $result = $this->sut->updateStatementFromObject($testStatement2, true);
        static::assertInstanceOf('\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement', $result);

        // with assigned user == current user, it should work
        $testStatement2->setTitle('updatedTitle666');
        $result = $this->sut->updateStatementFromObject($testStatement2);
        static::assertInstanceOf('\demosplan\DemosPlanCoreBundle\Entity\Statement\Statement', $result);

        $updatedStatement = $this->sut->getStatement($testStatement2->getId());
        static::assertSame('updatedTitle666', $updatedStatement->getTitle());
    }

    public function testCreateStatementWithOneFile()
    {
        self::markSkippedForCIIntervention();

        $procedureId = $this->getProcedureReference('testProcedure2')->getId();
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFilename().':'.$testFile1->getHash().':1234:'.$testFile1->getMimetype();

        $data = [
            'documentId'        => '',
            'elementId'         => '-',
            'externId'          => 'M5526',
            'file'              => [$fileString1],
            'isManualStatement' => true,
            'paragraphId'       => '',
            'phase'             => 'configuration',
            'pId'               => $procedureId,
            'publicVerified'    => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'submittedDate'     => '07.07.2016',
            'text'              => '<p>zuzuzuzzu</p>',
        ];

        // first new Statement
        $newStatement = $this->sut->newStatement($data);
        static::assertIsArray($newStatement->getFiles());
        static::assertCount(1, $newStatement->getFiles());
        static::assertEquals($fileString1, $newStatement->getFiles()[0]);
    }

    public function testCreateStatementWithOneFileLegacyString()
    {
        self::markSkippedForCIIntervention();

        $procedureId = $this->getProcedureReference('testProcedure2')->getId();
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFilename().':'.$testFile1->getHash().':1234:'.$testFile1->getMimetype();

        $data = [
            'documentId'        => '',
            'elementId'         => '-',
            'externId'          => 'M5526',
            'file'              => $fileString1, // as string, not as array
            'isManualStatement' => true,
            'paragraphId'       => '',
            'phase'             => 'configuration',
            'pId'               => $procedureId,
            'publicCheck'       => 'no',
            'publicVerified'    => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'submittedDate'     => '07.07.2016',
            'text'              => '<p>zuzuzuzzu</p>',
        ];

        // first new Statement
        $newStatement = $this->sut->newStatement($data);

        static::assertIsArray($newStatement->getFiles());
        static::assertCount(1, $newStatement->getFiles());
        static::assertEquals($fileString1, $newStatement->getFiles()[0]);
    }

    /**
     * @throws \demosplan\DemosPlanCoreBundle\Exception\CopyException
     */
    public function testCreateStatementWithTwoFiles()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->getProcedureReference('testProcedure2')->getId();
        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFilename().':'.$testFile1->getHash().':1234:'.$testFile1->getMimetype();
        $testFile2 = $this->getFileReference('testFile2');
        $fileString2 = $testFile2->getFilename().':'.$testFile2->getHash().':1234:'.$testFile2->getMimetype();

        $data = [
            'documentId'        => '',
            'elementId'         => '-',
            'externId'          => 'M5526',
            'file'              => [$fileString1, $fileString2],
            'isManualStatement' => true,
            'paragraphId'       => '',
            'phase'             => 'configuration',
            'pId'               => $procedureId,
            'publicVerified'    => Statement::PUBLICATION_NO_CHECK_SINCE_NOT_ALLOWED,
            'submittedDate'     => '07.07.2016',
            'text'              => '<p>zuzuzuzzu</p>',
        ];

        // first new Statement as original Statement
        $newStatement = $this->sut->newStatement($data);
        // fake creation of copy to be used in assessment table until AssessmentTableServiceStorage
        // is testable
        $statementCopy = $this->sut->copyStatementWithinProcedure($newStatement->getId(), false);

        static::assertIsArray($newStatement->getFiles());
        static::assertCount(2, $newStatement->getFiles());
        static::assertEquals($fileString1, $newStatement->getFiles()[0]);
        static::assertEquals($fileString2, $newStatement->getFiles()[1]);

        static::assertInstanceOf(Statement::class, $statementCopy);
        static::assertCount(2, $statementCopy->getFiles());
        static::assertEquals($fileString1, $statementCopy->getFiles()[0]);
        static::assertEquals($fileString2, $statementCopy->getFiles()[1]);
    }

    public function testAddFileToStatementWithOneFile()
    {
        self::markSkippedForCIIntervention();

        $testFile1 = $this->getFileReference('testFile');
        $fileString1 = $testFile1->getFilename().':'.$testFile1->getHash().':1234:'.$testFile1->getMimetype();

        // first new Statement
        $newStatement = $this->sut->addFilesToStatement([$fileString1], $this->getStatementReference('testStatement'));
        static::assertIsArray($newStatement->getFiles());
        static::assertCount(1, $newStatement->getFiles());
        static::assertEquals($fileString1, $newStatement->getFiles()[0]);
    }

    public function testGetAllStatements()
    {
        $allStatements = $this->sut->getAllStatements();
        $currentNumberOfStatements = $this->countEntries(Statement::class);
        static::assertCount($currentNumberOfStatements, $allStatements);
    }

    protected function enableStatementAssignmentFeature()
    {
        // modify permissions for test
        $permissions = $this->getMockSession()->get('permissions');
        $permissions['feature_statement_assignment']['enabled'] = true;
        $this->getMockSession()->set('permissions', $permissions);
    }

    protected function enableStatementClusterFeature()
    {
        // modify permissions for test
        $permissions = $this->getMockSession()->get('permissions');
        $permissions['feature_statement_cluster']['enabled'] = true;
        $this->getMockSession()->set('permissions', $permissions);
    }

    /**
     * Depending on the permission feature_statement_assignment.
     * If it is turned off the test fails, because it can update.
     *
     * @throws Exception
     */
    public function testRefuseAddStatementToNotClaimedClusterViaUpdateStatement()
    {
        self::markSkippedForCIIntervention();

        $this->enableStatementAssignmentFeature();
        $unassignedHeadStatement3 = $this->getStatementReference('clusterStatement3');
        static::assertNull($unassignedHeadStatement3->getAssignee());

        $testStatement2 = $this->getStatementReference('testStatement2');
        static::assertFalse($testStatement2->isInCluster());
        $testStatementArray = $this->sut->getStatementByIdent($testStatement2->getId());
        $testStatementArray['headStatementId'] = $unassignedHeadStatement3->getId();

        $successfulUpdatedStatement = $this->sut->updateStatement($testStatementArray);

        static::assertFalse($successfulUpdatedStatement);

        $currentTestStatement2 = $this->sut->getStatement($testStatement2->getId());
        static::assertFalse($currentTestStatement2->isInCluster());
    }

    public function testCoalescingOperator()
    {
        $testArray = [
            'null'        => null,
            '0'           => 0,
            '1'           => 1,
            'emptyString' => '',
            'false'       => false,
            'true'        => true,
        ];

        // behavior of isset
        static::assertFalse(isset($testArray['null']));
        static::assertTrue(isset($testArray['0']));
        static::assertTrue(isset($testArray['1']));
        static::assertTrue(isset($testArray['emptyString']));
        static::assertTrue(isset($testArray['false']));
        static::assertTrue(isset($testArray['true']));
        static::assertFalse(isset($testArray['undefinedIndex']));

        // behavior of coalescingOperator
        static::assertSame('default1', $testArray['null'] ?? 'default1');
        static::assertSame(0, $testArray['0'] ?? 'default2');
        static::assertSame(1, $testArray['1'] ?? 'default3');
        static::assertSame('', $testArray['emptyString'] ?? 'default4');
        static::assertFalse($testArray['false'] ?? 'default5');
        static::assertTrue($testArray['true'] ?? 'default6');
        static::assertSame('default7', $testArray['undefinedIndex'] ?? 'default7');

        // compare directly:
        static::assertSame($testArray['null'] ?? false, $testArray['null'] ?? false);
        static::assertSame($testArray['0'] ?? false, $testArray['0'] ?? false);
        static::assertSame($testArray['1'] ?? false, $testArray['1'] ?? false);
        static::assertSame($testArray['emptyString'] ?? false, $testArray['emptyString'] ?? false);
        static::assertSame($testArray['false'] ?? false, $testArray['false'] ?? false);
        static::assertSame($testArray['true'] ?? false, $testArray['true'] ?? false);
        static::assertSame($testArray['undefinedIndex'] ?? false, $testArray['undefinedIndex'] ?? false);
    }

    public function testGetEmptyStatementName()
    {
        $statement = $this->getStatementReference('testStatement');
        $statements = $this->getEntries(Statement::class, ['name' => null, 'id' => $statement->getId()]);

        // one statement was found, means the statement has null as value in name
        static::assertCount(1, $statements);
        // expectedgetName() will return '' in case of name == null
        static::assertSame('', $statement->getName());
    }

    public function testSetStatementName()
    {
        $statement = $this->getStatementReference('testStatement');
        $name = 'name of a statement';
        $statement->setName($name);
        $this->sut->updateStatementFromObject($statement);

        $currentStatement = $this->find(Statement::class, $statement->getId());
        static::assertSame($name, $currentStatement->getName());
    }

    // test to cover storing name of department instead of name of user in case of Bearbeitung abschliessend
    // indicator: copyConsiderationAdviceToConsideration

    public function testGetUnusedNextValidExternalIdForProcedure()
    {
        $testProcedure = $this->getProcedureReference('testProcedure');
        $unusedExternId = $this->sut->getNextValidExternalIdForProcedure($testProcedure->getId());

        $numberOfStatements = $this->countEntries(Statement::class, ['externId' => $unusedExternId]);
        static::assertSame(0, $numberOfStatements);
        $numberOfDraftStatements = $this->countEntries(DraftStatement::class, ['externId' => $unusedExternId]);
        static::assertSame(0, $numberOfDraftStatements);
    }

    public function testGetNextValidExternalIdForProcedure()
    {
        $testProcedure = $this->getProcedureReference('testProcedure');
        /** @var Statement[] $statements */
        $statements = $this->getEntries(Statement::class, ['procedure' => $testProcedure->getId()]);
        /** @var DraftStatement[] $draftStatements */
        $draftStatements = $this->getEntries(DraftStatement::class, ['procedure' => $testProcedure->getId()]);
        $collectionOfIds = collect([]);

        foreach ($statements as $statement) {
            $currentExternId = str_replace(['M', 'G', 'C'], '', $statement->getExternId());
            $collectionOfIds->push(intval($currentExternId));
        }

        foreach ($draftStatements as $draftStatement) {
            $collectionOfIds->push($draftStatement->getNumber());
        }

        $expectedNextExternId = $collectionOfIds->sort()->last() + 1;
        $nextExternId = $this->sut->getNextValidExternalIdForProcedure($testProcedure->getId());

        static::assertEquals($expectedNextExternId, $nextExternId);
    }

    public function testSetRepliedStatusOfStatementObject()
    {
        $testStatement = $this->getStatementReference('testStatement');
        static::assertFalse($testStatement->isReplied());

        $testStatement->setReplied(true);
        static::assertTrue($testStatement->isReplied());

        $this->sut->updateStatementFromObject($testStatement, true);
        $updatedStatement = $this->sut->getStatement($testStatement->getId());

        static::assertTrue($updatedStatement->isReplied());
    }

    public function testSetRepliedStatusOfStatementArray()
    {
        $testStatement = $this->getStatementReference('testStatement');
        static::assertFalse($testStatement->isReplied());

        $statementData = [
            'ident'   => $testStatement->getId(),
            'replied' => true,
        ];

        $this->sut->updateStatement($statementData, true);
        $updatedStatement = $this->sut->getStatement($testStatement->getId());
        static::assertTrue($updatedStatement->isReplied());
    }

    public function testIsManualStatement()
    {
        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $isManual = $this->sut->isManualStatement($testStatement->getId());
        static::assertTrue(is_bool($isManual));
        static::assertEquals($isManual, $testStatement->isManual());

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testManualStatement');
        $isManual = $this->sut->isManualStatement($testStatement->getId());
        static::assertTrue(is_bool($isManual));
        static::assertEquals($isManual, $testStatement->isManual());
    }

    /**
     * Check handling of bidirectional association.
     * On set GDPR-Consent to Statement ($statement->setGdprConsent())
     * also the opposite of the bidirectional association, should know about the associated statement.
     *
     * @throws InvalidDataException
     */
    public function testSetStatementOfGdprConsentOnSetGdprConsent()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('originalStatement21WithInternId');

        static::assertTrue($testStatement->isOriginal());
        static::assertNull($testStatement->getGdprConsent());

        $testStatement->setGdprConsent(new GdprConsent());
        $updatedStatement = $this->sut->updateStatementFromObject($testStatement, true, true, true);
        static::assertInstanceOf(Statement::class, $updatedStatement);
        static::assertInstanceOf(GdprConsent::class, $updatedStatement->getGdprConsent());

        $gdprConsent = $updatedStatement->getGdprConsent();
        static::assertInstanceOf(GdprConsent::class, $gdprConsent);
        static::assertInstanceOf(Statement::class, $gdprConsent->getStatement());
    }

    /**
     * Check handling of bidirectional association.
     * On set Statement to GDPR->Constent ($gdprConsent->setStatement())
     * also the opposite of the bidirectional association, should know about the associated statement.
     *
     * @throws InvalidDataException
     */
    public function testSetGdprConsentOfStatementOnSetStatement()
    {
        self::markSkippedForCIIntervention();
        // Leads to excessive memory usage

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('originalStatement21WithInternId');

        static::assertTrue($testStatement->isOriginal());
        static::assertNull($testStatement->getGdprConsent());

        $gdprConsent = new GdprConsent();
        $gdprConsent->setStatement($testStatement);
        $updatedStatement = $this->sut->updateStatementFromObject($testStatement, true, true, true);
        static::assertInstanceOf(Statement::class, $updatedStatement);
        static::assertInstanceOf(GdprConsent::class, $updatedStatement->getGdprConsent());

        $gdprConsent = $updatedStatement->getGdprConsent();
        static::assertInstanceOf(GdprConsent::class, $gdprConsent);
        static::assertInstanceOf(Statement::class, $gdprConsent->getStatement());
    }

    public function testCreateEntityContentChangeOnUpdateStatementForText()
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $amountOfEntityChangesBefore = $this->countEntries(EntityContentChange::class);

        $testStatement->setText('updated Text');
        $updatedStatement = $this->sut->updateStatementFromObject($testStatement, true);
        static::assertInstanceOf(Statement::class, $updatedStatement);
        static::assertEquals($updatedStatement->getText(), 'updated Text');

        $amountOfEntityChangesAfter = $this->countEntries(EntityContentChange::class);
        static::assertEquals($amountOfEntityChangesBefore + 1, $amountOfEntityChangesAfter);
    }

    public function testCreateEntityContentChangeOnUpdateStatementArrayForText()
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $amountOfEntityChangesBefore = $this->countEntries(EntityContentChange::class);

        $testStatementArray = [
            'ident' => $testStatement->getId(),
            'text'  => 'updated Text',
        ];

        $updatedStatement = $this->sut->updateStatement($testStatementArray, true);
        static::assertEquals($updatedStatement->getText(), $testStatementArray['text']);

        $amountOfEntityChangesAfter = $this->countEntries(EntityContentChange::class);
        static::assertEquals($amountOfEntityChangesBefore + 1, $amountOfEntityChangesAfter);
    }

    public function testCreateEntityContentChangeOnUpdateStatementForCounty()
    {
        self::markSkippedForCIIntervention();

        /** @var Statement $testStatement */
        $testStatement = $this->fixtures->getReference('testStatement1');
        $amountOfEntityChangesBefore = $this->countEntries(EntityContentChange::class);

        static::assertNotEmpty($testStatement->getCounties());
        $testStatement->setCounties([]);
        $updatedStatement = $this->sut->updateStatementFromObject($testStatement, true);
        static::assertInstanceOf(Statement::class, $updatedStatement);

        $amountOfEntityChangesAfter = $this->countEntries(EntityContentChange::class);
        static::assertEquals($amountOfEntityChangesBefore + 1, $amountOfEntityChangesAfter);
    }

    public function testGetOriginalStatementStatisticData()
    {
        /** @var Statement[] $allStatements */
        $allStatements = $this->getEntries(Statement::class);

        /** @var Statement[] $allStatements */
        $allOriginalStatements = $this->getEntries(Statement::class, ['deleted' => false, 'original' => null]);

        /** @var Procedure[] $procedures */
        $procedures = $this->getEntries(Procedure::class, ['deleted' => false]);

        $statistic = [
            'guestStatements'                    => [],
            'citizenStatements'                  => [],
            'invitableInstitutionsStatements'    => [],
        ];

        $precision = 2;

        foreach ($allStatements as $statement) {
            if (!$statement->isDeleted() && $statement->isOriginal()) {
                if (null === $statement->getSubmitterId()) {
                    $statistic['guestStatements'][] = $statement;
                    $statistic[$statement->getProcedureId()]['guestStatements'][] = $statement;
                } elseif ($statement->isCreatedByInvitableInstitution()) {
                    $statistic['invitableInstitutionsStatements'][] = $statement;
                    $statistic[$statement->getProcedureId()]['invitableInstitutionsStatements'][] = $statement;
                } elseif ($statement->isCreatedByCitizen()) {
                    $statistic['citizenStatements'][] = $statement;
                    $statistic[$statement->getProcedureId()]['citizenStatements'][] = $statement;
                }
            }
        }

        /** @var Statement[] $originalStatements */
        $originalStatements = $this->getEntries(Statement::class, ['deleted' => false, 'original' => null]);

        // convert to array to be compatible with changed constructor of StatementStatistic
        $originalStatementArrays = [];
        foreach ($originalStatements as $originalStatement) {
            $originalStatementArrays[$originalStatement->getId()]['publicStatement'] = $originalStatement->getPublicStatement();
            $originalStatementArrays[$originalStatement->getId()]['submitUId'] = $originalStatement->getSubmitterId();
            $originalStatementArrays[$originalStatement->getId()]['userId'] = $originalStatement->getUserId();
            $originalStatementArrays[$originalStatement->getId()]['isManual'] = $originalStatement->getUserId();
            $originalStatementArrays[$originalStatement->getId()]['procedureId'] = $originalStatement->getProcedureId();
        }

        $statementStatistic = new StatementStatistic($originalStatementArrays, count($procedures));

        static::assertCount($statementStatistic->getTotalAmountOfProcedures(), $procedures);
        static::assertCount($statementStatistic->getTotalAmountOfStatements(), $allOriginalStatements);

        static::assertCount($statementStatistic->getTotalAmountOfGuestStatements(), $statistic['guestStatements']);
        static::assertCount($statementStatistic->getTotalAmountOfCitizenStatements(), $statistic['citizenStatements']);
        static::assertCount($statementStatistic->getTotalAmountOfInstitutionStatements(), $statistic['invitableInstitutionsStatements']);

        $sum = (count($statistic['guestStatements']) + count($statistic['citizenStatements']) + count($statistic['invitableInstitutionsStatements']));
        static::assertEquals(
            $statementStatistic->getAverageAmountOfStatementsPerProcedure($precision),
            round($sum / count($procedures), $precision));

        static::assertEquals(
            $statementStatistic->getAverageAmountOfGuestStatementsPerProcedure($precision),
            round(count($statistic['guestStatements']) / count($procedures), $precision));

        static::assertEquals(
            $statementStatistic->getAverageAmountOfCitizenStatementsPerProcedure($precision),
            round(count($statistic['citizenStatements']) / count($procedures), $precision));

        static::assertEquals(
            $statementStatistic->getAverageAmountOfInstitutionStatementsPerProcedure($precision),
            round(count($statistic['invitableInstitutionsStatements']) / count($procedures), $precision));

        foreach ($procedures as $procedure) {
            if (array_key_exists($procedure->getId(), $statistic)) {
                static::assertCount(
                    $statementStatistic->getAmountOfCitizenStatementsOfProcedure($procedure->getId()),
                    $statistic[$procedure->getId()]['citizenStatements'] ?? []
                );
                static::assertCount(
                    $statementStatistic->getAmountOfGuestStatementsOfProcedure($procedure->getId()),
                    $statistic[$procedure->getId()]['guestStatements'] ?? []
                );
                static::assertCount(
                    $statementStatistic->getAmountOfInstitutionStatementsOfProcedure($procedure->getId()),
                    $statistic[$procedure->getId()]['invitableInstitutionsStatements'] ?? []
                );
            }
        }

        static::assertEquals(
            $statementStatistic->getTotalAmountOfPublicStatements(),
            count($statistic['guestStatements']) + count($statistic['citizenStatements'])
        );
    }

    public function testGetAssignedStatements(): void
    {
        $statementIdentifiers = [
            'testStatementAssigned6',
            'testStatementAssigned7',
            'testStatementAssigned10',
            'testStatementAssigned11',
            'testStatementAssigned12',
            'testStatementAssigned22',
            'testStatement1',
        ];

        collect($statementIdentifiers)
            ->map(Closure::fromCallable([$this, 'getStatementReference']))
            ->each(Closure::fromCallable([$this, 'assertAllWithSameUserId']));
    }

    private function assertAllWithSameUserId(Statement $statement): void
    {
        $expectedAssignee = $statement->getUser();
        collect(null === $expectedAssignee
            ? null
            : $this->sut->getAssignedStatements($expectedAssignee)
        )->map(
            static function (Statement $statement): ?User {
                return $statement->getAssignee();
            }
        )->each(
            static function (?User $assignee) use ($expectedAssignee): void {
                self::assertSame($expectedAssignee, $assignee);
            }
        );
    }

    public function testCollectRequest(): void
    {
        $testArray = [
            'r_email'               => 'test@test.de',
            'filter_publicVerified' => 'filter',
        ];
        $resultArray = $this->sut->collectRequest($testArray);
        $expectedResultArray = [
            'email' => 'test@test.de',
        ];
        self::assertEquals($expectedResultArray, $resultArray);
    }

    public function testAnonymousEmailAddressOnStatement(): void
    {
        /** @var array<string, Statement> $testStatements */
        $testStatements = [
            'testStatement'                        => $this->getStatementReference('testStatement'),
            'testStatementWithToken'               => $this->getStatementReference('testStatementWithToken'),
            'testStatementOrig'                    => $this->getStatementReference('testStatementOrig'),
            'testFixtureStatement'                 => $this->getStatementReference('testFixtureStatement'),
            'testStatement2'                       => $this->getStatementReference('testStatement2'),
            'childTestStatement2'                  => $this->getStatementReference('childTestStatement2'),
            'testStatementOtherOrga'               => $this->getStatementReference('testStatementOtherOrga'),
            'testStatementNotOriginal'             => $this->getStatementReference('testStatementNotOriginal'),
            'testStatementAssigned6'               => $this->getStatementReference('testStatementAssigned6'),
            'testCopiedStatement2'                 => $this->getStatementReference('testCopiedStatement2'),
            'clusterStatement 1'                   => $this->getStatementReference('clusterStatement 1'),
            'clusterStatement1'                    => $this->getStatementReference('clusterStatement1'),
            'testStatementAssigned10'              => $this->getStatementReference('testStatementAssigned10'),
            'testStatementAssigned11'              => $this->getStatementReference('testStatementAssigned11'),
            'clusterStatement2'                    => $this->getStatementReference('clusterStatement2'),
            'testStatementAssigned12'              => $this->getStatementReference('testStatementAssigned12'),
            'testManualStatement'                  => $this->getStatementReference('testManualStatement'),
            'testStatementParent'                  => $this->getStatementReference('testStatementParent'),
            'testCopiedStatement1'                 => $this->getStatementReference('testCopiedStatement1'),
            'testOriginalStatementWithElementOnly' => $this->getStatementReference('testOriginalStatementWithElementOnly'),
            'testStatementWithElementOnly'         => $this->getStatementReference('testStatementWithElementOnly'),
            'testStatementWithDocumentOnly'        => $this->getStatementReference('testStatementWithDocumentOnly'),
            'testStatementWithParagraphOnly'       => $this->getStatementReference('testStatementWithParagraphOnly'),
            'testStatement20'                      => $this->getStatementReference('testStatement20'),
            'originalStatement21WithInternId'      => $this->getStatementReference('originalStatement21WithInternId'),
            'testStatementWithInternID'            => $this->getStatementReference('testStatementWithInternID'),
            'testStatementAssigned22'              => $this->getStatementReference('testStatementAssigned22'),
            'testStatementWithFile'                => $this->getStatementReference('testStatementWithFile'),
            'clusterStatement3'                    => $this->getStatementReference('clusterStatement3'),
            'statementTestTagsBulkEdit1'           => $this->getStatementReference('statementTestTagsBulkEdit1'),
            'testStatementOrigWithToken'           => $this->getStatementReference('testStatementOrigWithToken'),
        ];

        foreach ($testStatements as $key => $testStatement) {
            // Logic which was used in assessment_statement_detail_statement_data.html.twig:231 before creation of statement.anonymous
            $emailAddressNeedsToBeAnonymized =
                ('' == $testStatement->getMeta()->getAuthorName() || User::ANONYMOUS_USER_NAME == $testStatement->getMeta()->getAuthorName())
                && Statement::EXTERNAL === $testStatement->getPublicStatement()
                && !$testStatement->isManual();

            // Logic which was used for migration to fill statement.anonymous
            $emailAddressAnonymized = $testStatement->hasBeenSubmittedAndAuthoredByUnregisteredCitizen() && User::ANONYMOUS_USER_NAME === $testStatement->getAuthorName();
            self::assertSame($emailAddressNeedsToBeAnonymized, $emailAddressAnonymized, $key);
        }
    }

    public function testExternIds(): void
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $result = $this->sut->getExternIdsInUse($procedure->getId());
        $this->assertCount(31, $result);
    }

    public function testGetStatisticsOfProcedure()
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->logIn($user);
        $this->enablePermissions([
            'feature_json_api_statement',
            'feature_json_api_statement_segment',
            // the following two should not be needed
            // but are anyway for internal reasons currently
            'feature_json_api_procedure',
            'feature_json_api_original_statement',
        ]);

        $expected = $this->getStatementReference('testStatement');
        /** @var CurrentProcedureService $currentProcedureService */
        $currentProcedureService = self::$container->get(CurrentProcedureService::class);
        $currentProcedureService->setProcedure($expected->getProcedure());

        $percentageDistribution = $this->sut->getStatisticsOfProcedure($expected->getProcedure());

        self::assertSame(25, $percentageDistribution->getTotal());
        $absolutes = $percentageDistribution->getAbsolutes();
        self::assertSame(24, $absolutes[StatementService::STATEMENT_STATUS_NEW_COUNT]);
        self::assertSame(1, $absolutes[StatementService::STATEMENT_STATUS_PROCESSING_COUNT]);
        self::assertSame(0, $absolutes[StatementService::STATEMENT_STATUS_COMPLETED_COUNT]);
    }
}
