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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\RpcBulkEditor\RpcSegmentIdLoader;
use stdClass;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

/**
 * The segment list sends a selected custom field option to segment.load.id as an equality condition on the
 * raw `customFields` JSON column, grouped per field as `customField_<fieldId>`. RpcSegmentIdLoader replaces
 * these conditions with substring conditions: any selected option of a field matches, every field with a
 * selection has to match. A mistake there silently drops conditions, so the resulting ids are asserted.
 *
 * The loader is called directly: the `customFields` property of the segment resource type only exists with
 * the permission, which an HTTP test cannot enable early enough.
 */
class RpcSegmentIdLoaderCustomFieldFilterTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?Procedure $procedure = null;
    protected ?CustomFieldConfiguration $priority = null;
    protected ?CustomFieldConfiguration $topics = null;

    /** @var array<string, Segment>|null */
    private ?array $segments = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->loginTestUser();
        $this->enablePermissions(['feature_json_api_statement_segment', 'field_segments_custom_fields']);
        $this->procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        self::getContainer()->get(CurrentProcedureService::class)->setProcedure($this->procedure);

        $this->priority = $this->createSegmentCustomField($this->procedure, 'Priority', ['High', 'Medium', 'Low']);
        $this->topics = $this->createSegmentCustomField($this->procedure, 'Topics', ['Noise', 'Traffic', 'Housing'], multiSelect: true);
        [$high, $medium, $low] = $this->getOptionIds($this->priority);
        [$noise, $traffic, $housing] = $this->getOptionIds($this->topics);

        $this->segments = [
            'highNoise'     => $this->createSegment([$this->priority->getId() => $high, $this->topics->getId() => [$noise]]),
            'highHousing'   => $this->createSegment([$this->priority->getId() => $high, $this->topics->getId() => [$housing]]),
            'mediumTraffic' => $this->createSegment([$this->priority->getId() => $medium, $this->topics->getId() => [$noise, $traffic]]),
            'lowNoise'      => $this->createSegment([$this->priority->getId() => $low, $this->topics->getId() => [$noise]]),
            'none'          => $this->createSegment([]),
        ];
    }

    public function testWithoutACustomFieldFilterEverySegmentIsLoaded(): void
    {
        $result = $this->loadSegmentIds([]);

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'highHousing', 'mediumTraffic', 'lowNoise', 'none'), $result);
    }

    public function testASelectedOptionNarrowsDownTheSegments(): void
    {
        [$high] = $this->getOptionIds($this->priority);

        $result = $this->loadSegmentIds($this->customFieldFilter([$this->priority->getId() => [$high]]));

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'highHousing'), $result);
    }

    public function testAnySelectedOptionOfOneFieldMatches(): void
    {
        [$high, $medium] = $this->getOptionIds($this->priority);

        $result = $this->loadSegmentIds($this->customFieldFilter([$this->priority->getId() => [$high, $medium]]));

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'highHousing', 'mediumTraffic'), $result);
    }

    public function testAMultiSelectValueMatchesOnAnyOfItsOptions(): void
    {
        [, $traffic] = $this->getOptionIds($this->topics);

        $result = $this->loadSegmentIds($this->customFieldFilter([$this->topics->getId() => [$traffic]]));

        self::assertSame($this->idsOf('mediumTraffic'), $result);
    }

    public function testEveryFieldWithASelectionMustMatch(): void
    {
        [$high] = $this->getOptionIds($this->priority);
        [$noise] = $this->getOptionIds($this->topics);

        $result = $this->loadSegmentIds($this->customFieldFilter([
            $this->priority->getId() => [$high],
            $this->topics->getId()   => [$noise],
        ]));

        self::assertSame($this->idsOf('highNoise'), $result);
    }

    public function testOptionsOfAFieldAreOrCombinedAndFieldsAreAndCombined(): void
    {
        [$high, $medium] = $this->getOptionIds($this->priority);
        [, $traffic, $housing] = $this->getOptionIds($this->topics);

        $result = $this->loadSegmentIds($this->customFieldFilter([
            $this->priority->getId() => [$high, $medium],
            $this->topics->getId()   => [$traffic, $housing],
        ]));

        self::assertEqualsCanonicalizing($this->idsOf('highHousing', 'mediumTraffic'), $result);
    }

    public function testAnOptionOfTheFieldNobodyHoldsMatchesNothing(): void
    {
        $field = $this->createSegmentCustomField($this->procedure, 'Unused', ['Never', 'Chosen']);
        [$never] = $this->getOptionIds($field);

        $result = $this->loadSegmentIds($this->customFieldFilter([$field->getId() => [$never]]));

        self::assertSame([], $result);
    }

    /**
     * @dataProvider invalidSelectionProvider
     *
     * @param callable(self): array<string, list<mixed>> $optionIdsByFieldId
     */
    public function testAnInvalidSelectionIsRejectedInsteadOfDropped(callable $optionIdsByFieldId): void
    {
        $result = $this->execute($this->customFieldFilter($optionIdsByFieldId($this)));

        self::assertObjectHasProperty('error', $result[0]);
        self::assertSame(-32602, $result[0]->error['code']);
        self::assertObjectNotHasProperty('result', $result[0]);
    }

    /**
     * @return array<string, array{callable(self): array<string, list<mixed>>}>
     */
    public static function invalidSelectionProvider(): array
    {
        return [
            'option that is not a UUID'      => [fn (self $test): array => [$test->priority->getId() => ['High']]],
            'blank option'                   => [fn (self $test): array => [$test->priority->getId() => ['']]],
            'option that is not a string'    => [fn (self $test): array => [$test->priority->getId() => [42]]],
            'UUID that is no option at all'  => [fn (self $test): array => [$test->priority->getId() => ['99999999-9999-4999-8999-999999999999']]],
            'option of another field'        => [fn (self $test): array => [$test->priority->getId() => [$test->getOptionIds($test->topics)[0]]]],
            'field that does not exist'      => [fn (self $test): array => ['99999999-9999-4999-8999-999999999999' => [$test->getOptionIds($test->priority)[0]]]],
            'valid next to an invalid field' => [fn (self $test): array => [
                $test->priority->getId() => [$test->getOptionIds($test->priority)[0]],
                $test->topics->getId()   => ['Noise'],
            ]],
        ];
    }

    public function testOtherConditionsStayNextToTheCustomFieldConditions(): void
    {
        [$high] = $this->getOptionIds($this->priority);
        $filter = $this->customFieldFilter([$this->priority->getId() => [$high]]);
        $filter['externId'] = ['condition' => [
            'path'     => 'externId',
            'operator' => '=',
            'value'    => $this->segments['highHousing']->getExternId(),
        ]];

        $result = $this->loadSegmentIds($filter);

        self::assertSame($this->idsOf('highHousing'), $result);
    }

    public function testConditionsOfOtherGroupsAreNotTreatedAsCustomFieldConditions(): void
    {
        $filter = [
            'externIds' => ['group' => ['conjunction' => 'OR']],
            'first'     => ['condition' => ['path' => 'externId', 'operator' => '=', 'value' => $this->segments['highNoise']->getExternId(), 'memberOf' => 'externIds']],
            'second'    => ['condition' => ['path' => 'externId', 'operator' => '=', 'value' => $this->segments['lowNoise']->getExternId(), 'memberOf' => 'externIds']],
        ];

        $result = $this->loadSegmentIds($filter);

        self::assertEqualsCanonicalizing($this->idsOf('highNoise', 'lowNoise'), $result);
    }

    public function testWithoutThePermissionTheCustomFieldsCanNotBeFiltered(): void
    {
        [$high] = $this->getOptionIds($this->priority);
        $this->enablePermissions(['feature_json_api_statement_segment']);

        $result = $this->execute($this->customFieldFilter([$this->priority->getId() => [$high]]));

        self::assertObjectHasProperty('error', $result[0]);
        self::assertObjectNotHasProperty('result', $result[0]);
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return list<string>
     */
    private function loadSegmentIds(array $filter): array
    {
        $result = $this->execute($filter);

        self::assertObjectNotHasProperty('error', $result[0]);

        return array_values($result[0]->result);
    }

    /**
     * @param array<string, mixed> $filter
     *
     * @return array<int, object> the RPC results
     */
    private function execute(array $filter): array
    {
        $sut = self::getContainer()->get(RpcSegmentIdLoader::class);

        // like the JSON object the frontend sends, which is `{}` without a filter
        $params = (object) [
            'filter' => [] === $filter ? new stdClass() : json_decode(json_encode($filter, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR),
        ];

        return $sut->execute($this->procedure, (object) ['id' => 'segment-ids', 'params' => $params]);
    }

    /**
     * The filter the segment list sends: per field an OR group `customField_<fieldId>` and one equality
     * condition on `customFields` per selected option as its member.
     *
     * @param array<string, list<string>> $optionIdsByFieldId
     *
     * @return array<string, mixed>
     */
    private function customFieldFilter(array $optionIdsByFieldId): array
    {
        $filter = [];
        foreach ($optionIdsByFieldId as $fieldId => $optionIds) {
            $group = 'customField_'.$fieldId;
            $filter[$group] = ['group' => ['conjunction' => 'OR']];
            foreach ($optionIds as $optionId) {
                $filter[$group.'_'.$optionId] = ['condition' => [
                    'path'     => 'customFields',
                    'operator' => '=',
                    'value'    => $optionId,
                    'memberOf' => $group,
                ]];
            }
        }

        return $filter;
    }

    /**
     * @return list<string>
     */
    private function idsOf(string ...$names): array
    {
        return array_map(fn (string $name): string => $this->segments[$name]->getId(), $names);
    }

    /**
     * @param array<string, string|list<string>> $valuesByFieldId
     */
    private function createSegment(array $valuesByFieldId): Segment
    {
        $attributes = [
            'procedure'                => $this->procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $this->procedure])->_real(),
        ];
        if ([] !== $valuesByFieldId) {
            $attributes['customFields'] = $this->buildCustomFieldValues($valuesByFieldId);
        }

        return SegmentFactory::createOne($attributes)->_real();
    }
}
