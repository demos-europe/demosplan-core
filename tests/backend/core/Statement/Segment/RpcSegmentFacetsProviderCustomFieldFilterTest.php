<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValue;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\CustomFields\CustomFieldConfigurationFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\EventDispatcher\TraceableEventDispatcher;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\JsonApiEsService;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiActionService;
use demosplan\DemosPlanCoreBundle\Logic\JsonApiPaginationParser;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Rpc\RpcErrorGenerator;
use demosplan\DemosPlanCoreBundle\Logic\Segment\RpcSegmentFacetsProvider;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use demosplan\DemosPlanCoreBundle\Services\ApiResourceService;
use demosplan\DemosPlanCoreBundle\ValueObject\ApiListResult;
use EDT\JsonApi\RequestHandling\JsonApiSortingParser;
use EDT\Querying\ConditionParsers\Drupal\DrupalFilterParser;
use stdClass;
use Tests\Base\FunctionalTestCase;

/**
 * The facets of the segment list count over the segments that match the filter of the request. A custom
 * field selection is a normal filter condition on the `customFields` property of the segment resource,
 * the same way a tag selection is a condition on `tags`. So the other facets are aware of it without
 * any special handling.
 *
 * The Elasticsearch search is replaced and only receives the ids the database found for the filter,
 * so these tests need no running Elasticsearch.
 */
class RpcSegmentFacetsProviderCustomFieldFilterTest extends FunctionalTestCase
{
    private const CONTAINS = 'STRING_CONTAINS_CASE_INSENSITIVE';

    private ?Procedure $procedure = null;
    private ?CustomFieldConfiguration $priority = null;
    private ?CustomFieldConfiguration $topic = null;

    /** @var list<string>|null the ids the Elasticsearch search got to see, per search */
    private ?array $searchedIds = null;

    /** @var array<string, Segment>|null */
    private ?array $segments = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginTestUser();
        $this->enablePermissions(['area_statement_segmentation', 'feature_json_api_statement_segment', 'field_segments_custom_fields']);
        $this->procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        self::getContainer()->get(CurrentProcedureService::class)->setProcedure($this->procedure);

        $this->priority = $this->createCustomField('Priority', ['High', 'Low']);
        $this->topic = $this->createCustomField('Topic', ['Noise', 'Traffic']);
        [$high, $low] = $this->getOptionIds($this->priority);
        [$noise] = $this->getOptionIds($this->topic);

