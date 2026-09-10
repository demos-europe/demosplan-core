<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\JsonApi\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadUserData;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\JsonApiTest;

/**
 * Runtime proof for DPLAN-18271 that `recommendationEmbedded` resolves via
 * {@see \DemosEurope\DemosplanAddon\Contracts\ResourceType\PropertyAutoPathTrait} off
 * {@see StatementSegmentResourceType}'s own class docblock, with no dependency on
 * StatementResourceConfigBuilder / the legacy AbstractStatementResourceType property
 * mechanism used by a different resource type family.
 */
class StatementSegmentResourceTypeRecommendationEmbeddedTest extends JsonApiTest
{
    public function testRecommendationIsSubstitutedAndRecommendationEmbeddedStaysRaw(): void
    {
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $boilerplate = BoilerplateFactory::createOne(['procedure' => $procedure, 'text' => 'Aktueller Textbausteininhalt'])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $procedure]),
            'recommendation'           => "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen",
        ])->_real();

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_json_api_statement_segment', 'feature_segment_recommendation_edit']);

        $responseBody = $this->executeListRequest(
            StatementSegmentResourceType::getName(),
            $user,
            $procedure,
            Response::HTTP_OK,
            [
                'fields' => [StatementSegmentResourceType::getName() => 'recommendation,recommendationEmbedded'],
                'filter' => [
                    'byId' => [
                        'condition' => [
                            'path'  => 'id',
                            'value' => $segment->getId(),
                        ],
                    ],
                ],
            ]
        );

        self::assertCount(1, $responseBody['data']);
        $attributes = $responseBody['data'][0]['attributes'];

        self::assertSame('Hallo, Aktueller Textbausteininhalt mit Grüßen', $attributes['recommendation']);
        self::assertSame(
            "Hallo, <dp-boilerplate boilerplate-id=\"{$boilerplate->getId()}\"></dp-boilerplate> mit Grüßen",
            $attributes['recommendationEmbedded']
        );
    }

    public function testRecommendationEmbeddedIsAbsentWithoutExplicitFieldsRequest(): void
    {
        // Not a default field (readable(false, …)): must never leak into a response that
        // didn't explicitly ask for it via sparse fieldsets.
        $procedure = $this->getProcedureReference(LoadProcedureData::TESTPROCEDURE);
        $boilerplate = BoilerplateFactory::createOne(['procedure' => $procedure])->_real();
        $segment = SegmentFactory::createOne([
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $procedure]),
        ])->_real();

        $user = $this->getUserReference(LoadUserData::TEST_USER_PLANNER_AND_PUBLIC_INTEREST_BODY);
        $this->enablePermissions(['feature_json_api_statement_segment', 'feature_segment_recommendation_edit']);

        $responseBody = $this->executeListRequest(
            StatementSegmentResourceType::getName(),
            $user,
            $procedure,
            Response::HTTP_OK,
            [
                'filter' => [
                    'byId' => [
                        'condition' => [
                            'path'  => 'id',
                            'value' => $segment->getId(),
                        ],
                    ],
                ],
            ]
        );

        self::assertCount(1, $responseBody['data']);
        self::assertArrayNotHasKey('recommendationEmbedded', $responseBody['data'][0]['attributes']);
    }
}
