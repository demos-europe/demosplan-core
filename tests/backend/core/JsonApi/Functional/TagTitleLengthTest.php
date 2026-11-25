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

class TagTitleLengthTest extends JsonApiTest
{
    private const TITLE_MAX_LENGTH = 250;

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

    public function testCreateTagWithTooLongTitleIsRejected(): void
    {
        $topic = TagTopicFactory::createOne(['procedure' => $this->procedure]);
        $title = $this->createTitle(self::TITLE_MAX_LENGTH + 1);

        $this->executeCreationRequest(
            TagResourceType::getName(),
            $this->user,
            [
                'data' => [
                    'type'          => TagResourceType::getName(),
                    'attributes'    => ['title' => $title],
                    'relationships' => [
                        'topic' => ['data' => ['id' => $topic->getId(), 'type' => TagTopicResourceType::getName()]],
                    ],
                ],
            ],
            $this->procedure,
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame(0, TagFactory::repository()->count(['title' => $title]));
    }

    public function testUpdateTagWithTooLongTitleIsRejected(): void
    {
        $topic = TagTopicFactory::createOne(['procedure' => $this->procedure]);
        $tagId = TagFactory::createOne(['topic' => $topic, 'title' => 'Bestehendes Schlagwort'])->getId();

        $this->sendTagTitleUpdate($tagId, $this->createTitle(self::TITLE_MAX_LENGTH + 1), Response::HTTP_BAD_REQUEST);

        self::assertSame('Bestehendes Schlagwort', $this->findStoredTitle(Tag::class, $tagId));
    }

    public function testUpdateTagWithMaxLengthTitleIsAccepted(): void
    {
        $topic = TagTopicFactory::createOne(['procedure' => $this->procedure]);
        $tag = TagFactory::createOne(['topic' => $topic, 'title' => 'Bestehendes Schlagwort']);
        $title = $this->createTitle(self::TITLE_MAX_LENGTH);

        $this->sendTagTitleUpdate($tag->getId(), $title, Response::HTTP_NO_CONTENT);

        self::assertSame($title, $tag->_refresh()->_real()->getTitle());
    }

    public function testCreateTopicWithTooLongTitleIsRejected(): void
    {
        $title = $this->createTitle(self::TITLE_MAX_LENGTH + 1);

        $this->executeCreationRequest(
            TagTopicResourceType::getName(),
            $this->user,
            [
                'data' => [
                    'type'          => TagTopicResourceType::getName(),
                    'attributes'    => ['title' => $title],
                    'relationships' => [
                        'procedure' => [
                            'data' => ['id' => $this->procedure->getId(), 'type' => ProcedureResourceType::getName()],
                        ],
                    ],
                ],
            ],
            $this->procedure,
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame(0, TagTopicFactory::repository()->count(['title' => $title]));
    }

    public function testUpdateTopicWithTooLongTitleIsRejected(): void
    {
        $topicId = TagTopicFactory::createOne([
            'procedure' => $this->procedure,
            'title'     => 'Bestehendes Thema',
        ])->getId();

        $this->executeUpdateRequest(
            TagTopicResourceType::getName(),
            $topicId,
            $this->user,
            [
                'data' => [
                    'id'         => $topicId,
                    'type'       => TagTopicResourceType::getName(),
                    'attributes' => ['title' => $this->createTitle(self::TITLE_MAX_LENGTH + 1)],
                ],
            ],
            $this->procedure,
            Response::HTTP_BAD_REQUEST
        );

        self::assertSame('Bestehendes Thema', $this->findStoredTitle(TagTopic::class, $topicId));
    }

    private function sendTagTitleUpdate(string $tagId, string $title, int $expectedStatus): void
    {
        $this->executeUpdateRequest(
            TagResourceType::getName(),
            $tagId,
            $this->user,
            [
                'data' => [
                    'id'         => $tagId,
                    'type'       => TagResourceType::getName(),
                    'attributes' => ['title' => $title],
                ],
            ],
            $this->procedure,
            $expectedStatus
        );
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

    private function createTitle(int $length): string
    {
        return str_repeat('ä', $length);
    }
}
