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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadFaqData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Faq;
use demosplan\DemosPlanCoreBundle\Entity\User\Role;
use demosplan\DemosPlanCoreBundle\ResourceTypes\FaqResourceType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class FaqResourceTypeTest extends JsonApiTest
{
    public function testList(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_CONTENT_EDITOR);

        $faqs = $this->executeListRequest(
            FaqResourceType::getName(),
            $user,
            null,
            Response::HTTP_OK,
            []
        );

        self::assertCount(2, $faqs['data']);
    }

    /**
     * @dataProvider getRoleUpdateData
     */
    public function testUpdate(string $faqReference, bool $enablePublic, bool $enableInstitution, bool $enablePlanner, bool $enabled): void
    {
        /** @var Faq $faq */
        $faq = $this->getReference($faqReference);
        $user = $this->getUserReference(LoadUserData::TEST_USER_CONTENT_EDITOR);

        $faqs = $this->executeUpdateRequest(
            FaqResourceType::getName(),
            $faq->getId(),
            $user,
            [
                'data' => [
                    'id'         => $faq->getId(),
                    'type'       => FaqResourceType::getName(),
                    'attributes' => [
                        'publicVisible'               => $enablePublic,
                        'invitableInstitutionVisible' => $enableInstitution,
                        'fpVisible'                   => $enablePlanner,
                        'enabled'                     => $enabled,
                    ],
                ],
            ],
            null,
            Response::HTTP_NO_CONTENT,
            []
        );

        self::assertNull($faqs);
        self::assertSame($enablePublic, $faq->hasRoleGroupCode(Role::GGUEST));
        self::assertSame($enableInstitution, $faq->hasRoleGroupCode(Role::GPSORG));
        self::assertSame($enablePlanner, $faq->hasRoleGroupCode(Role::GLAUTH));
        self::assertSame($enabled, $faq->getEnabled());
    }

    /**
     * @dataProvider getRoleUpdateData
     */
    public function testUpdateDeniedForGuest(string $faqReference, bool $enablePublic, bool $enableInstitution, bool $enablePlanner, bool $enabled): void
    {
        /** @var Faq $faq */
        $faq = $this->getReference($faqReference);
        $user = $this->getUserReference(LoadUserData::TEST_USER_GUEST);

        $previouslyPublicVisible = $faq->hasRoleGroupCode(Role::GGUEST);
        $previousInstitutionVisible = $faq->hasRoleGroupCode(Role::GPSORG);
        $previousPlannerVisible = $faq->hasRoleGroupCode(Role::GLAUTH);
        $previouslyEnabled = $faq->getEnabled();

        $faqs = $this->executeUpdateRequest(
            FaqResourceType::getName(),
            $faq->getId(),
            $user,
            [
                'data' => [
                    'id'         => $faq->getId(),
                    'type'       => FaqResourceType::getName(),
                    'attributes' => [
                        'publicVisible'               => $enablePublic,
                        'invitableInstitutionVisible' => $enableInstitution,
                        'fpVisible'                   => $enablePlanner,
                        'enabled'                     => $enabled,
                    ],
                ],
            ],
            null,
            Response::HTTP_BAD_REQUEST,
            []
        );

        self::assertSame($previouslyPublicVisible, $faq->hasRoleGroupCode(Role::GGUEST));
        self::assertSame($previousInstitutionVisible, $faq->hasRoleGroupCode(Role::GPSORG));
        self::assertSame($previousPlannerVisible, $faq->hasRoleGroupCode(Role::GLAUTH));
        self::assertSame($previouslyEnabled, $faq->getEnabled());
    }

    /**
     * @dataProvider getRoleUpdateData
     */
    public function testUpdateDeniedForForeignCustomerFaq(string $faqReference, bool $enablePublic, bool $enableInstitution, bool $enablePlanner, bool $enabled): void
    {
        /** @var Faq $faq */
        $faq = $this->getReference(LoadFaqData::FAQ_PLANNER_BB);
        $user = $this->getUserReference(LoadUserData::TEST_USER_CONTENT_EDITOR);

        $previouslyPublicVisible = $faq->hasRoleGroupCode(Role::GGUEST);
        $previousInstitutionVisible = $faq->hasRoleGroupCode(Role::GPSORG);
        $previousPlannerVisible = $faq->hasRoleGroupCode(Role::GLAUTH);
        $previouslyEnabled = $faq->getEnabled();

        $faqs = $this->executeUpdateRequest(
            FaqResourceType::getName(),
            $faq->getId(),
            $user,
            [
                'data' => [
                    'id'         => $faq->getId(),
                    'type'       => FaqResourceType::getName(),
                    'attributes' => [
                        'publicVisible'               => $enablePublic,
                        'invitableInstitutionVisible' => $enableInstitution,
                        'fpVisible'                   => $enablePlanner,
                        'enabled'                     => $enabled,
                    ],
                ],
            ],
            null,
            Response::HTTP_BAD_REQUEST,
            []
        );

        self::assertSame($previouslyPublicVisible, $faq->hasRoleGroupCode(Role::GGUEST));
        self::assertSame($previousInstitutionVisible, $faq->hasRoleGroupCode(Role::GPSORG));
        self::assertSame($previousPlannerVisible, $faq->hasRoleGroupCode(Role::GLAUTH));
        self::assertSame($previouslyEnabled, $faq->getEnabled());
    }

    public function testListDenied(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_GUEST);

        $faqs = $this->executeListRequest(
            FaqResourceType::getName(),
            $user,
            null,
            Response::HTTP_BAD_REQUEST,
            []
        );

        self::assertNotEmpty($faqs);
    }

    /**
     * @return array<int, array<int, bool>>
     */
    public function getRoleUpdateData(): array
    {
        return [
            [LoadFaqData::FAQ_GUEST, false, false, false, false],
            [LoadFaqData::FAQ_GUEST, false, false, true, false],
            [LoadFaqData::FAQ_GUEST, false, true, false, false],
            [LoadFaqData::FAQ_GUEST, false, true, true, false],

            [LoadFaqData::FAQ_GUEST, false, false, false, true],
            [LoadFaqData::FAQ_GUEST, false, false, true, true],
            [LoadFaqData::FAQ_GUEST, false, true, false, true],
            [LoadFaqData::FAQ_GUEST, false, true, true, true],

            [LoadFaqData::FAQ_GUEST, true, false, false, false],
            [LoadFaqData::FAQ_GUEST, true, false, true, false],
            [LoadFaqData::FAQ_GUEST, true, true, false, false],
            [LoadFaqData::FAQ_GUEST, true, true, true, false],

            [LoadFaqData::FAQ_GUEST, true, false, false, true],
            [LoadFaqData::FAQ_GUEST, true, false, true, true],
            [LoadFaqData::FAQ_GUEST, true, true, false, true],
            [LoadFaqData::FAQ_GUEST, true, true, true, true],

            [LoadFaqData::FAQ_PLANNER, false, false, false, false],
            [LoadFaqData::FAQ_PLANNER, false, false, true, false],
            [LoadFaqData::FAQ_PLANNER, false, true, false, false],
            [LoadFaqData::FAQ_PLANNER, false, true, true, false],

            [LoadFaqData::FAQ_PLANNER, false, false, false, true],
            [LoadFaqData::FAQ_PLANNER, false, false, true, true],
            [LoadFaqData::FAQ_PLANNER, false, true, false, true],
            [LoadFaqData::FAQ_PLANNER, false, true, true, true],

            [LoadFaqData::FAQ_PLANNER, true, false, false, false],
            [LoadFaqData::FAQ_PLANNER, true, false, true, false],
            [LoadFaqData::FAQ_PLANNER, true, true, false, false],
            [LoadFaqData::FAQ_PLANNER, true, true, true, false],

            [LoadFaqData::FAQ_PLANNER, true, false, false, true],
            [LoadFaqData::FAQ_PLANNER, true, false, true, true],
            [LoadFaqData::FAQ_PLANNER, true, true, false, true],
            [LoadFaqData::FAQ_PLANNER, true, true, true, true],
        ];
    }
}
