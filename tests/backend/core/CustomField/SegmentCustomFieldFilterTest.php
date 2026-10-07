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

use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\SegmentCustomFieldFilter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

class SegmentCustomFieldFilterTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?SegmentCustomFieldFilter $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(SegmentCustomFieldFilter::class);
    }

    public function testAssertValidSelectionsAcceptsAnEmptyList(): void
    {
        $this->sut->assertValidSelections([]);

        $this->addToAssertionCount(1);
    }

    public function testAssertValidSelectionsAcceptsFieldIdsAsKeys(): void
    {
        $this->sut->assertValidSelections([
            Uuid::uuid4()->toString() => ['a', 'b'],
            Uuid::uuid4()->toString() => ['c'],
        ]);

        $this->addToAssertionCount(1);
    }

    public function testAssertValidSelectionsRejectsAFieldIdThatIsNoUuid(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->sut->assertValidSelections(['not-a-uuid' => ['a']]);
    }

    public function testAssertValidSelectionsRejectsAFieldKeyThatIsNoString(): void
    {
        $this->expectException(BadRequestHttpException::class);

        // e.g. `customField[][]=a`, PHP turns the missing key into the number 0
        $this->sut->assertValidSelections([0 => ['a']]);
    }

    public function testFindMatchingSegmentIdsMatchesAnyOptionOfAFieldAndEveryField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise, $traffic] = $this->getOptionIds($topics);

        $highNoise = $this->createSegment($procedure, [$priority->getId() => $high, $topics->getId() => [$noise]]);
        $highTraffic = $this->createSegment($procedure, [$priority->getId() => $high, $topics->getId() => [$traffic]]);
        $lowNoise = $this->createSegment($procedure, [$priority->getId() => $low, $topics->getId() => [$noise, $traffic]]);
        $this->createSegment($procedure, null);

        // any option of one field
        self::assertEqualsCanonicalizing(
            [$highNoise, $highTraffic, $lowNoise],
            $this->sut->findMatchingSegmentIds($procedure->getId(), [$priority->getId() => [$high, $low]])
        );
        // every selected field has to match
        self::assertSame(
            [$highNoise],
            $this->sut->findMatchingSegmentIds($procedure->getId(), [$priority->getId() => [$high], $topics->getId() => [$noise]])
        );
        // a multi select value matches on any of its options
        self::assertEqualsCanonicalizing(
            [$highTraffic, $lowNoise],
            $this->sut->findMatchingSegmentIds($procedure->getId(), [$topics->getId() => [$traffic]])
        );
    }

    public function testFindMatchingSegmentIdsOnlyLooksAtTheGivenProcedure(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $otherProcedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $field = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        [$high] = $this->getOptionIds($field);
        $this->createSegment($otherProcedure, [$field->getId() => $high]);

        self::assertSame([], $this->sut->findMatchingSegmentIds($procedure->getId(), [$field->getId() => [$high]]));
    }

    /**
     * @param array<string, string|list<string>>|null $valuesByFieldId
     *
     * @return string the segment id
     */
    private function createSegment(Procedure $procedure, ?array $valuesByFieldId): string
    {
        $attributes = [
            'procedure'                => $procedure,
            'parentStatementOfSegment' => StatementFactory::createOne(['procedure' => $procedure])->_real(),
        ];
        if (null !== $valuesByFieldId) {
            $attributes['customFields'] = $this->buildCustomFieldValues($valuesByFieldId);
        }

        return SegmentFactory::createOne($attributes)->_real()->getId();
    }
}
