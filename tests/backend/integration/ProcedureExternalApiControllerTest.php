<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace backend\integration;

use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class ProcedureExternalApiControllerTest extends AbstractApiTest
{
    private const VALID_TOKEN = 'test-procedure-api-token';
    private const ENDPOINT = '/api/1.0/external/procedure';

    protected function setUp(): void
    {
        putenv('DPLAN_API_PROCEDURE_CREATE_TOKEN='.self::VALID_TOKEN);
        parent::setUp();
    }

    protected function tearDown(): void
    {
        putenv('DPLAN_API_PROCEDURE_CREATE_TOKEN');
        parent::tearDown();
    }

    protected function getServerParameters(): array
    {
        return [];
    }

    public function testReturnsUnauthorizedWhenAuthorizationHeaderIsMissing(): void
    {
        $this->client->request(
            'POST',
            self::ENDPOINT,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            '{}'
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testReturnsUnauthorizedWhenTokenIsWrong(): void
    {
        $this->client->request(
            'POST',
            self::ENDPOINT,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer wrong-token',
                'CONTENT_TYPE'       => 'application/json',
            ],
            '{}'
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testReturnsUnauthorizedWhenTokenIsTooShort(): void
    {
        $this->client->request(
            'POST',
            self::ENDPOINT,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer short',
                'CONTENT_TYPE'       => 'application/json',
            ],
            '{}'
        );

        self::assertSame(Response::HTTP_UNAUTHORIZED, $this->client->getResponse()->getStatusCode());
    }

    public function testReturnsBadRequestWhenNameIsMissing(): void
    {
        $this->client->request(
            'POST',
            self::ENDPOINT,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.self::VALID_TOKEN,
                'CONTENT_TYPE'       => 'application/json',
            ],
            (string) json_encode(['orgaId' => '00000000-0000-0000-0000-000000000000'])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testReturnsBadRequestWhenOrgaIdIsMissing(): void
    {
        $this->client->request(
            'POST',
            self::ENDPOINT,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.self::VALID_TOKEN,
                'CONTENT_TYPE'       => 'application/json',
            ],
            (string) json_encode(['name' => 'Test Procedure'])
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $this->client->getResponse()->getStatusCode());
    }

    public function testReturnsUnprocessableEntityWhenNoEligibleUserExistsForOrga(): void
    {
        $this->client->request(
            'POST',
            self::ENDPOINT,
            [],
            [],
            [
                'HTTP_AUTHORIZATION' => 'Bearer '.self::VALID_TOKEN,
                'CONTENT_TYPE'       => 'application/json',
            ],
            (string) json_encode([
                'name'   => 'Test Procedure',
                'orgaId' => '00000000-0000-0000-0000-000000000000',
            ])
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $this->client->getResponse()->getStatusCode());
    }
}
