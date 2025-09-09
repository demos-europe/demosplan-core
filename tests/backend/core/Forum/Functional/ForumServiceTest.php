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
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntry;
use demosplan\DemosPlanCoreBundle\Entity\Forum\ForumEntryFile;
use demosplan\DemosPlanCoreBundle\Logic\Forum\ForumService;
use Exception;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class ForumServiceTest extends FunctionalTestCase
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

        $this->sut = self::getContainer()->get(ForumService::class);

        $this->logIn($this->fixtures->getReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY));
    }

    public function testGetThreadEntryList()
    {
        $threadId = $this->fixtures->getReference('testForumThread1')->getIdent();
        $result = $this->sut->getThreadEntryList($threadId);

        // check return value
        static::assertTrue(is_array($result));
        static::assertEquals(2, count($result));
        static::assertArrayHasKey('ident', $result['thread']);
        $this->checkId($result['thread']['ident']);
        static::assertArrayHasKey('closed', $result['thread']);
        static::assertTrue(is_bool($result['thread']['closed']));
        static::assertArrayHasKey('progression', $result['thread']);
        static::assertTrue(is_bool($result['thread']['progression']));
        static::assertArrayHasKey('numberOfEntries', $result['thread']);
        static::assertTrue(is_integer($result['thread']['numberOfEntries']));
        static::assertArrayHasKey('recentActivity', $result['thread']);
        static::assertTrue($this->isTimestamp($result['thread']['recentActivity']));
        static::assertArrayHasKey('entryList', $result);
        static::assertEquals(1, count($result['entryList']));
        static::assertTrue(is_array($result['entryList']));

        // zusaätzliche items
        static::assertArrayHasKey('userRoles', $result['entryList'][0]);
        static::assertTrue(is_string($result['entryList'][0]['userRoles']));
        static::assertArrayHasKey('threadClosed', $result['entryList'][0]);
        static::assertTrue(is_bool($result['entryList'][0]['threadClosed']));
    }

    public function testGetThreadEntryListWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->getThreadEntryList('');
    }

    public function testUpdateThreadEntry()
    {
        $threadEntry = $this->fixtures->getReference('testForumEntry1');
        $data['text'] = 'neuer text';
        $data['files'] = ['food-q-c-640-480-8.jpg:d411cd59-f8e6-49a6-a457-dba43664b738:29533:image/jpeg'];
        $numberOfEntriesBefore = $this->countEntries(ForumEntry::class);
        $result = $this->sut->updateThreadEntry($threadEntry->getIdent(), $data);
        $numberOfEntriesAfter = $this->countEntries(ForumEntry::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore);

        // check return value
        static::assertTrue($result['status']);
        static::assertArrayHasKey('body', $result);

        static::assertArrayHasKey('ident', $result['body']);
        static::assertArrayHasKey('createDate', $result['body']);
        static::assertTrue($this->isCurrentTimestamp($result['body']['createDate']));
        static::assertArrayHasKey('modifiedDate', $result['body']);
        static::assertTrue($this->isCurrentTimestamp($result['body']['modifiedDate']));
        static::assertArrayHasKey('text', $result['body']);
        static::assertEquals($data['text'], $result['body']['text']);
        static::assertArrayHasKey('threadId', $result['body']);
        static::assertTrue(is_string($result['body']['threadId']));
        static::assertArrayHasKey('initialEntry', $result['body']);
        static::assertTrue(is_bool($result['body']['initialEntry']));
        static::assertEquals($threadEntry->isInitialEntry(), $result['body']['initialEntry']);
        static::assertArrayHasKey('threadClosed', $result['body']);
        static::assertTrue(is_bool($result['body']['threadClosed']));
        static::assertEquals($threadEntry->isThreadClosed(), $result['body']['threadClosed']);

        static::assertArrayHasKey('user', $result['body']);
        static::assertTrue(is_array($result['body']['user']));
        static::assertArrayHasKey('files', $result['body']);
        static::assertTrue(is_array($result['body']['files']));
        static::assertEquals($data['files'], $result['body']['files']);

        // zusätzliche items
        static::assertArrayHasKey('userRoles', $result['body']);
        static::assertTrue(is_string($result['body']['userRoles']));
        static::assertEquals($threadEntry->getUserRoles(), $result['body']['userRoles']);
    }

    public function testUpdateThreadEntryForAnonymising()
    {
        self::markSkippedForCIIntervention();

        $threadEntry = $this->fixtures->getReference('testForumEntry1');
        $data['text'] = 'Moderator';
        $data['anonymise'] = true;
        $numberOfEntriesBefore = $this->countEntries(ForumEntry::class);
        $result = $this->sut->updateThreadEntry($threadEntry->getIdent(), $data);
        $numberOfEntriesAfter = $this->countEntries(ForumEntry::class);
        static::assertEquals($numberOfEntriesAfter, $numberOfEntriesBefore);

        // check return value
        static::assertTrue($result['status']);
        static::assertArrayHasKey('body', $result);
        static::assertArrayHasKey('ident', $result['body']);
        $this->checkId($result['body']['ident']);
        static::assertArrayHasKey('createDate', $result['body']);
        static::assertTrue($this->isTimestamp($result['body']['createDate']));
        static::assertArrayHasKey('modifiedDate', $result['body']);
        static::assertTrue($this->isTimestamp($result['body']['modifiedDate']));
        static::assertArrayHasKey('text', $result['body']);
        static::assertTrue(is_string($result['body']['text']));
        static::assertArrayHasKey('threadId', $result['body']);
        $this->checkId($result['body']['threadId']);
        static::assertArrayHasKey('userRoles', $result['body']);
        static::assertTrue(is_string($result['body']['userRoles']));
        static::assertArrayHasKey('threadClosed', $result['body']);
        static::assertTrue(is_bool($result['body']['threadClosed']));

        // check content
        static::assertEquals($data['text'], $result['body']['text']);
        static::assertEquals(0, count($result['body']['files']));
        static::assertEquals($result['body']['userRoles'], $threadEntry->getUserRoles());
    }

    public function testUpdateThreadEntryWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->updateThreadEntry('', []);
    }

    public function testGetThreadEntry()
    {
        $threadEntryId = $this->fixtures->getReference('testForumEntry1')->getIdent();

        $result = $this->sut->getThreadEntry($threadEntryId);

        static::assertTrue(is_array($result));
        $this->checkSingleThreadEntryVariables($result);

        // zusätzliche items
        static::assertArrayHasKey('userRoles', $result);
        static::assertTrue(is_string($result['userRoles']));
        static::assertArrayHasKey('threadClosed', $result);
        static::assertTrue(is_bool($result['threadClosed']));
    }

    public function testGetThreadEntryWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->getThreadEntry('');
    }

    public function testGetThread()
    {
        self::markSkippedForCIIntervention();

        $threadId = $this->fixtures->getReference('testForumThread1')->getIdent();
        $result = $this->sut->getThread($threadId);

        static::assertTrue(is_array($result));
        static::assertArrayHasKey('ident', $result);
        $this->checkId($result['ident']);
        static::assertArrayHasKey('closed', $result);
        static::assertTrue(is_bool($result['closed']));
        static::assertArrayHasKey('progression', $result);
        static::assertTrue(is_bool($result['progression']));
        static::assertArrayHasKey('numberOfEntries', $result);
        static::assertTrue(is_integer($result['numberOfEntries']));
        static::assertArrayHasKey('recentActivity', $result);
        static::assertTrue($this->isTimestamp($result['recentActivity']));
        static::assertArrayHasKey('starterEntry', $result);
        static::assertTrue(is_array($result['starterEntry']));
        static::assertArrayHasKey('ident', $result['starterEntry']);
        static::assertArrayHasKey('threadId', $result['starterEntry']);
        static::assertEquals($threadId, $result['starterEntry']['threadId']);
        static::assertArrayHasKey('thread', $result['starterEntry']);
        static::assertArrayHasKey('ident', $result['starterEntry']);
        static::assertArrayHasKey('user', $result['starterEntry']);
        static::assertArrayHasKey('userRoles', $result['starterEntry']);
        static::assertArrayHasKey('text', $result['starterEntry']);
        static::assertArrayHasKey('initialEntry', $result['starterEntry']);
        static::assertTrue($result['starterEntry']['initialEntry']);
        static::assertArrayHasKey('createDate', $result['starterEntry']);
        static::assertArrayHasKey('modifyDate', $result['starterEntry']);
        static::assertArrayHasKey('files', $result['starterEntry']);
        static::assertArrayHasKey('threadClosed', $result['starterEntry']);
        static::assertArrayHasKey('userStory', $result['starterEntry']);
        static::assertTrue(is_string($result['starterEntry']['userRoles']));
    }

    public function testGetThreadWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->getThread('');
    }

    public function testDeleteForumFileWithEmptyParameters()
    {
        $this->expectException(Exception::class);

        $this->sut->deleteForumFile('');
    }

    protected function checkSingleThreadEntryVariables($entry)
    {
        static::assertArrayHasKey('ident', $entry);
        $this->checkId($entry['ident']);
        static::assertArrayHasKey('createDate', $entry);
        static::assertTrue($this->isTimestamp($entry['createDate']));
        static::assertArrayHasKey('modifiedDate', $entry);
        static::assertTrue($this->isTimestamp($entry['modifiedDate']));
        static::assertArrayHasKey('text', $entry);
        static::assertTrue(is_string($entry['text']));
        static::assertArrayHasKey('threadId', $entry);
        $this->checkId($entry['threadId']);
        // user
        static::assertArrayHasKey('user', $entry);
        static::assertArrayHasKey('ident', $entry['user']);
        $this->checkId($entry['user']['ident']);
        static::assertArrayHasKey('ufirstname', $entry['user']);
        static::assertTrue(is_string($entry['user']['ufirstname']));
        static::assertArrayHasKey('ulastname', $entry['user']);
        static::assertTrue(is_string($entry['user']['ulastname']));
        // files
        static::assertArrayHasKey('files', $entry);
        static::assertTrue(is_array($entry['files']));
        if (0 < count($entry['files'])) {
            static::assertTrue(is_string($entry['files'][0]));
        }
        static::assertArrayHasKey('initialEntry', $entry);
        static::assertTrue($entry['initialEntry']);
    }

    protected function checkSingleTopicVariablesLong($topic)
    {
        static::assertArrayHasKey('ident', $topic);
        $this->checkId($topic['ident']);
        static::assertArrayHasKey('url', $topic);
        static::assertTrue(is_string($topic['url']));
        static::assertArrayHasKey('title', $topic);
        static::assertTrue(is_string($topic['title']));
        static::assertArrayHasKey('description', $topic);
        static::assertTrue(is_string($topic['description']));
        static::assertArrayHasKey('forumId', $topic);
        $this->checkId($topic['forumId']);
        static::assertArrayHasKey('createDate', $topic);
        static::assertTrue($this->isTimestamp($topic['createDate']));
        static::assertArrayHasKey('modifiedDate', $topic);
        static::assertTrue($this->isTimestamp($topic['modifiedDate']));
    }

    protected function checkSingleTopicVariablesShort($topic)
    {
        static::assertArrayHasKey('ident', $topic);
        $this->checkId($topic['ident']);
        static::assertArrayHasKey('url', $topic);
        static::assertTrue(is_string($topic['url']));
        static::assertArrayHasKey('title', $topic);
        static::assertTrue(is_string($topic['title']));
        static::assertArrayHasKey('description', $topic);
        static::assertTrue(is_string($topic['description']));
        static::assertArrayHasKey('forumId', $topic);
        $this->checkId($topic['forumId']);
    }

    protected function checkSingleForumVariablesShort($forum)
    {
        static::assertArrayHasKey('ident', $forum);
        $this->checkId($forum['ident']);
        static::assertArrayHasKey('url', $forum);
        static::assertTrue(is_string($forum['url']));
        static::assertArrayHasKey('title', $forum);
        static::assertTrue(is_string($forum['title']));
        static::assertArrayHasKey('text', $forum);
        static::assertTrue(is_string($forum['text']));
        static::assertArrayHasKey('createDate', $forum);
        static::assertTrue($this->isTimestamp($forum['createDate']));
        static::assertArrayHasKey('modifiedDate', $forum);
        static::assertTrue($this->isTimestamp($forum['modifiedDate']));
    }

    protected function checkSingleForumVariablesLong($forum)
    {
        static::assertArrayHasKey('ident', $forum);
        $this->checkId($forum['ident']);
        static::assertArrayHasKey('url', $forum);
        static::assertTrue(is_string($forum['url']));
        static::assertArrayHasKey('title', $forum);
        static::assertTrue(is_string($forum['title']));
        static::assertArrayHasKey('text', $forum);
        static::assertTrue(is_string($forum['text']));
        static::assertArrayHasKey('createDate', $forum);
        static::assertTrue($this->isTimestamp($forum['createDate']));
        static::assertArrayHasKey('modifiedDate', $forum);
        static::assertTrue($this->isTimestamp($forum['modifiedDate']));
    }

    protected function checkSingleForumEntryVariables($forumEntry)
    {
        static::assertArrayHasKey('ident', $forumEntry);
        $this->checkId($forumEntry['ident']);
        static::assertArrayHasKey('forumTitle', $forumEntry);
        static::assertTrue(is_string($forumEntry['forumTitle']));
        static::assertArrayHasKey('forumUrl', $forumEntry);
        static::assertTrue(is_string($forumEntry['forumUrl']));
        static::assertArrayHasKey('topicTitle', $forumEntry);
        static::assertTrue(is_string($forumEntry['topicTitle']));
        static::assertArrayHasKey('topicUrl', $forumEntry);
        static::assertTrue(is_string($forumEntry['topicUrl']));
        static::assertArrayHasKey('threadId', $forumEntry);
        $this->checkId($forumEntry['threadId']);
        static::assertArrayHasKey('userFirstname', $forumEntry);
        static::assertTrue(is_string($forumEntry['userFirstname']));
        static::assertArrayHasKey('userLastname', $forumEntry);
        static::assertTrue(is_string($forumEntry['userLastname']));
        static::assertArrayHasKey('text', $forumEntry);
        static::assertTrue(is_string($forumEntry['text']));
        static::assertArrayHasKey('createDate', $forumEntry);
        static::assertTrue($this->isTimestamp($forumEntry['createDate']));
        static::assertArrayHasKey('fileList', $forumEntry);
        static::assertTrue(is_array($forumEntry['fileList']));
        if (0 < count($forumEntry['fileList'])) {
            static::assertTrue(is_string($forumEntry['fileList'][0]));
        }
    }

    public function testDeleteForumFile()
    {
        $fileHash = $this->fixtures->getReference('testForumEntryFile1')->getHash();
        $numberOfEntriesBefore = $this->countEntries(ForumEntryFile::class);
        $result = $this->sut->deleteForumFile($fileHash);
        $numberOfEntriesAfter = $this->countEntries(ForumEntryFile::class);
        static::assertEquals($numberOfEntriesBefore, $numberOfEntriesAfter + 1);

        // check if entry has no file anymore
        $threadEntry = $this->sut->getThreadEntry($this->fixtures->getReference('testForumEntry2')->getIdent());
        static::assertEquals(0, count($threadEntry['files']));
    }
}
