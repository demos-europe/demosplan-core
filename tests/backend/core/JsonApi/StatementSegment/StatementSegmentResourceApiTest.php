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

use DateTime;
use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

/**
 * Access rules (permissions, procedure scoping) and the single-item route of /api/3.0/StatementSegment.
 */
class StatementSegmentResourceApiTest extends AbstractApiTest
{
    use StatementSegmentApiTestTrait;

    private const UNKNOWN_SEGMENT_ID = '00000000-0000-0000-0000-000000000000';

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

    public function testGetCollectionIsAllowedWithAreaAdminStatementListPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segment = $this->createSegmentInProcedure($procedure);

        $response = $this->sendSegmentRequest(self::SEGMENT_COLLECTION_ROUTE, $procedure, ['area_admin_statement_list']);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([$segment->getId()], $this->getResponseIds($response));
    }

    public function testGetCollectionIsAllowedWithStatementsImportExcelPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segment = $this->createSegmentInProcedure($procedure);

        $response = $this->sendSegmentRequest(self::SEGMENT_COLLECTION_ROUTE, $procedure, ['feature_statements_import_excel']);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([$segment->getId()], $this->getResponseIds($response));
    }

    public function testGetItemReturnsSegmentAttributes(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segment = $this->createSegmentInProcedure($procedure, [
            'text'             => 'Laermschutzwand an der Hauptstrasse',
            'externId'         => 'S-4711',
            'orderInProcedure' => 7,
            'deadline'         => new DateTime('2030-05-17'),
        ]);

        $response = $this->sendSegmentRequest(self::SEGMENT_COLLECTION_ROUTE.'/'.$segment->getId(), $procedure);

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        self::assertSame($segment->getId(), $data['id']);
        self::assertSame('Laermschutzwand an der Hauptstrasse', $data['attributes']['text']);
        self::assertSame('S-4711', $data['attributes']['externId']);
        self::assertSame(7, $data['attributes']['orderInProcedure']);
        self::assertSame('2030-05-17', $data['attributes']['deadline']);
    }

    public function testGetItemReturnsNotFoundForUnknownId(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        $response = $this->sendSegmentRequest(self::SEGMENT_COLLECTION_ROUTE.'/'.self::UNKNOWN_SEGMENT_ID, $procedure);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetItemReturnsNotFoundForSegmentOfOtherProcedure(): void
    {
        $currentProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $foreignSegment = $this->createSegmentInProcedure($otherProcedure);

        $response = $this->sendSegmentRequest(self::SEGMENT_COLLECTION_ROUTE.'/'.$foreignSegment->getId(), $currentProcedure);

        self::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetItemIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $segment = $this->createSegmentInProcedure($procedure);

        $response = $this->sendSegmentRequest(self::SEGMENT_COLLECTION_ROUTE.'/'.$segment->getId(), $procedure, []);

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
        self::assertStringNotContainsString($segment->getId(), (string) $response->getContent());
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
}
