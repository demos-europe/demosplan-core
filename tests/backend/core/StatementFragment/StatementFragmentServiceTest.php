<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\StatementFragment;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\County;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Municipality;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\StatementFragmentVersion;
use demosplan\DemosPlanCoreBundle\Entity\User\Department;
use demosplan\DemosPlanCoreBundle\Exception\EntityIdNotFoundException;
use demosplan\DemosPlanCoreBundle\Exception\LockedByAssignmentException;
use demosplan\DemosPlanCoreBundle\Exception\MessageBagException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementFragmentService;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;
use Tests\Core\Statement\Functional\Procedure;
use Tests\Core\Statement\Functional\Statement;
use Illuminate\Support\Collection;

use function collect;

class StatementFragmentServiceTest extends FunctionalTestCase
{
    /**
     * @var StatementFragmentService
     */
    protected $sut;

    /**
     * @var DraftStatement
     */
    protected $testDraftStatement;

    /**
     * @var Session
     */
    protected $mockSession;
    /**
     * @var string
     */
    private $considerationAdvice = 'nein nein nein!';
    /**
     * @var string
     */
    private $consideration = 'oh doch!';

    private $voteAdvice = 'updated voteAdvice';
    /**
     * @var string
     */
    private $statementFragmentHtmlText = '<p>statementfragment</p>';

    public function testGetStatementFragmentsTag()
    {
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragmentFilled')->getId());
        $tags = $relatedFragment->getTags();
        static::assertNotEmpty($tags);
        static::assertCount(4, $tags);
        $tag = $tags->get(0);
        $result = $this->sut->getStatementFragmentsTag($tag);

        static::assertCount(1, $result);
        static::assertEquals($relatedFragment, $result[0]);
    }

    public function testDeleteStatementFragment()
    {
        $testFragment = $this->getStatementFragmentReference('testStatementFragment1');

        $fragmentFromDb = $this->sut->getStatementFragment($testFragment->getId());
        static::assertInstanceOf(StatementFragment::class, $fragmentFromDb);

        $result = $this->sut->deleteStatementFragment($testFragment->getId());

        static::assertTrue($result);
        // after deletion doctrine must have removed the ID
        static::assertNull($testFragment->getId());
    }

    /**
     * @dataProvider deleteStatementFragmentFailData()
     */
    public function testDeleteStatementFragmentFail($statementFragmentId)
    {
        $this->expectException(EntityIdNotFoundException::class);

        $this->sut->deleteStatementFragment($statementFragmentId);
    }

    public function deleteStatementFragmentFailData()
    {
        return [
            [null],
            [''],
            ['notExistant'],
        ];
    }

    public function testCreateStatementFragmentVersion()
    {
        $fragmentFixtureId = $this->getStatementFragmentReference('testStatementFragment10')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentFixtureId);
        $version = new StatementFragmentVersion($fragment);

        $testUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $version->setModifiedByUser($testUser);
        $version->setModifiedByDepartment($testUser->getDepartment());

        static::assertEquals($fragment, $version->getStatementFragment());
        static::assertSame($fragment->getDisplayId(), $version->getDisplayId());
        static::assertSame($fragment->getArchivedDepartmentName(), $version->getArchivedDepartmentName());
        static::assertSame($fragment->getArchivedOrgaName(), $version->getArchivedOrgaName());
        static::assertSame($fragment->getArchivedVoteUserName(), $version->getArchivedVoteUserName());
        static::assertSame($fragment->getConsideration(), $version->getConsideration());
        static::assertSame($fragment->getConsiderationAdvice(), $version->getConsiderationAdvice());

        static::assertEquals($fragment->getProcedure(), $version->getProcedure());
        static::assertEquals($fragment->getStatement(), $version->getStatementFragment()->getStatement());
        static::assertSame($fragment->getText(), $version->getText());
        static::assertSame($fragment->getVote(), $version->getVote());
        static::assertSame($fragment->getVoteAdvice(), $version->getVoteAdvice());

        static::assertSame($fragment->getDepartment()->getName(), $version->getDepartmentName());
        static::assertEquals($fragment->getDepartment(), $version->getModifiedByDepartment());
        static::assertEquals($fragment->getDepartment()->getOrga(), $version->getOrgaName());
        static::assertSame($fragment->getTagsAndTopicsAsString(), $version->getTagAndTopicNames());

        static::assertEquals($testUser, $version->getModifiedByUser());

        static::assertSame(
            collect($fragment->getMunicipalityNames())->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $version->getMunicipalityNamesAsJson());

        static::assertSame(
            collect($fragment->getCountyNames())->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $version->getCountyNamesAsJson());

        static::assertSame(
            collect($fragment->getPriorityAreaKeys())->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $version->getPriorityAreaKeysAsJson());

        static::assertNotEquals($fragment->getCreated(), $version->getCreated());

