<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Statement\DraftStatement;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class TagServiceTest extends FunctionalTestCase
{
    use RefreshElasticsearchIndexTrait;
    /**
     * @var TagService
     */
    protected $sut;

    /**
     * @var DraftStatement
     */
    protected $testDraftStatement;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(TagService::class);
        $this->testDraftStatement = $this->getDraftStatementReference('testDraftStatement');

        $this->setElasticsearchIndexManager(self::$container->get('fos_elastica.index_manager'));
    }

    public function testCreateTag()
    {
        $testTopic = $this->getTagTopicReference('testFixtureTopic_1');
        $title = 'ersterTag';

        $tag = $this->sut->createTag($title, $testTopic);

        $result = $this->sut->getTag($tag->getId());
        static::assertNotNull($result);
        static::assertInstanceOf(Tag::class, $result);

        static::assertSame($title, $result->getTitle());
        static::assertEquals($testTopic, $result->getTopic());
        static::assertNotNull($result->getId());
    }

    public function testCreateTopic()
    {
        $title = 'erstesTopic';
        $testProcedure = $this->getProcedureReference('testProcedure4');
        $topic = $this->sut->createTagTopic($title, $testProcedure);

        $result = $this->sut->getTopic($topic->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(TagTopic::class, $result);
        static::assertSame($title, $result->getTitle());
        static::assertNotNull($result->getId());
        static::assertEmpty($result->getTags());

        $testTag = $this->getTagReference('testFixtureTag_1');
        $moved = $this->sut->moveTagToTopic($testTag, $topic);
        static::assertTrue($moved);

        $result = $this->sut->getTopic($topic->getId());

        static::assertNotEmpty($result->getTags());
        static::assertContains($testTag, $result->getTags());
    }

    public function testDuplicatedTopic()
    {
        $this->expectException(DuplicatedTagTopicTitleException::class);
        $title = 'erstesTopic';
        $testProcedure = $this->getProcedureReference('testProcedure4');
        $this->sut->createTagTopic($title, $testProcedure);
        $this->sut->createTagTopic($title, $testProcedure);
    }

    public function testMoveTagToTopic()
    {
        $testProcedure = $this->getProcedureReference('testProcedure4');
        $topic1 = $this->sut->createTagTopic('Topic1', $testProcedure);
        $topic2 = $this->sut->createTagTopic('Topic2', $testProcedure);
        $tag1 = $this->sut->createTag('newTag1', $topic1);
        $tag2 = $this->sut->createTag('newTag2', $topic1);

        $topic1 = $this->sut->getTopic($topic1->getId());
        static::assertEquals(2, $topic1->getTags()->count());
        static::assertEquals($topic1, $tag1->getTopic());
        static::assertEquals($topic1, $tag2->getTopic());

        $topic2 = $this->sut->getTopic($topic2->getId());
        $moved = $this->sut->moveTagToTopic($tag2, $topic2);
        static::assertTrue($moved);
        static::assertEquals(1, $topic2->getTags()->count());
        static::assertContains($tag2, $topic2->getTags());
        static::assertEquals($tag2, $topic2->getTags()[0]);

        $topic1 = $this->sut->getTopic($topic1->getId());
        static::assertEquals(1, $topic1->getTags()->count());
        static::assertContains($tag1, $topic1->getTags());
        static::assertEquals($tag1, $topic1->getTags()[0]);
    }

    public function testGetTag()
    {
        $testTag1 = $this->getTagReference('testFixtureTag_1');
        $result = $this->sut->getTag($testTag1->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(Tag::class, $result);
        static::assertSame($testTag1->getTitle(), $result->getTitle());
        static::assertEquals($testTag1->getTopic(), $result->getTopic());
    }

    public function testGetTopic()
    {
        $testTopic = $this->getTagTopicReference('testFixtureTopic_1');
        $result = $this->sut->getTopic($testTopic->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(TagTopic::class, $result);
        static::assertSame($testTopic->getTitle(), $result->getTitle());
        static::assertEquals($testTopic->getTags(), $result->getTags());
    }

    public function testAttachTagsAndTopic()
    {
        $testProcedure = $this->getProcedureReference('testProcedure4');
        $topic = $this->sut->createTagTopic('filledTopic', $testProcedure);
        $initialTopic = $this->sut->createTagTopic('initialTopic', $testProcedure);
        $tag2 = $this->sut->createTag('tagToFillInTopic2', $initialTopic);
        $tag3 = $this->sut->createTag('tagToFillInTopic3', $initialTopic);
        $tag4 = $this->sut->createTag('tagToFillInTopic4', $initialTopic);

        $initialTopic = $this->sut->getTopic($initialTopic->getId());
        static::assertEquals(3, $initialTopic->getTags()->count());
        static::assertContains($tag2, $initialTopic->getTags());
        static::assertContains($tag3, $initialTopic->getTags());
        static::assertContains($tag4, $initialTopic->getTags());
        static::assertEquals($initialTopic, $tag2->getTopic());
        static::assertEquals($initialTopic, $tag3->getTopic());
        static::assertEquals($initialTopic, $tag4->getTopic());

        $this->sut->moveTagToTopic($tag2, $topic);
        $this->sut->moveTagToTopic($tag3, $topic);
        $this->sut->moveTagToTopic($tag4, $topic);

        $result = $this->sut->getTopic($topic->getId());
        static::assertEquals(3, $result->getTags()->count());
        static::assertContains($tag2, $result->getTags());
        static::assertContains($tag3, $result->getTags());
        static::assertContains($tag4, $result->getTags());
        static::assertEquals($result, $tag2->getTopic());
        static::assertEquals($result, $tag3->getTopic());
        static::assertEquals($result, $tag4->getTopic());

        $initialTopic = $this->sut->getTopic($initialTopic->getId());
        static::assertEquals(0, $initialTopic->getTags()->count());
    }

    public function testDeleteTopic()
    {
        $topic = $this->getTagTopicReference('testFixtureTopic_1');
        $topicId = $topic->getId();
        $tag1 = $this->getTagReference('testFixtureTag_1');
        $tag2 = $this->getTagReference('testFixtureTag_2');
        $tag3 = $this->getTagReference('testFixtureTag_3');
        static::assertContains($tag1, $topic->getTags());
        static::assertContains($tag2, $topic->getTags());
        static::assertContains($tag3, $topic->getTags());

        $this->sut->deleteTopic($topic);
        static::assertNull($this->sut->getTopic($topicId));

        $foundTag1 = $this->sut->getTag($tag1->getId());
        static::assertNull($foundTag1);

        $foundTag2 = $this->sut->getTag($tag2->getId());
        static::assertNull($foundTag2);

        $foundTag3 = $this->sut->getTag($tag3->getId());
        static::assertNull($foundTag3);
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
