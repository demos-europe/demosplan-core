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

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class TagResourceApiTest extends AbstractApiTest
{
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
     * {@see loginUserForApiPlatform()}. The inherited {@see AbstractApiTest::getAdditionalHeaders()}
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

    public function testGetReturnsTagScopedToCurrentProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tag = TagFactory::createOne(['topic' => $topic, 'title' => 'Laermschutz']);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Tag/'.$tag->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Laermschutz', $response->getContent());
    }

    public function testGetIncludesDefaultAssignee(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $assignee = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $tag = TagFactory::createOne(['topic' => $topic, 'defaultAssignee' => $assignee]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Tag/'.$tag->getId().'?include=defaultAssignee',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $decoded = Json::decodeToArray($content);

        self::assertSame($assignee->getId(), $decoded['data']['relationships']['defaultAssignee']['data']['id']);
        self::assertSame(
            $assignee->getFirstname(),
            $decoded['included'][0]['attributes']['firstname']
        );
    }

    public function testGetCollectionIsSortedBySortIndexAscending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tagC = TagFactory::createOne(['topic' => $topic, 'title' => 'C', 'sortIndex' => 30]);
        $tagA = TagFactory::createOne(['topic' => $topic, 'title' => 'A', 'sortIndex' => 10]);
        $tagB = TagFactory::createOne(['topic' => $topic, 'title' => 'B', 'sortIndex' => 20]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Tag?sort=sortIndex',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];

        self::assertSame(
            [$tagA->getId(), $tagB->getId(), $tagC->getId()],
            array_column($data, 'id')
        );
    }

    public function testGetCollectionIsSortedByTitleAscending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tagZ = TagFactory::createOne(['topic' => $topic, 'title' => 'Zaun']);
        $tagA = TagFactory::createOne(['topic' => $topic, 'title' => 'Abwasser']);
        $tagM = TagFactory::createOne(['topic' => $topic, 'title' => 'Muelltrennung']);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Tag?sort=title',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];

        self::assertSame(
            [$tagA->getId(), $tagM->getId(), $tagZ->getId()],
            array_column($data, 'id')
        );
    }

    public function testGetCollectionIsPaginated(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tagA = TagFactory::createOne(['topic' => $topic, 'title' => 'A', 'sortIndex' => 10]);
        $tagB = TagFactory::createOne(['topic' => $topic, 'title' => 'B', 'sortIndex' => 20]);
        TagFactory::createOne(['topic' => $topic, 'title' => 'C', 'sortIndex' => 30]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Tag?sort=sortIndex&pagination=true&page=1&itemsPerPage=2',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $decoded = Json::decodeToArray($content);

        self::assertSame(3, $decoded['meta']['totalItems']);
        self::assertSame(
            [$tagA->getId(), $tagB->getId()],
            array_column($decoded['data'], 'id')
        );
    }

    public function testGetCollectionReturnsAllTagsWithoutExplicitPagination(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tagA = TagFactory::createOne(['topic' => $topic, 'title' => 'A', 'sortIndex' => 10]);
        $tagB = TagFactory::createOne(['topic' => $topic, 'title' => 'B', 'sortIndex' => 20]);
        $tagC = TagFactory::createOne(['topic' => $topic, 'title' => 'C', 'sortIndex' => 30]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Tag?sort=sortIndex',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $decoded = Json::decodeToArray($content);

        self::assertSame(['totalItems' => 3], $decoded['meta']);
        self::assertSame(
            [$tagA->getId(), $tagB->getId(), $tagC->getId()],
            array_column($decoded['data'], 'id')
        );
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
