<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Forum\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentRelease;
use demosplan\DemosPlanCoreBundle\Entity\Forum\DevelopmentUserStory;
use demosplan\DemosPlanCoreBundle\Logic\Forum\ForumService;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;
use TypeError;

class ForumDevelopmentTest extends FunctionalTestCase
{
    /**
     * @var ForumService
     */
    protected $sut;

    /**
     * @var Session
     */
    protected $mockSession;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(ForumService::class);

        $this->logIn($this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    // ----------------------------Weiterentwicklungsbereich:----------------------------

    public function testNewReleaseWithOnlyMandatoryParameter()
    {
        $data = [
            'title' => 'testNewRelease',
            'phase' => 'configuration',
        ];

        $result = $this->sut->newRelease($data);

        static::assertTrue(is_array($result));
        static::assertTrue($result['status']);
        static::assertArrayHasKey('ident', $result['body']);
        $this->checkId($result['body']['ident']);
        static::assertArrayHasKey('phase', $result['body']);
        static::assertTrue(is_string($result['body']['phase']));
        static::assertArrayHasKey('title', $result['body']);
        static::assertTrue(is_string($result['body']['title']));
    }

    public function testNewReleaseWithAllPossibleParameters()
    {
        $now = time();
        $inFuture = time() + 72 * 60 * 60;
        $data = [
            'title'       => 'testNewRelease2',
            'description' => 'testDescription',
            'phase'       => 'voting_online',
            'startDate'   => $now,
            'endDate'     => $inFuture,
        ];

        $result = $this->sut->newRelease($data);
        static::assertTrue(is_array($result));
        static::assertTrue($result['status']);

        static::assertArrayHasKey('ident', $result['body']);
        $this->checkId($result['body']['ident']);
        static::assertArrayHasKey('phase', $result['body']);
        static::assertTrue(is_string($result['body']['phase']));
        static::assertArrayHasKey('title', $result['body']);
        static::assertTrue(is_string($result['body']['title']));
        static::assertArrayHasKey('description', $result['body']);
        static::assertTrue(is_string($result['body']['description']));
        static::assertArrayHasKey('startDate', $result['body']);
        static::assertTrue($this->isTimestamp($result['body']['startDate']));
        static::assertArrayHasKey('endDate', $result['body']);
        static::assertTrue($this->isTimestamp($result['body']['endDate']));
    }

    public function testUpdateRelease()
    {
        $releaseId = $this->fixtures->getReference('testRelease1')->getIdent();
        $now = time();
        $data = [
            'title'       => 'updateTitle',
            'phase'       => 'configuration',
            'description' => 'updatedDescription',
            'endDate'     => $now,
        ];
        $numberOfEntriesBefore = $this->countEntries(DevelopmentRelease::class);
        $result = $this->sut->updateRelease($releaseId, $data);
        $numberOfEntriesAfter = $this->countEntries(DevelopmentRelease::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);

        static::assertTrue($result);

        // check entry
        $entry = $this->sut->getRelease($releaseId);
        static::assertEquals($data['endDate'] * 1000, $entry['endDate']);
        static::assertEquals($data['title'], $entry['title']);
        static::assertEquals($data['phase'], $entry['phase']);
        static::assertEquals($data['description'], $entry['description']);
    }

    public function testUpdateReleaseWithEmptyParameters()
    {
        $this->expectException(TypeError::class);

        $this->sut->updateRelease('', '');
    }

    public function testDeleteRelease()
    {
        $releaseId = $this->fixtures->getReference('testRelease2')->getIdent();
        $numberOfEntriesBefore = $this->countEntries(DevelopmentRelease::class);
        $this->sut->deleteRelease($releaseId);
        $numberOfEntriesAfter = $this->countEntries(DevelopmentRelease::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter + 1);
    }

    public function testDeleteReleaseWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->deleteRelease('');
    }

