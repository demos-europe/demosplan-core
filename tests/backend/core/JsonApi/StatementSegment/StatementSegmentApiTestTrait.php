<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\StatementSegment;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shared helpers for the tests of the `/api/3.0/StatementSegment` endpoint.
 *
 * Meant for subclasses of {@see \Tests\Base\AbstractApiTest}.
 */
trait StatementSegmentApiTestTrait
{
    private const SEGMENT_COLLECTION_ROUTE = '/api/3.0/StatementSegment';

    /**
     * /api/3.0/* routes sit behind the `api_platform` firewall (context: main, form-login
     * authenticator), not the stateless JWT `api` firewall AbstractApiTest::sendRequest() targets —
     * so authentication needs the session-based test login, not an X-JWT-Authorization header.
     */
    private function loginUserForApiPlatform(User $user): void
    {
        $this->client->loginUser($user, 'main');
    }

    /**
     * The `main` firewall authenticates via the session set up in
     * {@see loginUserForApiPlatform()}. The inherited {@see \Tests\Base\AbstractApiTest::getAdditionalHeaders()}
     * always attaches an X-JWT-Authorization header meant for the stateless `api` firewall;
     * sending it alongside here confuses the `main` firewall's lazy authentication and can
     * cause it to treat the request as unauthenticated, so it is omitted for these requests.
     */
    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers = [];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }

    /**
     * SegmentFactory only applies an explicit `procedure` to the segment row itself; its default
     * parentStatementOfSegment keeps the factory's own procedure. The parent statement is created
     * explicitly so segment and parent statement consistently belong to the same procedure.
     *
     * @param array<string, mixed> $attributes additional or overriding segment attributes
     */
    private function createSegmentInProcedure(Procedure $procedure, array $attributes = []): Segment
    {
        return SegmentFactory::createOne(array_merge([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $procedure])->_real(),
        ], $attributes))->_real();
    }

    /**
     * Logs in as the default test user with the segment permission, sends GET with the given
     * query string, asserts a 200 and returns the segment ids in response order.
     *
     * @return list<string>
     */
    private function requestCollectionIds(string $query, Procedure $procedure): array
    {
        return array_column($this->requestCollection($query, $procedure)['data'], 'id');
    }

    /**
     * Same as {@see requestCollectionIds()}, but returns the whole decoded JSON:API document.
     *
     * @return array<string, mixed>
     */
    private function requestCollection(string $query, Procedure $procedure): array
    {
        $response = $this->sendSegmentRequest(
            self::SEGMENT_COLLECTION_ROUTE.('' === $query ? '' : '?'.$query),
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);

        return Json::decodeToArray($content);
    }

    /**
     * Logs in as the default test user, enables the given permissions and sends a GET request.
     *
     * @param list<string> $permissions pass an empty list to send the request without any permission
     */
    private function sendSegmentRequest(
        string $uri,
        ?Procedure $procedure,
        array $permissions = ['feature_json_api_statement_segment'],
    ): Response {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        if ([] !== $permissions) {
            $this->enablePermissions($permissions);
        }
        // Logged in before anything hydrates the user's organisation: a lazy proxy on the user
        // cannot be serialized into the session by KernelBrowser::loginUser().
        $this->loginUserForApiPlatform($user);

        return $this->sendRequest($uri, 'GET', $user, $procedure);
    }

    /**
     * @return list<string>
     */
    private function getResponseIds(Response $response): array
    {
        $content = $response->getContent();
        self::assertIsString($content);

        return array_column(Json::decodeToArray($content)['data'], 'id');
    }
}
