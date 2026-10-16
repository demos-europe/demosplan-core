<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Export;

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\SegmentExportCustomFieldFilter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\ParameterBag;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

/**
 * How {@see SegmentExportCustomFieldFilter} treats the `filter` parameter of the export request.
 * That the conditions really filter the segments is tested in {@see SegmentExportCustomFieldFilterApiTest}.
 */
class SegmentExportCustomFieldFilterTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?SegmentExportCustomFieldFilter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(SegmentExportCustomFieldFilter::class);
    }

    public function testHasCustomFieldConditions(): void
    {
        $tagEntry = ['condition' => ['path' => 'tags', 'value' => 'tag-id']];
        $customFieldEntry = ['condition' => ['path' => 'customField_'.Uuid::uuid4()->toString(), 'value' => 'option-id']];

        self::assertTrue($this->sut->hasCustomFieldConditions(['tag' => $tagEntry, 'customField' => $customFieldEntry]));
        self::assertFalse($this->sut->hasCustomFieldConditions(['tag' => $tagEntry]));
        self::assertFalse($this->sut->hasCustomFieldConditions([]));
    }

    public function testTakesTheCustomFieldConditionsOutOfTheFilterAndKeepsTheOthers(): void
    {
        $this->loginTestUser();
        $this->enablePermissions(['field_segments_custom_fields']);
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $tagEntry = ['condition' => ['path' => 'tags', 'value' => 'tag-id']];
        $query = new ParameterBag([
            'filter' => [
                'tag'         => $tagEntry,
                'customField' => ['condition' => ['path' => 'customField_'.$field->getId(), 'value' => $high]],
            ],
            'sort'   => 'orderInProcedure',
        ]);

        $conditions = $this->sut->extractConditions($query);

        self::assertCount(1, $conditions);
        self::assertSame(['tag' => $tagEntry], $query->all('filter'));
        self::assertSame('orderInProcedure', $query->get('sort'));
    }

    public function testRemovesTheFilterParameterWhenOnlyCustomFieldConditionsWereSent(): void
    {
        $this->loginTestUser();
        $this->enablePermissions(['field_segments_custom_fields']);
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $query = new ParameterBag(['filter' => [
            'customField' => ['condition' => ['path' => 'customField_'.$field->getId(), 'value' => $high]],
        ]]);

        $this->sut->extractConditions($query);

        self::assertFalse($query->has('filter'));
    }

    public function testCreatesOneConditionPerCustomField(): void
    {
        $this->loginTestUser();
        $this->enablePermissions(['field_segments_custom_fields']);
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise] = $this->getOptionIds($topics);
        $query = new ParameterBag(['filter' => [
            'a' => ['condition' => ['path' => 'customField_'.$priority->getId(), 'value' => $high]],
            'b' => ['condition' => ['path' => 'customField_'.$priority->getId(), 'value' => $low]],
            'c' => ['condition' => ['path' => 'customField_'.$topics->getId(), 'value' => $noise]],
        ]]);

        $conditions = $this->sut->extractConditions($query);

        self::assertCount(2, $conditions);
    }

    public function testIgnoresTheConditionsWithoutThePermission(): void
    {
        $this->loginTestUser();
        $this->disablePermissions(['field_segments_custom_fields']);
        $query = new ParameterBag(['filter' => [
            'customField' => ['condition' => ['path' => 'customField_'.Uuid::uuid4()->toString(), 'value' => Uuid::uuid4()->toString()]],
        ]]);

        $conditions = $this->sut->extractConditions($query);

        self::assertSame([], $conditions);
        self::assertFalse($query->has('filter'));
    }

    public function testReturnsNothingWithoutAFilter(): void
    {
        self::assertSame([], $this->sut->extractConditions(new ParameterBag(['sort' => 'orderInProcedure'])));
    }
}
