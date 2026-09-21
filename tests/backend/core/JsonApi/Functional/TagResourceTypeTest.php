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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\User\UserFactory;
use demosplan\DemosPlanCoreBundle\ResourceTypes\AssignableUserResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\TagResourceType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class TagResourceTypeTest extends JsonApiTest
{
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
}
