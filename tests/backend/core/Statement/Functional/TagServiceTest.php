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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTitleException;
use demosplan\DemosPlanCoreBundle\Exception\DuplicatedTagTopicTitleException;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Traits\DI\RefreshElasticsearchIndexTrait;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\HttpFoundation\Session\Session;
use Tests\Base\FunctionalTestCase;

class TagServiceTest extends FunctionalTestCase
{
    use RefreshElasticsearchIndexTrait;

    /**
     * @var TagService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(TagService::class);

        $this->setElasticsearchIndexManager($this->getContainer()->get('fos_elastica.index_manager'));
    }

    public function testCreateTag(): void
    {
        $testTopic = TagTopicFactory::createOne();
        $title = 'ersterTag';

        $tag = $this->sut->createTag($title, $testTopic->_real());

        $result = $this->sut->getTag($tag->getId());
        static::assertNotNull($result);
        static::assertInstanceOf(Tag::class, $result);

        static::assertSame($title, $result->getTitle());
        static::assertEquals($testTopic->_real(), $result->getTopic());
        static::assertNotNull($result->getId());
    }

    public function testCreateTopic(): void
    {
        $title = 'erstesTopic';
        $testProcedure = ProcedureFactory::createOne();

        $topic = $this->sut->createTagTopic($title, $testProcedure->_real());

        $result = $this->sut->getTopic($topic->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(TagTopic::class, $result);
        static::assertSame($title, $result->getTitle());
        static::assertNotNull($result->getId());
        static::assertEmpty($result->getTags());

        $testTag = TagFactory::createOne();
        $moved = $this->sut->moveTagToTopic($testTag->_real(), $topic);
        static::assertTrue($moved);

        $result = $this->sut->getTopic($topic->getId());

        static::assertNotEmpty($result->getTags());
        static::assertContains($testTag->_real(), $result->getTags());
    }

    public function testDuplicatedTopic(): void
    {
        $this->expectException(DuplicatedTagTopicTitleException::class);
        $title = 'erstesTopic';
        $testProcedure = ProcedureFactory::createOne();
        $this->sut->createTagTopic($title, $testProcedure->_real());
        $this->sut->createTagTopic($title, $testProcedure->_real());
    }

    /**
     * @throws DuplicatedTagTopicTitleException
     * @throws DuplicatedTagTitleException
     */
    public function testMoveTagToTopic(): void
    {
        $testProcedure = ProcedureFactory::createOne();
        $topic1 = $this->sut->createTagTopic('Topic1', $testProcedure->_real());
        $topic2 = $this->sut->createTagTopic('Topic2', $testProcedure->_real());
        $tag1 = $this->sut->createTag('newTag1', $topic1);
        $tag2 = $this->sut->createTag('newTag2', $topic1);

        $topic1 = $this->sut->getTopic($topic1->getId());
        static::assertSame(2, $topic1->getTags()->count());
        static::assertEquals($topic1, $tag1->getTopic());
        static::assertEquals($topic1, $tag2->getTopic());

        $topic2 = $this->sut->getTopic($topic2->getId());
        $moved = $this->sut->moveTagToTopic($tag2, $topic2);
        static::assertTrue($moved);
        static::assertSame(1, $topic2->getTags()->count());
        static::assertContains($tag2, $topic2->getTags());
        static::assertEquals($tag2, $topic2->getTags()[0]);

        $topic1 = $this->sut->getTopic($topic1->getId());
        static::assertSame(1, $topic1->getTags()->count());
        static::assertContains($tag1, $topic1->getTags());
        static::assertEquals($tag1, $topic1->getTags()[0]);
    }

    public function testGetTag(): void
    {
        $testTag1 = TagFactory::createOne();

        $result = $this->sut->getTag($testTag1->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(Tag::class, $result);
        static::assertSame($testTag1->getTitle(), $result->getTitle());
        static::assertEquals($testTag1->getTopic(), $result->getTopic());
    }

    public function testGetTopic(): void
    {
        $testTopic = TagTopicFactory::createOne();
        $result = $this->sut->getTopic($testTopic->getId());

        static::assertNotNull($result);
        static::assertInstanceOf(TagTopic::class, $result);
        static::assertSame($testTopic->getTitle(), $result->getTitle());
        static::assertEquals($testTopic->getTags(), $result->getTags());
    }

    /**
     * @throws DuplicatedTagTitleException
     * @throws DuplicatedTagTopicTitleException
     */
    public function testAttachTagsAndTopic(): void
    {
        $testProcedure = ProcedureFactory::createOne();
        $topic = $this->sut->createTagTopic('filledTopic', $testProcedure->_real());
        $initialTopic = $this->sut->createTagTopic('initialTopic', $testProcedure->_real());
        $tag2 = $this->sut->createTag('tagToFillInTopic2', $initialTopic);
        $tag3 = $this->sut->createTag('tagToFillInTopic3', $initialTopic);
        $tag4 = $this->sut->createTag('tagToFillInTopic4', $initialTopic);

        $initialTopic = $this->sut->getTopic($initialTopic->getId());
        static::assertSame(3, $initialTopic->getTags()->count());
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
        static::assertSame(3, $result->getTags()->count());
        static::assertContains($tag2, $result->getTags());
        static::assertContains($tag3, $result->getTags());
        static::assertContains($tag4, $result->getTags());
        static::assertEquals($result, $tag2->getTopic());
        static::assertEquals($result, $tag3->getTopic());
        static::assertEquals($result, $tag4->getTopic());

        $initialTopic = $this->sut->getTopic($initialTopic->getId());
        static::assertEquals(0, $initialTopic->getTags()->count());
    }

    /**
     * @throws EntityNotFoundException
     */
    public function testDeleteTopic(): void
    {
        $topic = TagTopicFactory::createOne();
        $topicId = $topic->getId();

        $tags = TagFactory::createMany(3, static fn (int $i) => [
            'title' => "TagTitle $i",
            'topic' => $topic,
        ]);

        $tagId1 = $tags[0]->getId();
        static::assertContains($tags[0]->_real(), $topic->getTags());
        $tagId2 = $tags[1]->getId();
        static::assertContains($tags[1]->_real(), $topic->getTags());
        $tagId3 = $tags[2]->getId();
        static::assertContains($tags[2]->_real(), $topic->getTags());

        $this->sut->deleteTopic($topic->_real());
        static::assertNull($this->sut->getTopic($topicId));
        static::assertNull($this->sut->getTag($tagId1));
        static::assertNull($this->sut->getTag($tagId2));
        static::assertNull($this->sut->getTag($tagId3));
    }

    protected function setUpMockSession(
        string $userReferenceName = LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY
    ): Session {
        $session = parent::setUpMockSession($userReferenceName);
        $permissions['feature_statement_assignment']['enabled'] = false;
        $permissions['feature_statement_cluster']['enabled'] = false;
        $permissions['feature_statement_content_changes_save']['enabled'] = true;
        $session->set('permissions', $permissions);

        return $session;
    }

    /**
     * @throws DuplicatedTagTitleException
     */
    public function testCreateTagWithDuplicateTitle(): void
    {
        $testTag1 = TagFactory::createOne();
        $testTopic1 = TagTopicFactory::createOne();
        $this->sut->createTag($testTag1->getTitle(), $testTopic1->_real());
        $this->expectException(DuplicatedTagTitleException::class);
        $this->sut->createTag($testTag1->getTitle(), $testTopic1->_real());
    }
}
