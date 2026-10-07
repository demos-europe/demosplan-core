<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\CustomField;

use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\CustomFields\CustomFieldConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\SegmentCustomFieldUsageCounter;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

class SegmentCustomFieldUsageCounterTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?SegmentCustomFieldUsageCounter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(SegmentCustomFieldUsageCounter::class);
    }

    public function testCountOptionUsageCountsSingleAndMultiSelectValues(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $single = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $multi = $this->createSegmentCustomField($procedure, 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($single);
        [$noise, $traffic] = $this->getOptionIds($multi);
        $segments = [
            $this->createSegment($procedure, [$single->getId() => $high, $multi->getId() => [$noise, $traffic]]),
            $this->createSegment($procedure, [$single->getId() => $high, $multi->getId() => [$noise]]),
            $this->createSegment($procedure, [$single->getId() => $low]),
            $this->createSegment($procedure, null),
        ];

        self::assertSame([$high => 2, $low => 1], $this->sut->countOptionUsage($segments, $single->getId()));
        self::assertSame([$noise => 2, $traffic => 1], $this->sut->countOptionUsage($segments, $multi->getId()));
    }

    public function testCountOptionsLeavesOutOptionsNoSegmentHolds(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $config = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Medium', 'Low']);
        [$high, , $low] = $this->getOptionIds($config);
        $segments = [
            $this->createSegment($procedure, [$config->getId() => $high]),
            $this->createSegment($procedure, [$config->getId() => $high]),
            $this->createSegment($procedure, [$config->getId() => $low]),
        ];

        $options = $this->sut->countOptions($this->getField($config), $segments);

        self::assertSame(
            [
                ['id' => $high, 'label' => 'High', 'count' => 2],
                ['id' => $low, 'label' => 'Low', 'count' => 1],
            ],
            $options
        );
    }

    public function testCountOptionsReturnsNothingWithoutSegments(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $config = $this->createSegmentCustomField($procedure, 'Priority', ['High']);

        self::assertSame([], $this->sut->countOptions($this->getField($config), []));
    }

    private function getField(CustomFieldConfiguration $config): CustomFieldInterface
    {
        $field = $config->getConfiguration();
        $field->setId($config->getId());

        return $field;
    }

    /**
     * @param array<string, string|list<string>>|null $valuesByFieldId
     */
    private function createSegment(Procedure $procedure, ?array $valuesByFieldId): Segment
    {
        $attributes = [
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $procedure])->_real(),
        ];
        if (null !== $valuesByFieldId) {
            $attributes['customFields'] = $this->buildCustomFieldValues($valuesByFieldId);
        }

        return SegmentFactory::createOne($attributes)->_real();
    }
}