        static::assertEquals($fragment->getParagraph(), $version->getParagraph());
        static::assertSame($fragment->getParagraph()->getId(), $version->getParagraph()->getId());
        static::assertSame($fragment->getParagraphTitle(), $version->getParagraphTitle());
        static::assertSame($fragment->getElementTitle(), $version->getElementTitle());
        static::assertSame($fragment->getElementCategory(), $version->getElementCategory());
    }

    public function testGetTagsAndTopics()
    {
        $fragmentFixtureId = $this->getStatementFragmentReference('testStatementFragmentFilled')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentFixtureId);

        $tagsAndTopics = $fragment->getTagsAndTopics();
        static::assertInstanceOf(Collection::class, $tagsAndTopics);
        static::assertTrue($tagsAndTopics->has('DataFixtureTopic_1'));
        static::assertTrue($tagsAndTopics->has('DataFixtureTopic_2'));
        static::assertInstanceOf(Collection::class, $tagsAndTopics->get('DataFixtureTopic_1'));
        static::assertNotFalse($tagsAndTopics->get('DataFixtureTopic_1')->search('DataFixtureTag_1'));
        static::assertNotFalse($tagsAndTopics->get('DataFixtureTopic_1')->search('DataFixtureTag_2'));
        static::assertNotFalse($tagsAndTopics->get('DataFixtureTopic_1')->search('DataFixtureTag_3'));
        static::assertInstanceOf(Collection::class, $tagsAndTopics->get('DataFixtureTopic_2'));
        static::assertNotFalse($tagsAndTopics->get('DataFixtureTopic_2')->search('DataFixtureTag_4'));
    }

    public function testGetTopics()
    {
        $fragmentFixtureId = $this->getStatementFragmentReference('testStatementFragmentFilled')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentFixtureId);

        $testTopic1 = $this->getTagTopicReference('testFixtureTopic_1');
        $testTopic2 = $this->getTagTopicReference('testFixtureTopic_2');
        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $testTag2 = $this->getTagReference('testFixtureTag_2');
        $testTag3 = $this->getTagReference('testFixtureTag_3');
        $testTag4 = $this->getTagReference('testFixtureTag_4');

        $topics = $fragment->getTopics();
        static::assertInstanceOf(Collection::class, $topics);

        static::assertTrue($topics->has($testTopic1->getId()));
        static::assertInstanceOf(Collection::class, $topics->get($testTopic1->getId()));

        static::assertTrue($topics->get($testTopic1->getId())->has('id'));
        static::assertIsString($topics->get($testTopic1->getId())->get('id'));
        static::assertEquals($topics->get($testTopic1->getId())->get('id'), $testTopic1->getId());

        static::assertTrue($topics->get($testTopic1->getId())->has('title'));
        static::assertIsString($topics->get($testTopic1->getId())->get('title'));
        static::assertSame($topics->get($testTopic1->getId())->get('title'), $testTopic1->getTitle());

        static::assertTrue($topics->get($testTopic1->getId())->has('tags'));
        static::assertInstanceOf(Collection::class, $topics->get($testTopic1->getId())->get('tags'));

        static::assertIsArray($topics->get($testTopic1->getId())->get('tags')->get($testTag1->getId()));
        static::assertEquals($testTag1->getId(), $topics->get($testTopic1->getId())->get('tags')->get($testTag1->getId())['id']);
        static::assertEquals($testTag1->getTitle(), $topics->get($testTopic1->getId())->get('tags')->get($testTag1->getId())['title']);

        static::assertIsArray($topics->get($testTopic1->getId())->get('tags')->get($testTag2->getId()));
        static::assertEquals($testTag2->getId(), $topics->get($testTopic1->getId())->get('tags')->get($testTag2->getId())['id']);
        static::assertEquals($testTag2->getTitle(), $topics->get($testTopic1->getId())->get('tags')->get($testTag2->getId())['title']);

        static::assertIsArray($topics->get($testTopic1->getId())->get('tags')->get($testTag3->getId()));
        static::assertEquals($testTag3->getId(), $topics->get($testTopic1->getId())->get('tags')->get($testTag3->getId())['id']);
        static::assertEquals($testTag3->getTitle(), $topics->get($testTopic1->getId())->get('tags')->get($testTag3->getId())['title']);

        static::assertTrue($topics->has($testTopic2->getId()));
        static::assertInstanceOf(Collection::class, $topics->get($testTopic2->getId()));

        static::assertTrue($topics->get($testTopic2->getId())->has('id'));
        static::assertIsString($topics->get($testTopic2->getId())->get('id'));
        static::assertEquals($topics->get($testTopic2->getId())->get('id'), $testTopic2->getId());

        static::assertTrue($topics->get($testTopic2->getId())->has('title'));
        static::assertIsString($topics->get($testTopic2->getId())->get('title'));
        static::assertSame($topics->get($testTopic2->getId())->get('title'), $testTopic2->getTitle());

        static::assertTrue($topics->get($testTopic2->getId())->has('tags'));
        static::assertInstanceOf(Collection::class, $topics->get($testTopic2->getId())->get('tags'));

        static::assertIsArray($topics->get($testTopic2->getId())->get('tags')->get($testTag4->getId()));
        static::assertEquals($testTag4->getId(), $topics->get($testTopic2->getId())->get('tags')->get($testTag4->getId())['id']);
        static::assertEquals($testTag4->getTitle(), $topics->get($testTopic2->getId())->get('tags')->get($testTag4->getId())['title']);
    }

    public function testGetStatementFragmentsStatementEmptyList()
    {
        /** @var StatementFragment[] $fragments */
        $fragments = $this->sut->getStatementFragmentsStatement($this->getStatementReference('testStatement2')->getId());
        static::assertCount(0, $fragments);
        static::assertIsArray($fragments);
    }

    public function testGetStatementFragmentsStatementNonEmptyList(): void
    {
        $testStatement = $this->getStatementReference('testStatement20');
        $fragments = $this->sut->getStatementFragmentsStatement($testStatement->getId());
        static::assertCount(2, $fragments);
        static::assertIsArray($fragments);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(StatementFragmentService::class);
        $this->testDraftStatement = $this->getDraftStatementReference('testDraftStatement');

        $this->mockSession = $this->setUpMockSession();
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->logIn($user);
    }

    /**
     * Schnutzing means AssignmentToPriorityAreaOrMunicipalityOrCounty.
     *
     * "schnutzen" (verb), das:
     * Der Vorgang des Zuweisens einer Stellungnahme zu einem Landkreis,
     * einer Gemeinde, einem Vorranggebiet, mehreren Landkreisenn, mehreren
     * Gemeinden, mehreren Voranggebieten oder einer beliebigen Kombination
     * der o.g. Optionen.
     *
     * @throws Exception
     */
    public function testUpdateStatementFragmentWithoutSchnutzing()
    {
        /** @var StatementFragment $relatedFragment */
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $storedMunicipalities = $relatedFragment->getMunicipalities();
        $storedCounties = $relatedFragment->getCounties();
        $storedPriorityAreas = $relatedFragment->getPriorityAreas();

        static::assertNull($relatedFragment->getConsideration());
        static::assertNull($relatedFragment->getConsiderationAdvice());

        $data = ['id' => $relatedFragment->getId(), 'considerationAdvice' => $this->considerationAdvice, 'consideration' => $this->consideration];
        $result = $this->sut->updateStatementFragment($data);

        static::assertNotFalse($result);

        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());

        static::assertSame($this->considerationAdvice, $updatedFragment->getConsiderationAdvice());
        static::assertSame($this->consideration, $updatedFragment->getConsideration());
        static::assertEquals($storedPriorityAreas, $updatedFragment->getPriorityAreas());
        static::assertEquals($storedCounties, $updatedFragment->getCounties());
        static::assertEquals($storedMunicipalities, $updatedFragment->getMunicipalities());

        // check the version, which should be created on update of statementFragment:
        $fragmentVersion = $this->sut->getStatementFragmentVersionsOfFragment($updatedFragment->getId());
        static::assertCount(1, $fragmentVersion);

        $fragmentVersion = $fragmentVersion[0];
        static::assertSame(
            $fragmentVersion->getConsiderationAdvice(),
            $result->getConsiderationAdvice()
        );
        static::assertSame($fragmentVersion->getConsideration(), $result->getConsideration());
    }

    public function testUpdateStatementFragmentEmptyConsiderations()
    {
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $relatedFragment->setConsiderationAdvice($this->considerationAdvice);
        $relatedFragment->setConsideration($this->consideration);
        $result = $this->sut->updateStatementFragment($relatedFragment, true);
        static::assertNotFalse($result);

        $emptyString = '';

        $relatedFragment->setConsiderationAdvice($emptyString);
        $relatedFragment->setConsideration($emptyString);

        $result = $this->sut->updateStatementFragment($relatedFragment, true);
        static::assertNotFalse($result);

        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($emptyString, $updatedFragment->getConsiderationAdvice());
        static::assertSame($emptyString, $updatedFragment->getConsideration());

        $null = null;

        $relatedFragment->setConsiderationAdvice($null);
        $relatedFragment->setConsideration($null);

        $result = $this->sut->updateStatementFragment($relatedFragment, true);
        static::assertNotFalse($result);

        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($null, $updatedFragment->getConsiderationAdvice());
        static::assertSame($null, $updatedFragment->getConsideration());
    }

    public function testGetStatementFragment()
    {
        $realtedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());

        /** @var StatementFragment[] $fragments */
        $fragment2 = $this->sut->getStatementFragment($realtedFragment->getId());
        static::assertInstanceOf(StatementFragment::class, $fragment2);
        // check sorting
        static::assertSame($realtedFragment->getId(), $fragment2->getId());

        static::assertEquals($realtedFragment->getMunicipalities(), $fragment2->getMunicipalities());
        static::assertSame(
            $realtedFragment->getArchivedOrgaName(),
            $fragment2->getArchivedOrgaName()
        );
        static::assertSame(
            $realtedFragment->getArchivedDepartmentName(),
            $fragment2->getArchivedDepartmentName()
        );
        static::assertSame(
            $realtedFragment->getArchivedVoteUserName(),
            $fragment2->getArchivedVoteUserName()
        );
        static::assertSame($realtedFragment->getConsideration(), $fragment2->getConsideration());
        static::assertSame(
            $realtedFragment->getConsiderationAdvice(),
            $fragment2->getConsiderationAdvice()
        );
        static::assertEquals($realtedFragment->getCounties(), $fragment2->getCounties());
        static::assertEquals($realtedFragment->getDepartment(), $fragment2->getDepartment());
        static::assertSame($realtedFragment->getDisplayId(), $fragment2->getDisplayId());
        static::assertEquals($realtedFragment->getPriorityAreas(), $fragment2->getPriorityAreas());
        static::assertEquals($realtedFragment->getProcedure(), $fragment2->getProcedure());
        static::assertEquals($realtedFragment->getStatement(), $fragment2->getStatement());
        static::assertEquals($realtedFragment->getTags(), $fragment2->getTags());
        static::assertSame($realtedFragment->getText(), $fragment2->getText());
        static::assertSame($realtedFragment->getVote(), $fragment2->getVote());
        static::assertSame($realtedFragment->getVoteAdvice(), $fragment2->getVoteAdvice());
    }

    public function testAssigningOfFragment()
    {
        self::markSkippedForCIIntervention();

        $this->enableStatementAssignmentFeature();

        $testFragment = $this->getStatementFragmentReference('testStatementFragment1');

        static::assertNull($testFragment->getAssignee());

        // normal update should fail, because no assignee:
        $testFragment->setText('updatedText2435');
        $result = $this->sut->updateStatementFragment($testFragment);
        if (false !== $result) {
            static::fail('Should be false');
        }

        // assign user with ignoring lock (normally via statementHandler->setAssigneeOfStatement())
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $testFragment->setAssignee($user);
        $result = $this->sut->updateStatementFragment($testFragment, true);
        static::assertInstanceOf(StatementFragment::class, $result);

        // with assigned user == current user, it should work
        $testFragment->setText('updatedText666');
        $result = $this->sut->updateStatementFragment($testFragment);
        static::assertInstanceOf(StatementFragment::class, $result);

        $updatedFragment = $this->sut->getStatementFragment($testFragment->getId());
        static::assertSame('updatedText666', $updatedFragment->getText());
    }

    public function testAssigningOfFragmentWithClusters()
    {
        self::markSkippedForCIIntervention();

        $this->enableStatementAssignmentFeature();
        $this->enableStatementClusterFeature();

        $testFragment = $this->getStatementFragmentReference('testStatementFragment1');
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        static::assertNull($testFragment->getAssignee());

        // normal update should fail, because no assignee:
        $testFragment->setText('updatedText2435');
        $result = $this->sut->updateStatementFragment($testFragment);
        if (false !== $result) {
            static::fail('Should be false');
        }

        // assign user with ignoring lock (normally via statementHandler->setAssigneeOfStatement())
        $testFragment->setAssignee($user);
        $result = $this->sut->updateStatementFragment($testFragment, true);
        static::assertInstanceOf(StatementFragment::class, $result);

        // with assigned user == current user, it should work
        $testFragment->setText('updatedText666');
        $result = $this->sut->updateStatementFragment($testFragment);
        static::assertInstanceOf(StatementFragment::class, $result);

        $updatedFragment = $this->sut->getStatementFragment($testFragment->getId());
        static::assertSame('updatedText666', $updatedFragment->getText());
    }

    /**
     * TODO: known to fail.
     *
     * @throws \demosplan\DemosPlanCoreBundle\Exception\MessageBagException
     */
    public function testUpdateStatementFragment()
    {
        self::markSkippedForCIIntervention();

        $fragmentFixture = $this->getStatementFragmentReference('testStatementFragment1');
        $fragment = $this->sut->getStatementFragment($fragmentFixture->getId());
        $fragmentId = $fragment->getId();
        $initialNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);

        static::assertNull($fragment->getConsideration());
        static::assertNull($fragment->getConsiderationAdvice());

        $data = ['id' => $fragmentId, 'considerationAdvice' => $this->considerationAdvice, 'consideration' => $this->consideration];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($this->considerationAdvice, $updatedFragment->getConsiderationAdvice());
        static::assertSame($this->consideration, $updatedFragment->getConsideration());
        // test version
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 1, $currentNumberOfVersions);

        $data = ['id' => $fragmentId, 'consideration' => null];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($this->considerationAdvice, $updatedFragment->getConsiderationAdvice());
        static::assertNull($updatedFragment->getConsideration());
        // test version
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 2, $currentNumberOfVersions);

        $data = ['id' => $fragmentId, 'considerationAdvice' => null];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertNull($updatedFragment->getConsiderationAdvice());
        static::assertNull($updatedFragment->getConsideration());
        // test version
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 3, $currentNumberOfVersions);

        $expectedUser = $this->getUserReference('testUser2');
        static::assertEquals($expectedUser, $result->getModifiedByUser());
    }

    public function testCreationOfVersionOnUpdateStatementFragment()
    {
        $fragmentFixture = $this->getStatementFragmentReference('testStatementFragment1');
        $fragment = $this->sut->getStatementFragment($fragmentFixture->getId());
        $fragmentId = $fragment->getId();

        $initialNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame(0, $initialNumberOfVersions);

        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertEmpty($relatedVersions);

        $data = ['id' => $fragmentId, 'text' => 'neuer text!'];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 1, $currentNumberOfVersions);
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertNotNull($relatedVersions);
        static::assertCount(1, $relatedVersions);

        /** @var StatementFragmentVersion $relatedVersion */
        $relatedVersion = $relatedVersions[0];
        static::assertEquals($fragmentId, $relatedVersion->getStatementFragment()->getId());
        static::assertEquals($fragment, $relatedVersion->getStatementFragment());
        static::assertSame($fragment->getConsideration(), $relatedVersion->getConsideration());
        static::assertSame($fragment->getVote(), $relatedVersion->getVote());
        static::assertSame($fragment->getVoteAdvice(), $relatedVersion->getVoteAdvice());
        static::assertSame(
            $fragment->getArchivedDepartmentName(),
            $relatedVersion->getArchivedDepartmentName()
        );
        static::assertSame(
            $fragment->getDepartment()->getName(),
            $relatedVersion->getDepartmentName()
        );
        static::assertEquals($fragment->getProcedure(), $relatedVersion->getProcedure());
        static::assertSame('[]', $relatedVersion->getMunicipalityNamesAsJson());
        static::assertSame('[]', $relatedVersion->getPriorityAreaKeysAsJson());
        static::assertSame('[]', $relatedVersion->getCountyNamesAsJson());
        static::assertSame(
            $fragment->getTagsAndTopicsAsString(),
            $relatedVersion->getTagAndTopicNames()
        );
        static::assertSame('neuer text!', $fragment->getText());

        static::assertNotEquals($fragment->getCreated(), $relatedVersion->getCreated());
        // TODO:      check wheter writing the version _after_ the update is really
        //            the desired behaviour in updateStatementFragmentArray
        //            and updateStatementFragment
        static::assertSame($fragment->getText(), $relatedVersion->getText());
    }

    public function testCreationOfVersionOnUpdateStatementFragmentVote()
    {
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $fragmentId = $fragment->getId();

        $initialNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame(0, $initialNumberOfVersions);

        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertEmpty($relatedVersions);

        $data = ['id' => $fragmentId, 'vote' => 'full', 'archivedVoteUserName' => 'imagineTestUser'];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 1, $currentNumberOfVersions);
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertNotNull($relatedVersions);
        static::assertCount(1, $relatedVersions);

        /** @var StatementFragmentVersion $relatedVersion */
        $relatedVersion = $relatedVersions[0];
        static::assertEquals($fragmentId, $relatedVersion->getStatementFragment()->getId());
        static::assertEquals($fragment, $relatedVersion->getStatementFragment());
        static::assertSame($fragment->getConsideration(), $relatedVersion->getConsideration());
        static::assertSame($fragment->getVoteAdvice(), $relatedVersion->getVoteAdvice());
        static::assertSame(
            $fragment->getArchivedDepartmentName(),
            $relatedVersion->getArchivedDepartmentName()
        );
        static::assertSame(
            $fragment->getDepartment()->getName(),
            $relatedVersion->getDepartmentName()
        );
        static::assertEquals($fragment->getProcedure(), $relatedVersion->getProcedure());
        static::assertSame('[]', $relatedVersion->getMunicipalityNamesAsJson());
        static::assertSame('[]', $relatedVersion->getPriorityAreaKeysAsJson());
        static::assertSame('[]', $relatedVersion->getCountyNamesAsJson());
        static::assertSame(
            $fragment->getTagsAndTopicsAsString(),
            $relatedVersion->getTagAndTopicNames()
        );
        static::assertSame($fragment->getText(), $relatedVersion->getText());

        static::assertNotEquals($fragment->getCreated(), $relatedVersion->getCreated());
        static::assertSame(
            $fragment->getArchivedVoteUserName(),
            $relatedVersion->getArchivedVoteUserName()
        );
        static::assertSame($fragment->getVote(), $relatedVersion->getVote());

        static::assertSame('imagineTestUser', $fragment->getArchivedVoteUserName());
        static::assertSame('full', $fragment->getVote());

        $initialNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame(1, $initialNumberOfVersions);

        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(1, $relatedVersions);

        $data = ['id' => $fragmentId, 'voteAdvice' => 'full', 'archivedDepartmentName' => 'NameOfADepartment', 'archivedOrgaName' => 'NameOfAOrga'];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 1, $currentNumberOfVersions);
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertNotNull($relatedVersions);
        static::assertCount(2, $relatedVersions);

        /** @var StatementFragmentVersion $relatedVersion */
        $relatedVersion = $relatedVersions[1];
        static::assertEquals($fragment, $relatedVersion->getStatementFragment());
        static::assertEquals($fragmentId, $relatedVersion->getStatementFragment()->getId());
        static::assertSame($fragment->getConsideration(), $relatedVersion->getConsideration());
        static::assertSame(
            $fragment->getDepartment()->getName(),
            $relatedVersion->getDepartmentName()
        );
        static::assertEquals($fragment->getProcedure(), $relatedVersion->getProcedure());
        static::assertSame('[]', $relatedVersion->getMunicipalityNamesAsJson());
        static::assertSame('[]', $relatedVersion->getPriorityAreaKeysAsJson());
        static::assertSame('[]', $relatedVersion->getCountyNamesAsJson());
        static::assertSame(
            $fragment->getTagsAndTopicsAsString(),
            $relatedVersion->getTagAndTopicNames()
        );
        static::assertSame($fragment->getText(), $relatedVersion->getText());
        static::assertSame($fragment->getVote(), $relatedVersion->getVote());
        static::assertSame(
            $fragment->getArchivedVoteUserName(),
            $relatedVersion->getArchivedVoteUserName()
        );

        static::assertNotEquals($fragment->getCreated(), $relatedVersion->getCreated());
        static::assertSame($fragment->getVoteAdvice(), $relatedVersion->getVoteAdvice());
        static::assertSame(
            $fragment->getArchivedDepartmentName(),
            $relatedVersion->getArchivedDepartmentName()
        );
        static::assertSame($fragment->getArchivedOrgaName(), $relatedVersion->getArchivedOrgaName());

        static::assertSame('NameOfADepartment', $fragment->getArchivedDepartmentName());
        static::assertSame('NameOfAOrga', $fragment->getArchivedOrgaName());
        static::assertSame('full', $fragment->getVoteAdvice());
    }

    public function testSetDepartmentOfStatementFragment()
    {
        $reference = 'testStatementFragmentFilled';
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference($reference)->getId());
        $fragmentId = $fragment->getId();
        static::assertNull($fragment->getDepartment());
        /** @var Department $newDepartment */
        $newDepartment = $this->getReference('testDepartment');

        $data = ['id' => $fragmentId, 'departmentId' => $newDepartment->getId()];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference($reference)->getId());
        static::assertEquals($newDepartment, $fragment->getDepartment());
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(1, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[0]->getStatementFragment()->getId());
        static::assertSame(
            $fragment->getDepartment()->getName(),
            $relatedVersions[0]->getDepartmentName()
        );

        $data = ['id' => $fragmentId, 'departmentId' => null];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference($reference)->getId());
        static::assertNull($fragment->getDepartment());
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(2, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[1]->getStatementFragment()->getId());
        static::assertNull($relatedVersions[1]->getDepartmentName());
    }

    public function testCountyOfFragment()
    {
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $fragmentId = $fragment->getId();

        // get initial empty:
        static::assertEmpty($fragment->getCounties());
        // add county + get county:
        /** @var County $testCounty */
        $testCounty = $this->getReference('testCounty1');
        static::assertEmpty($fragment->getCounties());
        $data = ['id' => $fragmentId, 'counties' => [$testCounty->getId()]];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        static::assertCount(1, $fragment->getCounties());
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(1, $fragment->getCounties());

        // get county from fragment:
        static::assertCount(1, $fragment->getCounties());
        static::assertContains($testCounty, $fragment->getCounties());
        // check version:
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(1, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[0]->getStatementFragment()->getId());
        static::assertSame(
            collect($fragment->getCountyNames())->toJson(
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $relatedVersions[0]->getCountyNamesAsJson()
        );

        static::assertContains($testCounty, $fragment->getCounties());
        static::assertContains($fragment, $testCounty->getStatementFragments());

        // remove county from fragment
        $data = ['id' => $fragmentId, 'counties' => []];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertEmpty($fragment->getCounties());
        // check version:
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(2, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[1]->getStatementFragment()->getId());
        static::assertSame(
            collect($fragment->getCountyNames())->toJson(
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $relatedVersions[1]->getCountyNamesAsJson()
        );

//        //add county to fragment
//        static::assertEmpty($fragment->getCounties());
        // //        $fragment->addCounty($testCounty);
        // //        $counties = $fragment->getCounties();
        // //        $counties->add($testCounty);
//        $data = ['counties' => [$testCounty]];
//        $this->sut->updateStatementFragment($fragmentId, $data);
//        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
//        $testCounty = $this->sut->getCounty($testCounty->getId());
//        static::assertContains($testCounty, $fragment->getCounties());
//        //check version:
//        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
//        static::assertCount(3, $relatedVersions);
//        static::assertEquals($fragmentId, $relatedVersions[2]->getStatementFragment()->getId());
//        static::assertNotEquals($fragment->getCounties()->count(), $relatedVersions[2]->getCounties()->count());
    }

    public function testMunicipalityOfFragment()
    {
        $procedureId = $this->getProcedureReference('testProcedure')->getId();
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $fragmentId = $fragment->getId();
        // get initial empty:
        static::assertEmpty($fragment->getMunicipalities());
        /** @var Municipality $testMunicipality */
        $testMunicipality = $this->getReference('testMunicipality1');

        // add Municipality + get Municipality:
        $data = ['id' => $fragmentId, 'municipalities' => [$testMunicipality->getId()]];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(1, $fragment->getMunicipalities());
        static::assertContains($testMunicipality, $fragment->getMunicipalities());

        // get Municipality from fragment:
        static::assertCount(1, $fragment->getMunicipalities());
        static::assertContains($testMunicipality, $fragment->getMunicipalities());
        // check version:
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(1, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[0]->getStatementFragment()->getId());
        static::assertSame(
            collect($fragment->getMunicipalityNames())->toJson(
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $relatedVersions[0]->getMunicipalityNamesAsJson()
        );

        // remove Municipality from fragment
        $data = ['id' => $fragmentId, 'municipalities' => []];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(0, $fragment->getMunicipalities());
        // check version:
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(2, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[1]->getStatementFragment()->getId());
        static::assertSame(
            collect($fragment->getMunicipalityNames())->toJson(
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $relatedVersions[1]->getMunicipalityNamesAsJson()
        );

//        //add Municipality to fragment
        // //        $fragment->addMunicipality($testMunicipality);
//        $municipalities = $fragment->getMunicipalities();
//        $municipalities->add($testMunicipality);
//        $data = ['municipalities' => $municipalities];
//        $this->sut->updateStatementFragment($fragmentId, $data);
//        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
//        static::assertCount(1, $fragment->getMunicipalities());
//        static::assertContains($testMunicipality, $fragment->getMunicipalities());
//        //check version:
//        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
//        static::assertCount(3, $relatedVersions);
//        static::assertEquals($fragmentId, $relatedVersions[2]->getStatementFragment()->getId());
//        static::assertNotEquals($fragment->getMunicipalities()->count(), $relatedVersions[2]->getMunicipalities()->count());
    }

    public function testPriorityAreaOfFragment()
    {
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $fragmentId = $fragment->getId();

        // get initial empty:
        static::assertEmpty($fragment->getPriorityAreas());

        // add PriorityArea + get PriorityArea:
        $testPriorityArea = $this->getReference('testPriorityArea1');
        $data = ['id' => $fragmentId, 'priorityAreas' => [$testPriorityArea->getId()]];
        static::assertCount(0, $fragment->getPriorityAreas());
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(1, $fragment->getPriorityAreas());
        static::assertContains($testPriorityArea, $fragment->getPriorityAreas());
        // check version:
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(1, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[0]->getStatementFragment()->getId());
        static::assertSame(
            collect($fragment->getPriorityAreaKeys())->toJson(JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            $relatedVersions[0]->getPriorityAreaKeysAsJson());

        // get PriorityArea from fragment:
        static::assertCount(1, $fragment->getPriorityAreas());
        static::assertContains($testPriorityArea, $fragment->getPriorityAreas());

        // remove PriorityArea from fragment
        $data = ['id' => $fragment->getId(), 'priorityAreas' => []];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertNotContains($testPriorityArea, $fragment->getPriorityAreas());
        // check version:
        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
        static::assertCount(2, $relatedVersions);
        static::assertEquals($fragmentId, $relatedVersions[1]->getStatementFragment()->getId());
        static::assertSame(
            collect($fragment->getPriorityAreaKeys())->toJson(
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ),
            $relatedVersions[1]->getPriorityAreaKeysAsJson()
        );

//        //add PriorityArea to fragment
        // //        $fragment->addPriorityArea($testPriorityArea);
//        $priorityAreas = $fragment->getPriorityAreas();
//        $priorityAreas->add($testPriorityArea);
//        $data = ['priorityAreas' => $priorityAreas];
//        $this->sut->updateStatementFragment($fragmentId, $data);
//        $fragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
//        static::assertCount(1, $fragment->getPriorityAreas());
//        static::assertContains($testPriorityArea, $fragment->getPriorityAreas());
//        //check version:
//        $relatedVersions = $this->sut->getStatementFragmentVersionsOfFragment($fragmentId);
//        static::assertCount(3, $relatedVersions);
//        static::assertEquals($fragmentId, $relatedVersions[2]->getStatementFragment()->getId());
//        static::assertNotEquals($fragment->getMunicipalities()->count(), $relatedVersions[2]->getMunicipalities()->count());
    }

    public function testArchivedOrgaName()
    {
        $fragmentFixtureId = $this->getStatementFragmentReference('testStatementFragment1')->getId();
        $relatedFragment = $this->sut->getStatementFragment($fragmentFixtureId);
        static::assertNull($relatedFragment->getArchivedOrgaName());
        static::assertNull($relatedFragment->getArchivedDepartmentName());

        $newOrgaName = 'tolleNeueOrga';
//        $relatedFragment->setArchivedOrgaName($newOrgaName);
        $data = ['id' => $relatedFragment->getId(), 'archivedOrgaName' => $newOrgaName];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $this->sut->getStatementFragment($relatedFragment->getId());
        static::assertSame($newOrgaName, $relatedFragment->getArchivedOrgaName());
        static::assertNull($relatedFragment->getArchivedDepartmentName());

//        $relatedFragment->setArchivedOrgaName(null);
        $data = ['id' => $relatedFragment->getId(), 'archivedOrgaName' => null];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $this->sut->getStatementFragment($relatedFragment->getId());
        static::assertNull($relatedFragment->getArchivedOrgaName());
        static::assertNull($relatedFragment->getArchivedDepartmentName());
    }

    public function testArchivedDepartmentName()
    {
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertNull($relatedFragment->getArchivedDepartmentName());
        static::assertNull($relatedFragment->getArchivedOrgaName());

        $newOrgaName = 'tollesNeuesDepartment';
//        $relatedFragment->setArchivedDepartmentName($newOrgaName);
        $data = ['id' => $relatedFragment->getId(), 'archivedDepartmentName' => $newOrgaName];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $this->sut->getStatementFragment($relatedFragment->getId());
        static::assertSame($newOrgaName, $relatedFragment->getArchivedDepartmentName());
        static::assertNull($relatedFragment->getArchivedOrgaName());

//        $relatedFragment->setArchivedDepartmentName(null);
        $data = ['id' => $relatedFragment->getId(), 'archivedDepartmentName' => null];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $this->sut->getStatementFragment($relatedFragment->getId());
        static::assertNull($relatedFragment->getArchivedDepartmentName());
        static::assertNull($relatedFragment->getArchivedOrgaName());
    }

    /**
     * TODO: known to fail because $currentUserOfMockedSession is not used in sut
     * Better update fragment to ensure that fragment is not locked by user.
     *
     * Test of deleting a fragment which is claimed by another user.
     */
    public function testDeleteLockedStatementFragment()
    {
        self::markSkippedForCIIntervention();

        $this->expectException(LockedByAssignmentException::class);

        $this->enableStatementAssignmentFeature();
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $currentUserOfMockedSession = $this->getUserReference('testUser2');
        static::assertNotEquals($currentUserOfMockedSession, $user);

        $fragmentId = $this->getStatementFragmentReference('testStatementFragmentAssigned4')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertEquals($user, $fragment->getAssignee());
        static::assertNotEquals($currentUserOfMockedSession, $fragment->getAssignee());

        $deleted = $this->sut->deleteStatementFragment($fragmentId);
        static::assertFalse($deleted);
    }

    /** Test of deleting a fragment which unclaimed. */
    public function testDeleteUnClaimedStatementFragment()
    {
        $this->enableStatementAssignmentFeature();
        $fragmentId = $this->getStatementFragmentReference('testStatementFragment10')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentId);

        // setup test data
        $fragment->setAssignee(null);
        $this->sut->updateStatementFragment($fragment, true);
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertNull($fragment->getAssignee());

        // execute deletion
        $deleted = $this->sut->deleteStatementFragment($fragmentId);
        static::assertTrue($deleted);
        static::assertNull($this->sut->getStatementFragment($fragmentId));
    }

    /** Test of deleting a fragment which is claimed by the current user. */
    public function testDeleteClaimedStatementFragment()
    {
        $this->enableStatementAssignmentFeature();
        $currentUserOfMockedSession = $this->getUserReference('testUser2');
        $fragmentId = $this->getStatementFragmentReference('testStatementFragment10')->getId();
        $fragment = $this->sut->getStatementFragment($fragmentId);

        // setup test data
        $fragment->setAssignee($currentUserOfMockedSession);
        $this->sut->updateStatementFragment($fragment, true);
        $fragment = $this->sut->getStatementFragment($fragmentId);
        static::assertEquals($currentUserOfMockedSession, $fragment->getAssignee());

        // execute deletion
        $deleted = $this->sut->deleteStatementFragment($fragmentId);
        static::assertTrue($deleted);
        static::assertNull($this->sut->getStatementFragment($fragmentId));
    }

    protected function enableStatementClusterFeature()
    {
        // modify permissions for test
        $permissions = $this->getMockSession()->get('permissions');
        $permissions['feature_statement_cluster']['enabled'] = true;
        $this->getMockSession()->set('permissions', $permissions);
    }

    protected function enableStatementAssignmentFeature()
    {
        // modify permissions for test
        $permissions = $this->getMockSession()->get('permissions');
        $permissions['feature_statement_assignment']['enabled'] = true;
        $this->getMockSession()->set('permissions', $permissions);
    }

    /**
     * TODO: known to fail.
     *
     * @throws MessageBagException
     */
    public function testCreateEntityContentChangeForConsiderationOnUpdateFragmentObject()
    {
        self::markSkippedForCIIntervention();

        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragment->setConsideration('updated Consideration');
        $amountOfContentChangesBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'consideration']
        );

        $updatedFragment = $this->sut->updateStatementFragment($fragment);
        $amountOfContentChangesAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'consideration']
        );
        static::assertSame($amountOfContentChangesBefore + 1, $amountOfContentChangesAfter);
    }

    /**
     * TODO: known to fail.
     *
     * @throws MessageBagException
     */
    public function testCreateEntityContentChangeForConsiderationOnUpdateFragmentArray()
    {
        self::markSkippedForCIIntervention();

        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragmentData = [
            'id'            => $fragment->getId(),
            'consideration' => 'updated Consideration',
        ];
        $amountOfContentChangesBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'consideration']
        );
        $this->sut->updateStatementFragment($fragmentData);
        $amountOfContentChangesAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'consideration']
        );
        static::assertSame($amountOfContentChangesBefore + 1, $amountOfContentChangesAfter);
    }

    /**
     * TODO: known to fail.
     *
     * @throws MessageBagException
     */
    public function testCreateEntityContentChangeForVoteOnUpdateFragmentObject()
    {
        self::markSkippedForCIIntervention();

        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragment->setVote('updated vote');
        $amountOfContentChangesBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'vote']
        );
        $updatedFragment = $this->sut->updateStatementFragment($fragment);
        $amountOfContentChangesAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'vote']
        );
        static::assertSame($amountOfContentChangesBefore + 1, $amountOfContentChangesAfter);
    }

    /**
     * TODO: known to fail.
     *
     * @throws MessageBagException
     */
    public function testCreateEntityContentChangeForVoteOnUpdateFragmentArray()
    {
        self::markSkippedForCIIntervention();

        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragmentData = [
            'id'   => $fragment->getId(),
            'vote' => 'updated vote',
        ];
        $amountOfContentChangesBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'vote']
        );
        $this->sut->updateStatementFragment($fragmentData);
        $amountOfContentChangesAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'vote']
        );
        static::assertSame($amountOfContentChangesBefore + 1, $amountOfContentChangesAfter);
    }

    public function testDoNotCreateEntityContentChangeForConsiderationAdviceOnUpdateFragmentObject()
    {
        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragment->setConsiderationAdvice('updated ConsiderationAdvice');
        $amountOfContentChangesBefore = $this->countEntries(EntityContentChange::class);
        $updatedFragment = $this->sut->updateStatementFragment($fragment);
        $amountOfContentChangesAfter = $this->countEntries(EntityContentChange::class);
        // do not create version on update considerationAdvice!
        static::assertSame($amountOfContentChangesBefore, $amountOfContentChangesAfter);
    }

    public function testDoNotCreateEntityContentChangeForConsiderationAdviceOnUpdateFragmentArray()
    {
        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragmentData = [
            'id'                  => $fragment->getId(),
            'considerationAdvice' => 'updated ConsiderationAdvice',
        ];
        $amountOfContentChangesBefore = $this->countEntries(EntityContentChange::class);
        $this->sut->updateStatementFragment($fragmentData);
        $amountOfContentChangesAfter = $this->countEntries(EntityContentChange::class);
        // do not create version on update considerationAdvice!
        static::assertSame($amountOfContentChangesBefore, $amountOfContentChangesAfter);
    }

    public function testDoNotCreateEntityContentChangeForVoteAdviceOnUpdateFragmentObject()
    {
        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragment->setVoteAdvice($this->voteAdvice);
        $amountOfContentChangesBefore = $this->countEntries(EntityContentChange::class);
        $updatedFragment = $this->sut->updateStatementFragment($fragment);
        $amountOfContentChangesAfter = $this->countEntries(EntityContentChange::class);
        // do not create version on update voteAdvice!
        static::assertSame($amountOfContentChangesBefore, $amountOfContentChangesAfter);
    }

    public function testDoNotCreateEntityContentChangeForVoteAdviceOnUpdateFragmentArray()
    {
        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragmentData = [
            'id'         => $fragment->getId(),
            'voteAdvice' => $this->voteAdvice,
        ];
        $amountOfContentChangesBefore = $this->countEntries(EntityContentChange::class);
        $this->sut->updateStatementFragment($fragmentData);
        $amountOfContentChangesAfter = $this->countEntries(EntityContentChange::class);
        // do not create version on update voteAdvice!
        static::assertSame($amountOfContentChangesBefore, $amountOfContentChangesAfter);
    }

    public function testDoNotCreateEntityContentChangeForVoteOnUpdateVoteAdviceOnFragmentArray()
    {
        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragmentData = [
            'id'                                     => $fragment->getId(),
            'voteAdvice'                             => $this->voteAdvice,
            'copyConsiderationAdviceToConsideration' => true,
        ];
        $amountOfContentChangesOfVoteAdviceBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'voteAdvice']
        );
        $amountOfContentChangesOfVoteBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'vote']
        );

        $this->sut->updateStatementFragment($fragmentData);

        $amountOfContentChangesOfVoteAdviceAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'voteAdvice']
        );
        $amountOfContentChangesOfVoteAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'vote']
        );
        // do not create version on update voteAdvice
        static::assertSame(
            $amountOfContentChangesOfVoteAdviceBefore,
            $amountOfContentChangesOfVoteAdviceAfter
        );
        // and do not copy voteAdvice into vote, therefore no new version for vote:
        static::assertSame($amountOfContentChangesOfVoteBefore, $amountOfContentChangesOfVoteAfter);
    }

    /**
     * TODO: known to fail.
     *
     * @throws MessageBagException
     */
    public function testCreateEntityContentChangeForVoteOnUpdateVoteAdviceOnFragmentArray()
    {
        self::markSkippedForCIIntervention();

        $fragment = $this->getStatementFragmentReference('testStatementFragmentFilled');
        $fragment->setVoteAdvice($this->voteAdvice);
        $fragmentData = [
            'id'                                     => $fragment->getId(),
            'considerationAdvice'                    => 'updated considerationAdvice',
            'copyConsiderationAdviceToConsideration' => true,
        ];
        $amountOfContentChangesOfConsiderationAdviceBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'considerationAdvice']
        );
        $amountOfContentChangesOfConsiderationBefore = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'consideration']
        );

        $this->sut->updateStatementFragment($fragmentData);

        $amountOfContentChangesOfConsiderationAdviceAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'considerationAdvice']
        );
        $amountOfContentChangesOfConsiderationAfter = $this->countEntries(
            EntityContentChange::class,
            ['entityType' => StatementFragment::class, 'entityField' => 'consideration']
        );
        // do not create version on update considerationAdvice:
        static::assertSame(
            $amountOfContentChangesOfConsiderationAdviceBefore,
            $amountOfContentChangesOfConsiderationAdviceAfter
        );
        // but a version consideration,because considerationAdvice was copied into consideration in case of copyConsiderationAdviceToConsideration
        static::assertSame(
            $amountOfContentChangesOfConsiderationBefore + 1,
            $amountOfContentChangesOfConsiderationAfter
        );
    }

    /**
     * @return \Symfony\Component\HttpFoundation\Session\Session
     */
    protected function getMockSession()
    {
        return $this->mockSession;
    }

    /**
     * Während der Arbeit mit DataObjects ist eine Besonderheit im Verhalten von Doctrine aufgefallen, das wir bis jetzt nicht enträzelt/gelöst haben:
     * Wenn ein Objekt geupdated werden soll und im Zuge dessen, ein Array von Tags übergeben wird, kommt es zum Fehler beim Inserten dieser Tags wenn:.
     *
     * Das Objekt bereits ein Teil dieser Tags hat. Das passiert dann wenn die Liste von Tags erweitert werden soll.
     * Dann wird die gesamte Liste von Tags (inkl. des neuen Tags) ans Backend übrgeben.
     * In der Anwendung funktionert es, das so erhaltene Array von Tags mittels set() dem Objekt zuzuweisen und damit,
     * das bis dahin bestehende array von Tags zu üebrschreiben
     * beim persistieren wird dann nur das neu hinzugefügte Tag als solches erkannt und nur dieses zusätzlich in die DB geschrieben
     *
     * In unseren Tests funktioniert das allerdings nicht.
     * Wenn wir ein Objekt, das bereits Tag(s) hat, um weitere Tags erweitern wollen,
     * geben wir auch hier ein Array mit Tags an die entsprechende Methode.
     * Dieses array enthält (wie auch in der Anwendung) die "alten" als auch das neue Tag.
     * Auch hier wird das Array mittels set() dem Objekt zuzuweisen und damit das bis dahin bestehende Array von Tags zu überschreiben.
     * Beim persistieren versucht Doctrine allerdings, nicht nur das neu hinzu gekommmene Tag zu inserten,
     * sondern jedes Tag im array !
     * Das fürt zu einer "unique constraint vaiolation".

     * Der einzige Unterschied der bis jetzt erkannt worden ist, ist dass sich diese beiden fälle in den Objekttypen der Tags unterscheiden.
     * So git es einmal "unser normales" Tagobjekt und einmal das "doctrine" Proxyobjekt.
     * Ich vermute dass Doctrine nur Proxyobjekte inserted. "normale" objecte werden schlicht nicht getrackt.
     */
    public function testUpdateStatementFragmentVote()
    {
        $this->loginTestUser();

        /** @var Department $testDepartment */
        $testDepartment = $this->getReference('testDepartmentPlanningOffice');

        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertNull($relatedFragment->getVoteAdvice());
        static::assertNull($relatedFragment->getVote());

        $inputString1 = 'acknowledge';
        $result = $this->sut->updateStatementFragment(
            [
                'id'                     => $relatedFragment->getId(),
                'archivedDepartmentName' => $testDepartment->getName(),
                'archivedOrgaName'       => $testDepartment->getOrgaName(),
                'voteAdvice'             => $inputString1,
            ],
            true);

        static::assertNotFalse($result);
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($inputString1, $relatedFragment->getVoteAdvice());
        static::assertSame(
            $testDepartment->getName(),
            $relatedFragment->getArchivedDepartmentName()
        );
        static::assertSame($testDepartment->getOrgaName(), $relatedFragment->getArchivedOrgaName());
        static::assertNull($relatedFragment->getVote());

        $inputString2 = 'partial';
        $result = $this->sut->updateStatementFragment(['id' => $relatedFragment->getId(), 'vote' => $inputString2], true);
        static::assertNotFalse($result);
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($inputString1, $relatedFragment->getVoteAdvice());
        static::assertSame($inputString2, $relatedFragment->getVote());
        // check if not have changed:
        static::assertSame(
            $testDepartment->getName(),
            $relatedFragment->getArchivedDepartmentName()
        );
        static::assertSame($testDepartment->getOrgaName(), $relatedFragment->getArchivedOrgaName());

        $inputString3 = 'full';
        $inputString4 = 'partial';
        $result = $this->sut->updateStatementFragment(
            [
                'id'                     => $relatedFragment->getId(),
                'archivedDepartmentName' => $testDepartment->getName(),
                'archivedOrgaName'       => $testDepartment->getOrgaName(),
                'voteAdvice'             => $inputString3, 'vote' => $inputString4,
            ],
            true);
        static::assertNotFalse($result);
        $relatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertSame($inputString3, $relatedFragment->getVoteAdvice());
        static::assertSame(
            $testDepartment->getName(),
            $relatedFragment->getArchivedDepartmentName()
        );
        static::assertSame($testDepartment->getOrgaName(), $relatedFragment->getArchivedOrgaName());
        static::assertSame($inputString4, $relatedFragment->getVote());

        $result = $this->sut->updateStatementFragment(
            [
                'id'                     => $relatedFragment->getId(),
                'archivedDepartmentName' => $testDepartment->getName(),
                'archivedOrgaName'       => $testDepartment->getOrgaName(),
                'voteAdvice'             => '',
            ],
            true);
        static::assertNotFalse($result);
        static::assertNull($relatedFragment->getVoteAdvice());
        static::assertSame($inputString4, $relatedFragment->getVote());

        $result = $this->sut->updateStatementFragment(
            [
                'id'   => $relatedFragment->getId(),
                'vote' => '',
            ],
            true);
        static::assertNotFalse($result);
        static::assertNull($relatedFragment->getVoteAdvice());
        static::assertNull($relatedFragment->getVote());

        $result = $this->sut->updateStatementFragment(
            [
                'id'                     => $relatedFragment->getId(),
                'archivedDepartmentName' => $testDepartment->getName(),
                'archivedOrgaName'       => $testDepartment->getOrgaName(),
                'vote'                   => null,
                'voteAdvice'             => null,
            ],
            true);
        static::assertNotFalse($result);
        static::assertNull($relatedFragment->getVoteAdvice());
        static::assertNull($relatedFragment->getVote());

        $result = $this->sut->updateStatementFragment(
            [
                'id'                     => $relatedFragment->getId(),
                'archivedDepartmentName' => $testDepartment->getName(),
                'archivedOrgaName'       => $testDepartment->getOrgaName(),
            ],
            true);
        static::assertNotFalse($result);
        static::assertNull($relatedFragment->getVoteAdvice());
        static::assertNull($relatedFragment->getVote());
    }

    public function testHandlerUpdateStatementFragmentTags()
    {
        self::markSkippedForCIIntervention();
        // Will fail, because of diffrent object type (doctrine-Proxy)

        $relatedFragment = $this->getStatementFragmentReference('testStatementFragment1');
        $topic = $this->sut->getTopic($this->getTagTopicReference('testFixtureTopic_1')->getId());
        $tags = $topic->getTags();

        static::assertEmpty($relatedFragment->getTags());
//        $relatedFragment->addTag($tags[0]);

        $fragmentData = ['id' => $relatedFragment->getId(), 'tags' => [$tags[0]->getId()]];
        $result = $this->sut->updateStatementFragment($fragmentData, true);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(1, $updatedFragment->getTags());

        $fragmentData = ['id' => $relatedFragment->getId(), 'tags' => [$tags[0]->getId(), $tags[1]->getId()]];
        $result = $this->sut->updateStatementFragment($fragmentData, true);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(2, $updatedFragment->getTags());

        $relatedFragment->removeTag($tags[1]);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(1, $updatedFragment->getTags());

        $relatedFragment->removeTag($tags[0]);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(0, $updatedFragment->getTags());
    }

    public function testRemoveTagsFromStatementFragment()
    {
        $this->loginTestUser();

        $fragmentFixture = $this->getStatementFragmentReference('testStatementFragment1');
        $newFragment = $this->sut->getStatementFragment($fragmentFixture->getId());
        $newFragmentId = $newFragment->getId();
        $topic = $this->getTagTopicReference('testFixtureTopic_1');
        $tags = $topic->getTags();
        $initialNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertEmpty($newFragment->getTags());

        // remove ONE tag (set 2 of 3):
        $data = ['id' => $newFragmentId, 'tags' => [$tags[1], $tags[2]]];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        $currentTags = $updatedFragment->getTags();
        static::assertCount(2, $currentTags);
        static::assertContains($tags[1], $currentTags);
        static::assertContains($tags[2], $currentTags);
        static::assertNotContains($tags[0], $currentTags);
        // test version
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 1, $currentNumberOfVersions);

        // remove all tags:
        $data = ['id' => $newFragmentId, 'tags' => []];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(0, $updatedFragment->getTags());
        // test version
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 2, $currentNumberOfVersions);
    }

    public function testAddTagsFromStatementFragment()
    {
        $this->loginTestUser();

        $fragmentFixture = $this->getStatementFragmentReference('testStatementFragment1');
        $newFragment = $this->sut->getStatementFragment($fragmentFixture->getId());
        $newFragmentId = $newFragment->getId();
        $topic = $this->getTagTopicReference('testFixtureTopic_1');
        $tags = $topic->getTags();
        static::assertNotEmpty($tags);
        $initialNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertEmpty($newFragment->getTags());

        $data = ['id' => $newFragmentId, 'tags' => [$tags[0], $tags[1]]];
        $result = $this->sut->updateStatementFragment($data);
        static::assertNotFalse($result);
        $updatedFragment = $this->sut->getStatementFragment($this->getStatementFragmentReference('testStatementFragment1')->getId());
        static::assertCount(2, $updatedFragment->getTags());
        static::assertContains($tags[0], $updatedFragment->getTags());
        static::assertContains($tags[1], $updatedFragment->getTags());
        // test version
        $currentNumberOfVersions = $this->countEntries(StatementFragmentVersion::class);
        static::assertSame($initialNumberOfVersions + 1, $currentNumberOfVersions);
    }

    public function testGetStatementFragmentsStatement()
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser();
        $data = [
            'text'        => 'First Fragment',
            'procedureId' => $this->getProcedureReference('testProcedure2')->getId(),
            'statementId' => $this->getStatementReference('testStatement')->getId(),
        ];
        $this->sut->createStatementFragmentIgnoreAssignment($data);
        $data2 = $data;
        $data2['text'] = 'Second Fragment';
        // If done too fast sorting would not work.
        sleep(1);
        $this->sut->createStatementFragmentIgnoreAssignment($data2);

        $statementId = $this->getStatementReference('testStatement')->getId();
        /** @var StatementFragment[] $fragments */
        $fragments = $this->sut->getStatementFragmentsStatement($statementId);
        $amount = $this->countEntries(StatementFragment::class, ['statement' => $data['statementId']]);

        static::assertCount($amount, $fragments);
        static::assertIsArray($fragments);
        static::assertInstanceOf(StatementFragment::class, $fragments[0]);
        // check sorting
        static::assertSame($data2['text'], $fragments[0]->getText());
        static::assertSame($data['text'], $fragments[1]->getText());
    }

    public function testGetStatementFragmentsListProcedureEmptyList()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->getProcedureReference('testProcedure4')->getId();
        /** @var StatementFragment[] $fragments */
        $fragments = $this->sut->getStatementFragmentsProcedure($procedureId)->getResult();
        static::assertCount(0, $fragments);
        static::assertIsArray($fragments);
    }

    public function testGetStatementFragmentsListProcedureList()
    {
        self::markSkippedForCIElasticsearchUnavailable();

        $procedureId = $this->getProcedureReference('testProcedure')->getId();
        $result = $this->sut->getStatementFragmentsProcedure($procedureId);
        static::assertIsArray($result->getResult());
        $fragments = $result->getResult();
        $amount = $this->countEntries(StatementFragment::class, ['procedure' => $procedureId]);
        static::assertCount($amount, $fragments);
        foreach ($fragments as $fragment) {
            static::assertIsArray($fragment);
            static::assertEquals($procedureId, $fragment['procedureId']);
        }
    }

    public function testCreateStatementFragment()
    {
        self::markSkippedForCIIntervention();

        $this->loginTestUser();

        $relatedStatement = $this->getStatementReference('testStatement');
        $data = [
            'text'        => $this->statementFragmentHtmlText,
            'procedureId' => $this->getProcedureReference('testProcedure2')->getId(),
            'statementId' => $relatedStatement->getId(),
        ];

        // first new Statement
        $statementFragment = $this->sut->createStatementFragment($data);
        static::assertInstanceOf(StatementFragment::class, $statementFragment);
        static::assertSame('00001', $statementFragment->getDisplayId());
        static::assertSame($data['text'], $statementFragment->getText());
        static::assertInstanceOf(Statement::class, $statementFragment->getStatement());
        static::assertInstanceOf(Procedure::class, $statementFragment->getProcedure());

        $versions = $statementFragment->getVersions()->getValues();
        static::assertIsArray($versions);

        /** @var StatementFragmentVersion $lastVersion */
        $lastVersion = $versions[0];
        static::assertInstanceOf(StatementFragmentVersion::class, $lastVersion);
        static::assertSame($statementFragment->getText(), $lastVersion->getText());
        static::assertSame(
            $statementFragment->getProcedureId(),
            $lastVersion->getProcedure()->getId()
        );
        static::assertEquals($statementFragment, $lastVersion->getStatementFragment());

        // should have the counties, municipalities and priorityAreas of the relatedStatement
        static::assertEquals($relatedStatement->getCounties(), $relatedStatement->getCounties());
        static::assertEquals($relatedStatement->getMunicipalities(), $relatedStatement->getMunicipalities());
        static::assertEquals($relatedStatement->getPriorityAreas(), $relatedStatement->getPriorityAreas());
    }

    public function testCreateStatementFragmentMissingProcedure()
    {
        $data = [
            'text'        => $this->statementFragmentHtmlText,
            'statementId' => $this->getStatementReference('testStatement')->getId(),
        ];

        $statementFragment = $this->sut->createStatementFragment($data);
        static::assertNull($statementFragment);
    }

    public function testCreateStatementFragmentMissingStatement()
    {
        $data = [
            'text'        => $this->statementFragmentHtmlText,
            'procedureId' => $this->getProcedureReference('testProcedure2')->getId(),
        ];

        $statementFragment = $this->sut->createStatementFragment($data);
        static::assertNull($statementFragment);
    }

    public function testCreateStatementFragmentMissingText()
    {
        $data = [
            'statementId' => $this->getStatementReference('testStatement')->getId(),
            'procedureId' => $this->getProcedureReference('testProcedure2')->getId(),
        ];

        $statementFragment = $this->sut->createStatementFragment($data);
        static::assertNull($statementFragment);
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
}