        $this->segments = [
            'highNoise' => $this->createSegment([$this->priority->getId() => $high, $this->topic->getId() => $noise]),
            'high'      => $this->createSegment([$this->priority->getId() => $high]),
            'low'       => $this->createSegment([$this->priority->getId() => $low]),
            'none'      => $this->createSegment([]),
        ];
    }

    public function testWithoutACustomFieldFilterTheFacetsSeeEverySegment(): void
    {
        $this->execute([]);

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'high', 'low', 'none'), $this->getSearchedIds());
    }

    public function testASelectedOptionNarrowsDownTheSegmentsTheFacetsSee(): void
    {
        [$high] = $this->getOptionIds($this->priority);

        $this->execute(['priorityHigh' => $this->contains($high)]);

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'high'), $this->getSearchedIds());
    }

    public function testAnyOptionOfAGroupMatches(): void
    {
        [$high, $low] = $this->getOptionIds($this->priority);

        $this->execute([
            'priority'     => ['group' => ['conjunction' => 'OR']],
            'priorityHigh' => $this->contains($high, 'priority'),
            'priorityLow'  => $this->contains($low, 'priority'),
        ]);

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'high', 'low'), $this->getSearchedIds());
    }

    public function testEveryConditionOutsideOfAGroupMustMatch(): void
    {
        [$high] = $this->getOptionIds($this->priority);
        [$noise] = $this->getOptionIds($this->topic);

        $this->execute(['priorityHigh' => $this->contains($high), 'topicNoise' => $this->contains($noise)]);

        self::assertEqualsCanonicalizing($this->idsOf('highNoise'), $this->getSearchedIds());
    }

    public function testACustomFieldFilterWorksTogetherWithTheOtherFilters(): void
    {
        [$high] = $this->getOptionIds($this->priority);

        $this->execute([
            'priorityHigh' => $this->contains($high),
            'externId'     => ['condition' => ['path' => 'externId', 'operator' => '=', 'value' => $this->segments['high']->getExternId()]],
        ]);

        self::assertEqualsCanonicalizing($this->idsOf('high'), $this->getSearchedIds());
    }

    public function testAnOptionNobodyHasMatchesNothing(): void
    {
        $this->execute(['unknown' => $this->contains('99999999-9999-4999-8999-999999999999')]);

        self::assertSame([], $this->getSearchedIds());
    }

    public function testWithoutThePermissionTheCustomFieldsCanNotBeFiltered(): void
    {
        [$high] = $this->getOptionIds($this->priority);
        $this->enablePermissions(['area_statement_segmentation', 'feature_json_api_statement_segment']);

        $result = $this->execute(['priorityHigh' => $this->contains($high)]);

        self::assertNull($this->searchedIds, 'The search must not run.');
        self::assertArrayHasKey('error', (array) $result[0]);
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array<int, object> the RPC results
     */
    private function execute(array $filter): array
    {
        $container = self::getContainer();

        $esService = $this->createMock(JsonApiEsService::class);
        $esService->method('getEsFilteredObjects')->willReturnCallback(
            function (...$arguments): ApiListResult {
                $this->searchedIds = $arguments[1];

                return new ApiListResult([], [], [], 0);
            }
        );
        $actionService = new JsonApiActionService(
            $container->get(TraceableEventDispatcher::class),
            $esService,
            $container->get(JsonApiPaginationParser::class),
            $container->get(DrupalFilterParser::class),
            $container->get(JsonApiSortingParser::class),
        );
        $sut = new RpcSegmentFacetsProvider(
            $container->get(ApiResourceService::class),
            $container->get(CurrentUserInterface::class),
            $container->get(DrupalFilterParser::class),
            $actionService,
            $container->get(RpcErrorGenerator::class),
            $container->get(StatementSegmentResourceType::class),
        );

        $params = (object) [
            'path'         => 'tags',
            // like the JSON object the frontend sends, which is `{}` without a filter
            'filter'       => [] === $filter ? new stdClass() : json_decode(json_encode($filter, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
            'searchPhrase' => null,
        ];

        return $sut->execute($this->procedure, (object) ['id' => 'facets', 'params' => $params]);
    }

    /**
     * @return list<string>
     */
    private function getSearchedIds(): array
    {
        self::assertNotNull($this->searchedIds, 'The search did not run.');

        return array_values($this->searchedIds);
    }

    /**
     * @return list<string>
     */
    private function idsOf(string ...$names): array
    {
        return array_map(fn (string $name): string => $this->segments[$name]->getId(), $names);
    }

    /**
     * A selected custom field option, as the filter panel sends a selected option of any facet.
     *
     * @return array<string, array<string, string>>
     */
    private function contains(string $optionId, ?string $memberOf = null): array
    {
        $condition = ['path' => 'customFields', 'operator' => self::CONTAINS, 'value' => $optionId];
        if (null !== $memberOf) {
            $condition['memberOf'] = $memberOf;
        }

        return ['condition' => $condition];
    }

    /**
     * @param list<string> $optionLabels
     */
    private function createCustomField(string $name, array $optionLabels): CustomFieldConfiguration
    {
        return CustomFieldConfigurationFactory::new()
            ->withRelatedProcedure($this->procedure)
            ->withRelatedTargetEntity('SEGMENT')
            ->asRadioButton($name, options: $optionLabels)
            ->create()
            ->_real();
    }

    /**
     * @return list<string> the option ids, in the order the options were created
     */
    private function getOptionIds(CustomFieldConfiguration $customField): array
    {
        return array_map(
            static fn ($option): string => $option->getId(),
            $customField->getConfiguration()->getOptions()
        );
    }

    /**
     * @param array<string, string> $optionIdByFieldId
     */
    private function createSegment(array $optionIdByFieldId): Segment
    {
        $attributes = [
            'procedure'                => $this->procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $this->procedure])->_real(),
        ];
        if ([] !== $optionIdByFieldId) {
            $values = new CustomFieldValuesList();
            foreach ($optionIdByFieldId as $fieldId => $optionId) {
                $value = new CustomFieldValue();
                $value->setId($fieldId);
                $value->setValue($optionId);
                $values->addCustomFieldValue($value);
            }
            $attributes['customFields'] = $values;
        }

        return SegmentFactory::createOne($attributes)->_real();
    }
}
