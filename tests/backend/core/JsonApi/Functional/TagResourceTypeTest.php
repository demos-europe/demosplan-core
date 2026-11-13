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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AssignableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class TagResourceTypeTest extends JsonApiTest
{
    private const NEW_TAG_TITLE = 'Neues Schlagwort';
    private const NEW_DESCRIPTION = 'Fachliche Beschreibung';
    private const EXISTING_DESCRIPTION = 'Bestehende Beschreibung';
    private const DESCRIPTION_MAX_LENGTH = 1000;

    public function testUpdateDefaultAssignee(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $tagTopic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tag = TagFactory::createOne(['topic' => $tagTopic]);
        $this->enablePermissions([
            'area_admin_statements_tag',
            'feature_json_api_update',
            'feature_json_api_user',
            'feature_tag_default_assignee',
        ]);

        $this->executeUpdateRequest(
            TagResourceType::getName(),
            $tag->getId(),
            $user,
            [
                'data' => [
                    'id'         => $tag->getId(),
                    'type'       => TagResourceType::getName(),
                    'attributes' => [
                        'title' => $tag->getTitle(),
                    ],
                    'relationships' => [
                        'defaultAssignee' => [
                            'data' => [
                                'id'   => $user->getId(),
                                'type' => AssignableUserResourceType::getName(),
                            ],
                        ],
                    ],
                ],
            ],
            $procedure,
            Response::HTTP_NO_CONTENT
        );

        self::assertSame($user->getId(), $tag->_real()->getDefaultAssignee()?->getId());
    }

    public function testRemoveDefaultAssignee(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $tagTopic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tag = TagFactory::createOne(['topic' => $tagTopic]);
        $assignedUser = UserFactory::createOne();
        $tag->_real()->setDefaultAssignee($assignedUser->_real());
        $tag->_save();
        $this->enablePermissions([
            'area_admin_statements_tag',
            'feature_json_api_update',
            'feature_json_api_user',
            'feature_tag_default_assignee',
        ]);

        $this->executeUpdateRequest(
            TagResourceType::getName(),
            $tag->getId(),
            $user,
            [
                'data' => [
                    'id'         => $tag->getId(),
                    'type'       => TagResourceType::getName(),
                    'attributes' => [
                        'title' => $tag->getTitle(),
                    ],
                    'relationships' => [
                        'defaultAssignee' => [
                            'data' => null,
                        ],
                    ],
                ],
            ],
            $procedure,
            Response::HTTP_NO_CONTENT
        );

        self::assertNull($tag->_real()->getDefaultAssignee());
    }

    #[DataProvider('successfulDescriptionUpdates')]
    public function testUpdateDescription(?string $existingDescription, array $attributes, ?string $expected): void
    {
        $tagId = $this->sendTagUpdate($existingDescription, $attributes, true, Response::HTTP_NO_CONTENT);

        self::assertSame($expected, $this->findTag($tagId)->getDescription());
    }

    public static function successfulDescriptionUpdates(): iterable
    {
        yield 'sets description' => [null, ['description' => self::NEW_DESCRIPTION], self::NEW_DESCRIPTION];
        yield 'omitted description keeps existing one' => [self::EXISTING_DESCRIPTION, [], self::EXISTING_DESCRIPTION];
        yield 'empty description removes it' => [self::EXISTING_DESCRIPTION, ['description' => ''], null];
    }

    #[DataProvider('rejectedDescriptionUpdates')]
    public function testUpdateDescriptionIsRejected(
        ?string $existingDescription,
        array $attributes,
        bool $withPermission,
    ): void {
        $tagId = $this->sendTagUpdate($existingDescription, $attributes, $withPermission, Response::HTTP_BAD_REQUEST);

        self::assertSame($existingDescription, $this->findTag($tagId)->getDescription());
    }

    public static function rejectedDescriptionUpdates(): iterable
    {
        yield 'exceeding max length' => [
            self::EXISTING_DESCRIPTION,
            ['description' => self::createTooLongDescription()],
            true,
        ];
        yield 'without permission' => [null, ['description' => self::NEW_DESCRIPTION], false];
    }

    #[DataProvider('successfulTagCreations')]
    public function testCreateTag(array $attributes, ?string $expectedDescription): void
    {
        $response = $this->sendTagCreation($attributes, true, Response::HTTP_CREATED);

        self::assertSame($expectedDescription, $this->findTag($response['data']['id'])->getDescription());
        self::assertArrayNotHasKey('description', $response['data']['attributes'] ?? []);
    }

    public static function successfulTagCreations(): iterable
    {
        yield 'with description' => [['description' => self::NEW_DESCRIPTION], self::NEW_DESCRIPTION];
        yield 'without description' => [[], null];
        yield 'with empty description' => [['description' => ''], null];
    }

    #[DataProvider('rejectedTagCreations')]
    public function testCreateTagIsRejected(array $attributes, bool $withPermission): void
    {
        $this->sendTagCreation($attributes, $withPermission, Response::HTTP_BAD_REQUEST);

        $this->getEntityManager()->clear();
        $createdTag = $this->getEntityManager()
            ->getRepository(Tag::class)
            ->findOneBy(['title' => self::NEW_TAG_TITLE]);
        self::assertNull($createdTag);
    }

    public static function rejectedTagCreations(): iterable
    {
        yield 'exceeding max length' => [['description' => self::createTooLongDescription()], true];
        yield 'without permission' => [['description' => self::NEW_DESCRIPTION], false];
    }

    private static function createTooLongDescription(): string
    {
        return str_repeat('a', self::DESCRIPTION_MAX_LENGTH + 1);
    }

    private function sendTagUpdate(
        ?string $existingDescription,
        array $attributes,
        bool $withPermission,
        int $expectedStatus,
    ): string {
        $user = $this->createApiUser();
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $tag = TagFactory::createOne([
            'topic'       => TagTopicFactory::createOne(['procedure' => $procedure]),
            'description' => $existingDescription,
        ]);
        $tagId = $tag->getId();
        $title = $tag->getTitle();
        $this->enablePermissions($this->buildPermissions('feature_json_api_update', $withPermission));

        $this->executeUpdateRequest(
            TagResourceType::getName(),
            $tagId,
            $user,
            $this->buildTagUpdateBody($tagId, $title, $attributes),
            $procedure,
            $expectedStatus
        );

        return $tagId;
    }

    private function sendTagCreation(array $attributes, bool $withPermission, int $expectedStatus): array
    {
        $user = $this->createApiUser();
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $this->enablePermissions($this->buildPermissions('feature_json_api_create', $withPermission));

        return $this->executeCreationRequest(
            TagResourceType::getName(),
            $user,
            $this->buildTagCreationBody($topic->getId(), ['title' => self::NEW_TAG_TITLE] + $attributes),
            $procedure,
            $expectedStatus
        );
    }

    /**
     * @return list<string>
     */
    private function buildPermissions(string $jsonApiPermission, bool $withDescriptionPermission): array
    {
        $permissions = ['area_admin_statements_tag', $jsonApiPermission];
        if ($withDescriptionPermission) {
            $permissions[] = 'field_tag_description';
        }

        return $permissions;
    }

    private function createApiUser(): User
    {
        return UserFactory::new([
            'deleted'              => false,
            'login'                => 'tag-description-test-'.uniqid('', true).'@example.com',
            'totpEnabled'          => false,
            'authCodeEmailEnabled' => false,
        ])
            ->afterInstantiate(static function (User $user): void {
                $user->setProfileCompleted(true);
            })
            ->create()
            ->_real();
    }

    private function buildTagCreationBody(string $topicId, array $attributes): array
    {
        return [
            'data' => [
                'type'          => TagResourceType::getName(),
                'attributes'    => $attributes,
                'relationships' => [
                    'topic' => ['data' => ['type' => 'TagTopic', 'id' => $topicId]],
                ],
            ],
        ];
    }

    private function buildTagUpdateBody(string $tagId, string $title, array $additionalAttributes): array
    {
        return [
            'data' => [
                'id'         => $tagId,
                'type'       => TagResourceType::getName(),
                'attributes' => ['title' => $title] + $additionalAttributes,
            ],
        ];
    }

    private function findTag(string $tagId): Tag
    {
        $entityManager = $this->getEntityManager();
        $entityManager->clear();

        return $entityManager->getRepository(Tag::class)->find($tagId);
    }
}
