<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi;

use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class OrgaResourceApiTest extends AbstractApiTest
{
    private const ORGA_ROUTE = '/api/3.0/Orga/';

    /**
     * /api/3.0/* routes sit behind the `api_platform` firewall (context: main, form-login
     * authenticator), not the stateless JWT `api` firewall AbstractApiTest::sendRequest() targets —
     * so authentication needs the session-based test login, not an X-JWT-Authorization header.
     */
    private function loginUserForApiPlatform(User $user): void
    {
        $this->client->loginUser($user, 'main');
    }

    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        return [];
    }

    public function testGetReturnsOwnOrgaWithoutSpecialPermission(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::ORGA_ROUTE.$user->getOrga()->getId(),
            'GET',
            $user,
            null
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString($user->getOrga()->getName(), $response->getContent());
    }

    /**
     * Guards against the ApiPlatform Orga endpoint fetching any organisation by id with no
     * conditions at all, bypassing OrgaResourceType::getMandatoryConditions()'s unconditional
     * exclusion of the anonymous citizen organisation.
     */
    public function testGetIsDeniedForAnonymousOrga(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::ORGA_ROUTE.UserInterface::ANONYMOUS_USER_ORGA_ID,
            'GET',
            $user,
            null
        );

        self::assertStringNotContainsString(UserInterface::ANONYMOUS_USER_ORGA_NAME, $response->getContent());
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