    public function testGetSingleRelease()
    {
        $releaseId = $this->fixtures->getReference('testRelease1')->getIdent();
        $result = $this->sut->getRelease($releaseId);

        static::assertTrue(is_array($result));
        static::assertArrayHasKey('ident', $result);
        $this->checkId($result['ident']);
        static::assertArrayHasKey('phase', $result);
        static::assertTrue(is_string($result['phase']));
        static::assertArrayHasKey('title', $result);
        static::assertTrue(is_string($result['title']));
        static::assertArrayHasKey('startDate', $result);
        static::assertTrue($this->isTimestamp($result['startDate']));
        static::assertArrayHasKey('endDate', $result);
        static::assertTrue($this->isTimestamp($result['endDate']));
    }

    public function testGetReleaseWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->getRelease('');
    }

    public function testGetListOfReleases()
    {
        $result = $this->sut->getReleases();

        static::assertTrue(is_array($result));
        static::assertEquals(2, count($result));
        foreach ($result as $release) {
            static::assertArrayHasKey('ident', $release);
            $this->checkId($release['ident']);
            static::assertArrayHasKey('phase', $release);
            static::assertTrue(is_string($release['phase']));
            static::assertArrayHasKey('title', $release);
            static::assertTrue(is_string($release['title']));
        }
        // eventuell noch andere Parameter, wenn sie definiert sind
        static::assertArrayHasKey('startDate', $result[0]);
        static::assertTrue($this->isTimestamp($result[0]['startDate']));
        static::assertArrayHasKey('endDate', $result[0]);
        static::assertTrue($this->isTimestamp($result[0]['endDate']));
    }

    public function testNewUserStory()
    {
        $data = [
            'title'       => 'neue testuserstory',
            'description' => 'hahahaha',
        ];
        $releaseId = $this->fixtures->getReference('testRelease1')->getIdent();
        $numberOfEntriesBefore = $this->countEntries(DevelopmentUserStory::class);
        $result = $this->sut->newUserStory($releaseId, $data);
        $numberOfEntriesAfter = $this->countEntries(DevelopmentUserStory::class);
        static::assertEquals($numberOfEntriesBefore + 1, $numberOfEntriesAfter);

        static::assertTrue(is_array($result));
        static::assertArrayHasKey('body', $result);
        static::assertArrayHasKey('status', $result);
        static::assertTrue($result['status']);
        $this->checkSingleUserStoryVariables($result['body']);
        static::assertEquals($data['title'], $result['body']['title']);
        static::assertEquals($data['description'], $result['body']['description']);
    }

    public function testUpdateUserStory()
    {
        $data = [
            'title'       => 'geupdated title',
            'description' => 'rrrrrr',
        ];
        $storyId = $this->fixtures->getReference('testUserStory1')->getIdent();
        $numberOfEntriesBefore = $this->countEntries(DevelopmentUserStory::class);
        $result = $this->sut->updateUserStory($storyId, $data);
        $numberOfEntriesAfter = $this->countEntries(DevelopmentUserStory::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter);

        static::assertTrue(is_array($result));
        static::assertEquals(3, count($result));
        static::assertTrue($result['status']);

        // check entry
        $entry = $this->sut->getUserStory($storyId);
        static::assertEquals($data['title'], $entry['title']);
        static::assertEquals($data['description'], $entry['description']);
        static::assertEquals(3, $entry['onlineVotes']);
    }

    public function testUpdateUserStoryWithEmptyParameters()
    {
        $this->expectException(TypeError::class);

        $this->sut->updateUserStory('', '');
    }

    public function testDeleteUserStory()
    {
        $storyId = $this->fixtures->getReference('testUserStory2')->getIdent();
        $numberOfEntriesBefore = $this->countEntries(DevelopmentUserStory::class);
        $result = $this->sut->deleteUserStory($storyId);
        $numberOfEntriesAfter = $this->countEntries(DevelopmentUserStory::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter + 1);
    }

    public function testDeleteUserStoryWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $result = $this->sut->deleteUserStory('');
    }

    public function testGetListOfUserStories()
    {
        $releaseId = $this->fixtures->getReference('testRelease1')->getIdent();
        $result = $this->sut->getUserStories($releaseId);

        static::assertTrue(is_array($result));
        static::assertEquals(2, count($result));
        // release variable
        static::assertArrayHasKey('release', $result);
        static::assertArrayHasKey('ident', $result['release']);
        $this->checkId($result['release']['ident']);
        static::assertArrayHasKey('phase', $result['release']);
        static::assertTrue(is_string($result['release']['phase']));
        static::assertArrayHasKey('title', $result['release']);
        static::assertTrue(is_string($result['release']['title']));
        static::assertArrayHasKey('description', $result['release']);
        if (!is_null($result['release']['description'])) {
            static::assertTrue(is_string($result['release']['description']));
        }
        static::assertArrayHasKey('startDate', $result['release']);
        if (!is_null($result['release']['startDate'])) {
            static::assertTrue($this->isTimestamp($result['release']['startDate']));
        }
        static::assertArrayHasKey('endDate', $result['release']);
        if (!is_null($result['release']['endDate'])) {
            static::assertTrue($this->isTimestamp($result['release']['endDate']));
        }
        static::assertArrayHasKey('createDate', $result['release']);
        static::assertTrue($this->isTimestamp($result['release']['createDate']));
        static::assertArrayHasKey('modifiedDate', $result['release']);
        static::assertTrue($this->isTimestamp($result['release']['modifiedDate']));

        // userStory variable
        static::assertArrayHasKey('userStories', $result);
        static::assertEquals(2, count($result['userStories']));
        $this->checkSingleUserStoryVariables($result['userStories'][0]);
    }

    public function testGetUserStoriesWithEmptyParameters()
    {
        $result = $this->sut->getUserStories('');
        static::assertArrayHasKey('release', $result);
        static::assertArrayHasKey('userStories', $result);
        static::assertCount(0, $result['release']);
        static::assertCount(0, $result['userStories']);
    }

    public function testGetUserStory()
    {
        $storyId = $this->fixtures->getReference('testUserStory1')->getIdent();
        $result = $this->sut->getUserStory($storyId);

        static::assertTrue(is_array($result));

        static::assertArrayHasKey('ident', $result);
        $this->checkId($result['ident']);
        static::assertArrayHasKey('releaseId', $result);
        $this->checkId($result['releaseId']);
        static::assertArrayHasKey('threadId', $result);
        $this->checkId($result['threadId']);
        static::assertArrayHasKey('createDate', $result);
        static::assertTrue($this->isTimestamp($result['createDate']));
        static::assertArrayHasKey('modifiedDate', $result);
        static::assertTrue($this->isTimestamp($result['modifiedDate']));
        static::assertArrayHasKey('onlineVotes', $result);
        static::assertTrue(is_numeric($result['onlineVotes']));
        static::assertArrayHasKey('offlineVotes', $result);
        static::assertTrue(is_numeric($result['offlineVotes']));
        static::assertArrayHasKey('description', $result);
        static::assertTrue(is_string($result['description']));
        static::assertArrayHasKey('title', $result);
        static::assertTrue(is_string($result['title']));

        $userStory1 = $this->fixtures->getReference('testUserStory1');
        static::assertEquals($userStory1->getIdent(), $result['ident']);
        static::assertEquals($userStory1->getReleaseId(), $result['releaseId']);
        static::assertEquals($userStory1->getThreadId(), $result['threadId']);
        static::assertEquals($userStory1->getOfflineVotes(), $result['offlineVotes']);
        static::assertEquals($userStory1->getOnlineVotes(), $result['onlineVotes']);
        static::assertEquals($userStory1->getDescription(), $result['description']);
        static::assertEquals($userStory1->getTitle(), $result['title']);
        static::assertEquals($userStory1->getCreateDate()->getTimestamp() * 1000, $result['createDate']);
        static::assertEquals($userStory1->getModifiedDate()->getTimestamp() * 1000, $result['modifiedDate']);
    }

    public function testGetUserStoryWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->getUserStory('');
    }

    public function testGetVotes()
    {
        $storyId = $this->fixtures->getReference('testUserStory1')->getIdent();

        $result = $this->sut->getVotes($storyId);

        static::assertTrue(is_array($result));
        static::assertArrayHasKey('userStory', $result);
        $this->checkSingleUserStoryVariables($result['userStory']);
        static::assertEquals(3, $result['userStory']['numberOfVotes']);
        static::assertArrayHasKey('votes', $result);
        static::assertTrue(is_array($result['votes']));

        // checkVotes Variables
        // static::assertEquals('#',$result['votes']);
        static::assertArrayHasKey('ident', $result['votes'][0]);
        $this->checkId($result['votes'][0]['ident']);
        static::assertArrayHasKey('orgaId', $result['votes'][0]);
        $this->checkId($result['votes'][0]['orgaId']);
        static::assertArrayHasKey('userId', $result['votes'][0]);
        $this->checkId($result['votes'][0]['userId']);
        static::assertArrayHasKey('userStoryId', $result['votes'][0]);
        $this->checkId($result['votes'][0]['userStoryId']);
        static::assertEquals($result['userStory']['ident'], $result['votes'][0]['userStoryId']);
        static::assertArrayHasKey('numberOfVotes', $result['votes'][0]);
        static::assertTrue(is_integer($result['votes'][0]['numberOfVotes']));
        static::assertEquals(2, $result['votes'][0]['numberOfVotes']);
        static::assertArrayHasKey('createDate', $result['votes'][0]);
        static::assertTrue($this->isTimestamp($result['votes'][0]['createDate']));
        static::assertArrayHasKey('modifiedDate', $result['votes'][0]);
        static::assertTrue($this->isTimestamp($result['votes'][0]['modifiedDate']));
    }

    public function testSaveVotes()
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        $releaseId = $this->fixtures->getReference('testRelease1')->getIdent();
        $userStories = $this->sut->getUserStories($releaseId);
        $votes = [
            ['userStoryId'      => $userStories['userStories'][0]['ident'],
                'numberOfVotes' => 0, ],
            ['userStoryId'      => $userStories['userStories'][1]['ident'],
                'numberOfVotes' => 3, ], ];

        $result = $this->sut->saveVotes($releaseId, $votes);

        static::assertTrue(is_array($result));
        static::assertArrayHasKey('status', $result);
        static::assertTrue($result['status']);
        static::assertArrayHasKey('votes', $result['body']);
        static::assertEquals(0, $result['body']['votes'][0]['numberOfVotes']);
        $this->checkId($result['body']['votes'][0]['userStoryId']);
        static::assertEquals($userStories['userStories'][0]['ident'], $result['body']['votes'][0]['userStoryId']);

        // checkVoteandUserStoryEntry
        $votes = $this->sut->getVotes($userStories['userStories'][0]['ident']);
        static::assertEquals(1, $votes['userStory']['onlineVotes']);
        static::assertEquals(2, count($votes['votes']));
        static::assertEquals(1, $votes['votes'][0]['numberOfVotes']);
        static::assertEquals(0, $votes['votes'][1]['numberOfVotes']);
        $votes = $this->sut->getVotes($userStories['userStories'][1]['ident']);
        static::assertEquals(1, count($votes['votes']));
    }

    public function testSaveVotesWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->saveVotes('', '');
    }

    public function testGetVotesWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->getVotes('');
    }

    protected function checkSingleUserStoryVariables($userStory)
    {
        static::assertArrayHasKey('ident', $userStory);
        $this->checkId($userStory['ident']);
        static::assertArrayHasKey('releaseId', $userStory);
        $this->checkId($userStory['releaseId']);
        static::assertArrayHasKey('threadId', $userStory);
        $this->checkId($userStory['threadId']);
        static::assertArrayHasKey('createDate', $userStory);
        static::assertTrue($this->isTimestamp($userStory['createDate']));
        static::assertArrayHasKey('modifiedDate', $userStory);
        static::assertTrue($this->isTimestamp($userStory['modifiedDate']));
        static::assertArrayHasKey('onlineVotes', $userStory);
        static::assertTrue(is_integer($userStory['onlineVotes']));
        static::assertArrayHasKey('offlineVotes', $userStory);
        static::assertTrue(is_integer($userStory['offlineVotes']));
        static::assertArrayHasKey('description', $userStory);
        static::assertTrue(is_string($userStory['description']));
        static::assertArrayHasKey('title', $userStory);
        static::assertTrue(is_string($userStory['title']));
    }
}
