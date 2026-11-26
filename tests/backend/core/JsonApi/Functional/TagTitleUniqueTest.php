<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\Statement\TagTopic;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Logic\User\CustomerService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagTopicResourceType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class TagTitleUniqueTest extends JsonApiTest
{
    private const EXISTING_TITLE = 'Baulärm';
    private const SAME_TITLE_OTHER_SPELLING = '  baulärm ';
    private const OTHER_TITLE = 'Verkehrslärm';
    private const TAG_DUPLICATE_MESSAGE = 'Dieses Schlagwort gibt es in diesem Thema schon.';
    private const TOPIC_DUPLICATE_MESSAGE = 'Ein Schlagwort-Thema mit diesem Titel gibt es schon.';

    private ?User $user = null;
    private ?Procedure $procedure = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->procedure = ProcedureFactory::createOne([
            'orga'     => $this->user->getOrga(),
            'customer' => static::getContainer()->get(CustomerService::class)->getCurrentCustomer(),
        ])->_real();
        $this->enablePermissions([
            'area_admin_statements_tag',
            'feature_json_api_create',
            'feature_json_api_tag_topic',
            'feature_json_api_tag_topic_create',
            'feature_json_api_update',
        ]);
    }

    public function testCreateTagWithTitleOfTagInSameTopicIsRejected(): void
    {
        $topicId = $this->createTopic('Lärm', [self::EXISTING_TITLE]);

        $response = $this->sendTagCreation($topicId, self::SAME_TITLE_OTHER_SPELLING, Response::HTTP_BAD_REQUEST);

        self::assertContains(self::TAG_DUPLICATE_MESSAGE, $response['meta']['messages']['error']);
        self::assertSame(0, TagFactory::repository()->count(['title' => self::SAME_TITLE_OTHER_SPELLING]));
    }

    public function testCreateTagWithTitleOfTagInOtherTopicIsAccepted(): void
    {
        $this->createTopic('Lärm', [self::EXISTING_TITLE]);
        $otherTopicId = $this->createTopic('Baustelle', []);

        $this->sendTagCreation($otherTopicId, self::EXISTING_TITLE, Response::HTTP_CREATED);

        self::assertSame(2, TagFactory::repository()->count(['title' => self::EXISTING_TITLE]));
    }

    public function testRenameTagToTitleOfTagInSameTopicIsRejected(): void
    {
        $topicId = $this->createTopic('Lärm', [self::EXISTING_TITLE, self::OTHER_TITLE]);
        $tagId = $this->findTagId($topicId, self::OTHER_TITLE);

        $response = $this->sendTagRename($tagId, self::SAME_TITLE_OTHER_SPELLING, Response::HTTP_BAD_REQUEST);

        self::assertContains(self::TAG_DUPLICATE_MESSAGE, $response['meta']['messages']['error']);
        self::assertSame(self::OTHER_TITLE, $this->findStoredTitle(Tag::class, $tagId));
    }

    public function testRenameTagToTitleOfTagInOtherTopicIsAccepted(): void
    {
        $this->createTopic('Lärm', [self::EXISTING_TITLE]);
        $otherTopicId = $this->createTopic('Baustelle', [self::OTHER_TITLE]);
        $tagId = $this->findTagId($otherTopicId, self::OTHER_TITLE);

        $this->sendTagRename($tagId, self::EXISTING_TITLE, Response::HTTP_NO_CONTENT);

        self::assertSame(self::EXISTING_TITLE, $this->findStoredTitle(Tag::class, $tagId));
    }

    public function testRenameTagToDifferentSpellingOfOwnTitleIsAccepted(): void
    {
        $topicId = $this->createTopic('Lärm', [self::EXISTING_TITLE]);
        $tagId = $this->findTagId($topicId, self::EXISTING_TITLE);

        $this->sendTagRename($tagId, 'BAULÄRM', Response::HTTP_NO_CONTENT);

        self::assertSame('BAULÄRM', $this->findStoredTitle(Tag::class, $tagId));
    }

    public function testCreateTopicWithTitleOfExistingTopicIsRejected(): void
    {
        $this->createTopic(self::EXISTING_TITLE, []);

        $response = $this->executeCreationRequest(
            TagTopicResourceType::getName(),
            $this->user,
            $this->topicCreationBody(self::SAME_TITLE_OTHER_SPELLING),
            $this->procedure,
            Response::HTTP_BAD_REQUEST
        );

        self::assertContains(self::TOPIC_DUPLICATE_MESSAGE, $response['meta']['messages']['warning']);
        self::assertSame(0, TagTopicFactory::repository()->count(['title' => self::SAME_TITLE_OTHER_SPELLING]));
    }

    public function testCreateTopicWithNewTitleIsAccepted(): void
    {
        $this->createTopic(self::EXISTING_TITLE, []);

        $this->executeCreationRequest(
            TagTopicResourceType::getName(),
            $this->user,
            $this->topicCreationBody(self::OTHER_TITLE),
            $this->procedure,
            Response::HTTP_CREATED
        );

        self::assertSame(1, TagTopicFactory::repository()->count(['title' => self::OTHER_TITLE]));
    }

    public function testRenameTopicToTitleOfOtherTopicIsRejected(): void
    {
        $this->createTopic(self::EXISTING_TITLE, []);
        $topicId = $this->createTopic(self::OTHER_TITLE, []);

        $response = $this->sendTopicRename($topicId, self::SAME_TITLE_OTHER_SPELLING, Response::HTTP_BAD_REQUEST);

        self::assertContains(self::TOPIC_DUPLICATE_MESSAGE, $response['meta']['messages']['warning']);
        self::assertSame(self::OTHER_TITLE, $this->findStoredTitle(TagTopic::class, $topicId));
    }

    public function testRenameTopicToDifferentSpellingOfOwnTitleIsAccepted(): void
    {
        $topicId = $this->createTopic(self::EXISTING_TITLE, []);

        $this->sendTopicRename($topicId, 'BAULÄRM', Response::HTTP_NO_CONTENT);

        self::assertSame('BAULÄRM', $this->findStoredTitle(TagTopic::class, $topicId));
    }

    /**
     * @param list<string> $tagTitles
     */
    private function createTopic(string $title, array $tagTitles): string
    {
        $topic = TagTopicFactory::createOne(['procedure' => $this->procedure, 'title' => $title]);
        foreach ($tagTitles as $tagTitle) {
            TagFactory::createOne(['topic' => $topic, 'title' => $tagTitle]);
        }

        return $topic->getId();
    }

    private function findTagId(string $topicId, string $title): string
    {
        return TagFactory::repository()->findOneBy(['topic' => $topicId, 'title' => $title])->getId();
    }

    private function sendTagCreation(string $topicId, string $title, int $expectedStatus): array
    {
        return $this->executeCreationRequest(
            TagResourceType::getName(),
            $this->user,
            [
                'data' => [
                    'type'          => TagResourceType::getName(),
                    'attributes'    => ['title' => $title],
                    'relationships' => [
                        'topic' => ['data' => ['id' => $topicId, 'type' => TagTopicResourceType::getName()]],
                    ],
                ],
            ],
            $this->procedure,
            $expectedStatus
        );
    }

    private function sendTagRename(string $tagId, string $title, int $expectedStatus): ?array
    {
        return $this->executeUpdateRequest(
            TagResourceType::getName(),
            $tagId,
            $this->user,
            ['data' => ['id' => $tagId, 'type' => TagResourceType::getName(), 'attributes' => ['title' => $title]]],
            $this->procedure,
            $expectedStatus
        );
    }

    private function sendTopicRename(string $topicId, string $title, int $expectedStatus): ?array
    {
        return $this->executeUpdateRequest(
            TagTopicResourceType::getName(),
            $topicId,
            $this->user,
            [
                'data' => [
                    'id'         => $topicId,
                    'type'       => TagTopicResourceType::getName(),
                    'attributes' => ['title' => $title],
                ],
            ],
            $this->procedure,
            $expectedStatus
        );
    }

    private function topicCreationBody(string $title): array
    {
        return [
            'data' => [
                'type'          => TagTopicResourceType::getName(),
                'attributes'    => ['title' => $title],
                'relationships' => [
                    'procedure' => [
                        'data' => ['id' => $this->procedure->getId(), 'type' => ProcedureResourceType::getName()],
                    ],
                ],
            ],
        ];
    }

    /**
     * @param class-string<Tag|TagTopic> $entityClass
     */
    private function findStoredTitle(string $entityClass, string $id): string
    {
        $entityManager = static::getContainer()->get('doctrine')->getManager();
        $entityManager->clear();

        return $entityManager->find($entityClass, $id)->getTitle();
    }
}
