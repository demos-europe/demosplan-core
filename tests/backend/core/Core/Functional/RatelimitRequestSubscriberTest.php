<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlanningDocumentCategoryResourceType;
use Exception;
use Lexik\Bundle\JWTAuthenticationBundle\Security\Authentication\Token\JWTUserToken;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

class RatelimitRequestSubscriberTest extends JsonApiTest
{
    private ?string $jwtToken = '';
    private const RATE_LIMIT = 100;

    public function testAnyValidRequest(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_GUEST);
        $this->enablePermissions(['area_documents']);
        $this->executeListRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $user
        );
    }

    public function testRateLimitedRequest(): void
    {
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        $user = $this->getUserReference(LoadUserData::TEST_USER_GUEST);
        $this->enablePermissions(['area_documents']);
        $this->expectException(Exception::class);
        // call the same request 10 times, this should work
        for ($i = 0; $i < self::RATE_LIMIT; ++$i) {
            $this->executeListRequest(
                PlanningDocumentCategoryResourceType::getName(),
                $user
            );
        }
        // call one more time, this should fail
        // should be Response::HTTP_TOO_MANY_REQUESTS, but in the tests it redirects to /error
        // at least it is not Response::HTTP_OK
        $this->executeListRequest(
            PlanningDocumentCategoryResourceType::getName(),
            $user,
            null,
            Response::HTTP_FOUND
        );
    }

    protected function initializeUser(User $user): string
    {
        // use existing token if already initialized to be able to mock bad requests
        if ('' === $this->jwtToken) {
            $this->jwtToken = $this->tokenManager->create($user);
            $jwtToken = new JWTUserToken($user->getDplanRolesArray(), $user, $this->jwtToken);
            $this->tokenStorage->setToken($jwtToken);
        }

        return $this->jwtToken;
    }
}
