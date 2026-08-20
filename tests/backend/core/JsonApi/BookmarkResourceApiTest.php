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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BookmarkFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\HashedQueryFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\StoredQuery\AssessmentTableQuery;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * The permission pair mirrors {@see \demosplan\DemosPlanCoreBundle\Api\Bookmark\BookmarkAccessChecker::isAvailable()}.
 */
class BookmarkResourceApiTest extends AbstractApiTest
{
    private const BOOKMARK_ROUTE = '/api/3.0/Bookmark';
    private const PERMISSIONS = ['area_statement_segmentation', 'feature_procedure_user_filter_sets'];

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
     * Drops the inherited X-JWT-Authorization header, which confuses the `main` firewall's lazy
     * authentication, and adds the JSON:API content type that writes need to be deserialized.
     */
    protected function getAdditionalHeaders(string $jwtToken, ?Procedure $procedure): array
    {
        $headers = ['CONTENT_TYPE' => 'application/vnd.api+json'];
        if (null !== $procedure) {
            $headers['HTTP_X_DEMOSPLAN_PROCEDURE_ID'] = $procedure->getId();
        }

        return $headers;
    }

    public function testCollectionReturnsOwnBookmarksOfCurrentProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        BookmarkFactory::new()->forSegmentList($user, $procedure->_real(), 'My tagged view', ['selectedColumns' => ['text', 'recommendation']])->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::BOOKMARK_ROUTE, 'GET', $user, $procedure->_real());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $data = Json::decodeToArray($response->getContent())['data'];
        self::assertCount(1, $data);
        self::assertSame('My tagged view', $data[0]['attributes']['name']);
        // Sorted on the way in, so the response is insensitive to the order the columns were sent in.
        self::assertSame(['recommendation', 'text'], $data[0]['attributes']['viewSettings']['selectedColumns']);
    }

    public function testCollectionExcludesBookmarksOfOtherUsers(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $otherUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        BookmarkFactory::new()->forSegmentList($otherUser, $procedure->_real(), 'Not mine')->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::BOOKMARK_ROUTE, 'GET', $user, $procedure->_real());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(0, Json::decodeToArray($response->getContent())['data']);
    }

    public function testCollectionExcludesBookmarksOfOtherProcedures(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        BookmarkFactory::new()->forSegmentList($user, $otherProcedure->_real(), 'Elsewhere')->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::BOOKMARK_ROUTE, 'GET', $user, $procedure->_real());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(0, Json::decodeToArray($response->getContent())['data']);
    }

    /**
     * The bookmark table is shared with the assessment table, whose saved filters are the same entity.
     * They are told apart by the format inside the referenced query, so this is the test that proves
     * the format condition in the access checker actually bites.
     */
    public function testCollectionExcludesAssessmentTableBookmarks(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);

        $assessmentQuery = new AssessmentTableQuery();
        $assessmentQuery->setProcedureId($procedure->getId());
        $assessmentTableSet = HashedQueryFactory::createOne([
            'hash'        => $assessmentQuery->getHash(),
            'procedure'   => $procedure,
            'storedQuery' => $assessmentQuery,
        ]);
        BookmarkFactory::createOne([
            'filterSet' => $assessmentTableSet,
            'name'      => 'Assessment table filter set',
            'procedure' => $procedure,
            'user'      => $user,
        ]);

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::BOOKMARK_ROUTE, 'GET', $user, $procedure->_real());

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertCount(0, Json::decodeToArray($response->getContent())['data']);
    }

    public function testPostCreatesBookmarkForTheGivenQueryHash(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $hashedQuery = HashedQueryFactory::new()->forSegmentList($procedure->_real(), ['sorting' => '-deadline'])->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::BOOKMARK_ROUTE,
            'POST',
            $user,
            $procedure->_real(),
            ['data' => ['type' => 'Bookmark', 'attributes' => ['name' => 'Saved view', 'queryHash' => $hashedQuery->getHash()]]]
        );

        self::assertSame(Response::HTTP_CREATED, $response->getStatusCode(), $response->getContent());
        $attributes = Json::decodeToArray($response->getContent())['data']['attributes'];
        self::assertSame('Saved view', $attributes['name']);
        self::assertSame($hashedQuery->getHash(), $attributes['queryHash']);
        self::assertSame('-deadline', $attributes['viewSettings']['sorting']);
    }

    /**
     * API Platform operations run through its own MainController, so exceptions they raise never reach
     * the APIController branch of {@see \demosplan\DemosPlanCoreBundle\EventListener\ExceptionEventSubscriber::handleException()}.
     * Without the branch that recognises them they fall through to the HTML error page, and the client
     * receives a 302 redirect it cannot parse. These three cases pin that down per status code.
     */
    public function testPostWithUnknownQueryHashIsRejected(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::BOOKMARK_ROUTE,
            'POST',
            $user,
            $procedure->_real(),
            ['data' => ['type' => 'Bookmark', 'attributes' => ['name' => 'Saved view', 'queryHash' => 'doesNotExist']]]
        );

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode(), $response->getContent());
        self::assertSame(Response::HTTP_BAD_REQUEST, Json::decodeToArray($response->getContent())['errors'][0]['status']);
    }

    public function testDeleteOfAnotherUsersBookmarkIsNotFound(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $otherUser = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $bookmark = BookmarkFactory::new()->forSegmentList($otherUser, $procedure->_real(), 'Not mine')->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::BOOKMARK_ROUTE.'/'.$bookmark->getId(),
            'DELETE',
            $user,
            $procedure->_real()
        );

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode(), $response->getContent());
        BookmarkFactory::repository()->assert()->count(1);
    }

    public function testCollectionIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);

        // Deliberately no enablePermissions() call.
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::BOOKMARK_ROUTE, 'GET', $user, $procedure->_real());

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode(), $response->getContent());
    }

    public function testPostWithDuplicateNameIsRejected(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        BookmarkFactory::new()->forSegmentList($user, $procedure->_real(), 'Taken')->create();
        $hashedQuery = HashedQueryFactory::new()->forSegmentList($procedure->_real(), [], [], 'other')->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::BOOKMARK_ROUTE,
            'POST',
            $user,
            $procedure->_real(),
            ['data' => ['type' => 'Bookmark', 'attributes' => ['name' => 'Taken', 'queryHash' => $hashedQuery->getHash()]]]
        );

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode(), $response->getContent());
    }

    public function testDeleteRemovesTheBookmark(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $bookmark = BookmarkFactory::new()->forSegmentList($user, $procedure->_real(), 'Disposable')->create();

        $this->enablePermissions(self::PERMISSIONS);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::BOOKMARK_ROUTE.'/'.$bookmark->getId(),
            'DELETE',
            $user,
            $procedure->_real()
        );

        self::assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode(), $response->getContent());
        self::assertSame('', $response->getContent());
        BookmarkFactory::repository()->assert()->count(0);
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
