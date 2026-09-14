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

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class AssignableUserResourceApiTest extends AbstractApiTest
{
    private const ASSIGNABLE_USER_ROUTE = '/api/3.0/AssignableUser';

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
        $headers = [];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }

    public function testGetCollectionIncludesOrgaName(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_user']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::ASSIGNABLE_USER_ROUTE.'?sort=lastname&include=orga&fields[AssignableUser]=firstname,lastname,orga&fields[orga]=name',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        self::assertStringContainsString($user->getOrga()->getName(), $content);
        self::assertStringContainsString('"included"', $content);
    }

    public function testGetCollectionIsEmptyWithoutCurrentProcedure(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_user']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::ASSIGNABLE_USER_ROUTE,
            'GET',
            $user,
            null
        );

        self::assertStringNotContainsString($user->getOrga()->getName(), $response->getContent());
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
