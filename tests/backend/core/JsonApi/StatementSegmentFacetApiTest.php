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
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\TagTopicFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\AbstractApiTest;

class StatementSegmentFacetApiTest extends AbstractApiTest
{
    private const FACET_ROUTE = '/api/3.0/StatementSegmentFacet';

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

    public function testTagFacetCountsExcludeItsOwnFilter(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        // Tag's topic has its own `procedure` default (a fresh one per Foundry's
        // TagTopicFactory::defaults()), so it must be overridden explicitly to match - the
        // tags/assignee/place facets are enumerated via `tag.topic.procedure.id = :procedureId`.
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tagA = TagFactory::createOne(['title' => 'Schallschutz', 'topic' => $topic]);
        $tagB = TagFactory::createOne(['title' => 'Positiv, Zustimmung', 'topic' => $topic]);

        // parentStatementOfSegment's own procedure must explicitly match, since both the
        // facet filter (`parentStatementOfSegment.procedure.id`) and the access conditions
        // scope by that path, not by the segment's own `procedure` property.
        $parentStatement = StatementFactory::new(['procedure' => $procedure]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tagA]]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tagA]]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tagB]]);

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_admin_statement_list']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=tags&parentStatementOfSegment.procedure.id='.$procedure->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        $counts = array_combine(array_column($data, 'id'), array_column(array_column($data, 'attributes'), 'count'));

        self::assertSame(2, $counts[$tagA->getId()]);
        self::assertSame(1, $counts[$tagB->getId()]);

        // Now filter down to tagB's segment via an unrelated facet (place is not filtered here,
        // but selecting tagB itself as an *active* filter must not zero out its own count —
        // it should still be reported, just with `selected: true`.
        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=tags&parentStatementOfSegment.procedure.id='.$procedure->getId().'&tags.id='.$tagB->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        $tagBEntry = current(array_filter($data, static fn (array $row): bool => $row['id'] === $tagB->getId()));

        self::assertNotFalse($tagBEntry);
        self::assertSame(1, $tagBEntry['attributes']['count']);
        self::assertTrue($tagBEntry['attributes']['selected']);
    }

    public function testTagWithNoMatchingSegmentsStillAppearsWithZeroCount(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $usedTag = TagFactory::createOne(['title' => 'Schallschutz', 'topic' => $topic]);
        // Belongs to the same procedure, but no segment ever references it - the facet must
        // still enumerate it (with count 0), not silently drop it, so users can see and select
        // options that don't currently match anything.
        $unusedTag = TagFactory::createOne(['title' => 'Unused', 'topic' => $topic]);

        $parentStatement = StatementFactory::new(['procedure' => $procedure]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$usedTag]]);

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_admin_statement_list']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=tags&parentStatementOfSegment.procedure.id='.$procedure->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        $counts = array_combine(array_column($data, 'id'), array_column(array_column($data, 'attributes'), 'count'));

        self::assertSame(1, $counts[$usedTag->getId()]);
        self::assertArrayHasKey($unusedTag->getId(), $counts);
        self::assertSame(0, $counts[$unusedTag->getId()]);
    }

    public function testTagsAreGroupedByTopic(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['title' => 'Lärm', 'procedure' => $procedure]);
        $tagA = TagFactory::createOne(['title' => 'Schallschutz', 'topic' => $topic]);
        $tagB = TagFactory::createOne(['title' => 'Baulärm', 'topic' => $topic]);

        $parentStatement = StatementFactory::new(['procedure' => $procedure]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tagA]]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tagB]]);

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_admin_statement_list']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=tags&parentStatementOfSegment.procedure.id='.$procedure->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        $byId = array_combine(array_column($data, 'id'), array_column($data, 'attributes'));

        self::assertSame($topic->getId(), $byId[$tagA->getId()]['groupId']);
        self::assertSame('Lärm', $byId[$tagA->getId()]['groupLabel']);
        self::assertSame($topic->getId(), $byId[$tagB->getId()]['groupId']);
    }

    public function testAssigneeFacetIncludesUnassignedCount(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();

        // `assignee` is enumerated via ProcedureService::getAuthorizedUsers(), which is scoped to
        // the *logged-in* user's own Orga (filtered by planning-agency/hearing-authority role) -
        // not any arbitrary user. Reusing the logged-in test user as the assignee guarantees it
        // passes that check, rather than needing to fabricate a matching Orga/role for a
        // separate user.
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $assignee = $user;

        $parentStatement = StatementFactory::new(['procedure' => $procedure]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'assignee' => $assignee]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'assignee' => null]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'assignee' => null]);

        $this->enablePermissions(['area_admin_statement_list']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=assignee&parentStatementOfSegment.procedure.id='.$procedure->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        $counts = array_combine(array_column($data, 'id'), array_column(array_column($data, 'attributes'), 'count'));

        self::assertSame(1, $counts[$assignee->getId()]);
        self::assertSame(2, $counts['unassigned']);
    }

    public function testSearchPhraseNarrowsCounts(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $topic = TagTopicFactory::createOne(['procedure' => $procedure]);
        $tag = TagFactory::createOne(['title' => 'Schallschutz', 'topic' => $topic]);

        $parentStatement = StatementFactory::new(['procedure' => $procedure]);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tag], 'text' => 'Lärmschutzwand am Bahndamm']);
        SegmentFactory::createOne(['procedure' => $procedure, 'parentStatementOfSegment' => $parentStatement, 'tags' => [$tag], 'text' => 'Unrelated content about parking spaces']);

        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->enablePermissions(['area_admin_statement_list']);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=tags&parentStatementOfSegment.procedure.id='.$procedure->getId().'&searchPhrase=Bahndamm',
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        $content = $response->getContent();
        self::assertIsString($content);
        $data = Json::decodeToArray($content)['data'];
        $counts = array_combine(array_column($data, 'id'), array_column(array_column($data, 'attributes'), 'count'));

        self::assertSame(1, $counts[$tag->getId()]);
    }

    public function testFacetIsDeniedWithoutPermission(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create();
        $user = $this->getUserReference(LoadUserData::TEST_USER_FP_ONLY);
        $this->loginUserForApiPlatform($user);

        $response = $this->sendRequest(
            self::FACET_ROUTE.'?facet=tags&parentStatementOfSegment.procedure.id='.$procedure->getId(),
            'GET',
            $user,
            $procedure
        );

        self::assertSame(Response::HTTP_FORBIDDEN, $response->getStatusCode());
    }

    protected function getServerParameters(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/vnd.api+json',
        ];
    }
}
