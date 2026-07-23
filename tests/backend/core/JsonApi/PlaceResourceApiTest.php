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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class PlaceResourceApiTest extends AbstractApiTest
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

    public function testGetReturnsPlaceScopedToCurrentProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $place = PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'Step 1']);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Place/'.$place->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertStringContainsString('Step 1', $response->getContent());
    }

    /**
     * AccessDeniedHttpException thrown from an ApiPlatform provider is not turned into a
     * JSON:API error body — {@see \demosplan\DemosPlanCoreBundle\EventListener\ExceptionEventSubscriber::handleException()}
     * only special-cases the legacy APIController stack, so this falls through to the generic
     * HTML error redirect. Asserting the current (imperfect) behavior rather than the ideal one.
     */
    public function testGetIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $place = PlaceFactory::createOne(['procedure' => $procedure]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Place/'.$place->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_FOUND, $response->getStatusCode());
    }

    public function testGetCollectionIsSortedBySortIndexAscending(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $placeC = PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'C', 'sortIndex' => 30]);
        $placeA = PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'A', 'sortIndex' => 10]);
        $placeB = PlaceFactory::createOne(['procedure' => $procedure, 'name' => 'B', 'sortIndex' => 20]);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_statement_segmentation']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            '/api/3.0/Place?sort=sortIndex',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];

        self::assertSame(
            [$placeA->getId(), $placeB->getId(), $placeC->getId()],
            array_column($data, 'id')
        );
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
