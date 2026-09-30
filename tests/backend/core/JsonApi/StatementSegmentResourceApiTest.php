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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementMetaFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Statement;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class StatementSegmentResourceApiTest extends AbstractApiTest
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

    public function testGetCollectionExcludesSegmentsOfOtherProcedures(): void
    {
        $currentProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $ownSegment = $this->createSegmentInProcedure($currentProcedure);
        $foreignSegment = $this->createSegmentInProcedure($otherProcedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE, 'GET', $user, $currentProcedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([$ownSegment->getId()], $this->getResponseIds($response));
        self::assertStringNotContainsString($foreignSegment->getId(), (string) $response->getContent());
    }

    /**
     * Segments of a procedure listed in allowedSegmentAccessProcedures are only reachable if the
     * current user also owns that procedure — ProcedureAccessEvaluator::filterNonOwnedProcedureIds()
     * drops the rest, so merely being listed must not widen access.
     */
    public function testGetCollectionExcludesAllowedSegmentAccessProcedureNotOwnedByUser(): void
    {
        $allowedProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $currentProcedure = $this->createProcedureAllowingSegmentAccessTo($allowedProcedure->_real());
        $ownSegment = $this->createSegmentInProcedure($currentProcedure);
        $allowedSegment = $this->createSegmentInProcedure($allowedProcedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE, 'GET', $user, $currentProcedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([$ownSegment->getId()], $this->getResponseIds($response));
        self::assertStringNotContainsString($allowedSegment->getId(), (string) $response->getContent());
    }

    /**
     * Segments of a procedure that is both listed in allowedSegmentAccessProcedures and owned by
     * the current user are returned alongside the current procedure's own segments.
     */
    public function testGetCollectionIncludesOwnedAllowedSegmentAccessProcedure(): void
    {
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        // Logged in before the organisation is read: hydrating it first leaves an unserializable
        // lazy proxy on the user, which KernelBrowser::loginUser() cannot put into the session.
        $this->loginUserForApiPlatform($user);
        $allowedProcedure = ProcedureFactory::new()
            ->withDefaultSettings()
            ->create(['orga' => $user->getOrga()]);
        $currentProcedure = $this->createProcedureAllowingSegmentAccessTo($allowedProcedure->_real());
        $ownSegment = $this->createSegmentInProcedure($currentProcedure);
        $allowedSegment = $this->createSegmentInProcedure($allowedProcedure);

        $response = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE, 'GET', $user, $currentProcedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $returnedIds = $this->getResponseIds($response);
        self::assertContains($ownSegment->getId(), $returnedIds);
        self::assertContains($allowedSegment->getId(), $returnedIds);
    }

    /**
     * Without a procedure header AccessChecker::getAccessConditions() falls back to false(), which
     * yields an empty collection rather than an error.
     */
    public function testGetCollectionIsEmptyWithoutCurrentProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segment = $this->createSegmentInProcedure($procedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE, 'GET', $user, null);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([], $this->getResponseIds($response));
        self::assertStringNotContainsString($segment->getId(), (string) $response->getContent());
    }

    public function testGetCollectionIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segment = $this->createSegmentInProcedure($procedure);
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE, 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringNotContainsString($segment->getId(), (string) $response->getContent());
    }

    /**
     * `order[submitter]` sorts by the first line the "Einreicher*in" column shows: the parent
     * statement's author name, else its submit name, else its organisation name.
     */
    public function testGetCollectionSortsBySubmitterWithDisplayFallbackChain(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        $this->loginUserForApiPlatform($user);
        $shownAsBerta = $this->createSegmentWithSubmitter($procedure, 'Berta', 'Zoe', 'Amt Z');
        $shownAsAnton = $this->createSegmentWithSubmitter($procedure, '', 'Anton', 'Amt Y');
        $shownAsDeichverband = $this->createSegmentWithSubmitter($procedure, '', '', 'Deichverband');
        $shownAsClara = $this->createSegmentWithSubmitter($procedure, 'Clara', 'Anna', 'Amt X');

        $ascending = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE.'?order[submitter]=asc', 'GET', $user, $procedure);
        $descending = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE.'?order[submitter]=desc', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $ascending->getStatusCode());
        self::assertSame(
            [$shownAsAnton->getId(), $shownAsBerta->getId(), $shownAsClara->getId(), $shownAsDeichverband->getId()],
            $this->getResponseIds($ascending)
        );
        self::assertSame(
            [$shownAsDeichverband->getId(), $shownAsClara->getId(), $shownAsBerta->getId(), $shownAsAnton->getId()],
            $this->getResponseIds($descending)
        );
    }

    /**
     * `order[externId]` uses natural order on the parent statement ID and then on the segment ID,
     * so "M2-1" comes before "M10-1" and "M1-2" before "M1-10" despite plain string order.
     */
    public function testGetCollectionSortsByExternIdInNaturalOrder(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        $this->loginUserForApiPlatform($user);
        $m10 = StatementFactory::createOne(['procedure' => $procedure, 'externId' => 'M10'])->_real();
        $m2 = StatementFactory::createOne(['procedure' => $procedure, 'externId' => 'M2'])->_real();
        $m1 = StatementFactory::createOne(['procedure' => $procedure, 'externId' => 'M1'])->_real();
        $m10s1 = $this->createSegmentWithExternId($procedure, $m10, 'M10-1');
        $m2s1 = $this->createSegmentWithExternId($procedure, $m2, 'M2-1');
        $m1s10 = $this->createSegmentWithExternId($procedure, $m1, 'M1-10');
        $m1s2 = $this->createSegmentWithExternId($procedure, $m1, 'M1-2');

        $ascending = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE.'?order[externId]=asc', 'GET', $user, $procedure);
        $descending = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE.'?order[externId]=desc', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $ascending->getStatusCode());
        self::assertSame(
            [$m1s2->getId(), $m1s10->getId(), $m2s1->getId(), $m10s1->getId()],
            $this->getResponseIds($ascending)
        );
        self::assertSame(
            [$m10s1->getId(), $m2s1->getId(), $m1s10->getId(), $m1s2->getId()],
            $this->getResponseIds($descending)
        );
    }

    /**
     * "Schritt" sorts by the workflow position of the place, not alphabetically by its name.
     */
    public function testGetCollectionSortsByPlaceSortIndex(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['feature_json_api_statement_segment']);
        $this->loginUserForApiPlatform($user);
        $third = $this->createSegmentInPlace($procedure, 'Alpha', 3);
        $first = $this->createSegmentInPlace($procedure, 'Zeta', 1);
        $second = $this->createSegmentInPlace($procedure, 'Beta', 2);

        $ascending = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE.'?order[place.sortIndex]=asc', 'GET', $user, $procedure);
        $descending = $this->sendRequest(self::SEGMENT_COLLECTION_ROUTE.'?order[place.sortIndex]=desc', 'GET', $user, $procedure);

        self::assertSame(Response::HTTP_OK, $ascending->getStatusCode());
        self::assertSame([$first->getId(), $second->getId(), $third->getId()], $this->getResponseIds($ascending));
        self::assertSame([$third->getId(), $second->getId(), $first->getId()], $this->getResponseIds($descending));
    }

    /**
     * SegmentFactory only applies an explicit `procedure` to the segment row itself; its default
     * parentStatementOfSegment keeps the factory's own procedure. The parent statement is created
     * explicitly so segment and parent statement consistently belong to the same procedure.
     */
    private function createSegmentInProcedure(Procedure $procedure): Segment
    {
        return SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $procedure])->_real(),
        ])->_real();
    }

    private function createSegmentWithSubmitter(Procedure $procedure, string $authorName, string $submitName, string $orgaName): Segment
    {
        $parentStatement = StatementFactory::createOne(['procedure' => $procedure])->_real();
        StatementMetaFactory::createOne([
            'statement'  => $parentStatement,
            'authorName' => $authorName,
            'submitName' => $submitName,
            'orgaName'   => $orgaName,
        ]);

        return SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => $parentStatement,
        ])->_real();
    }

    private function createSegmentWithExternId(Procedure $procedure, Statement $parentStatement, string $externId): Segment
    {
        return SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => $parentStatement,
            'externId'                 => $externId,
        ])->_real();
    }

    private function createSegmentInPlace(Procedure $procedure, string $placeName, int $sortIndex): Segment
    {
        $place = PlaceFactory::createOne([
            'procedure' => $procedure,
            'name'      => $placeName,
            'sortIndex' => $sortIndex,
        ])->_real();

        return SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $procedure])->_real(),
            'place'                    => $place,
        ])->_real();
    }

    private function createProcedureAllowingSegmentAccessTo(Procedure $allowedProcedure): Procedure
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $procedure->_real()->getSettings()->setAllowedSegmentAccessProcedures(
            new ArrayCollection([$allowedProcedure])
        );
        $procedure->_save();

        return $procedure->_real();
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

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
