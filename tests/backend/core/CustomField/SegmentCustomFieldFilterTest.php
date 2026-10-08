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

    public function testAssertValidSelectionsAcceptsOptionsOfTheirOwnField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise] = $this->getOptionIds($topics);

        $this->sut->assertValidSelections([$priority->getId() => [$high, $low], $topics->getId() => [$noise]]);

        $this->addToAssertionCount(1);
    }

    public function testAssertValidSelectionsRejectsAnOptionOfAnotherField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise']);
        [$noise] = $this->getOptionIds($topics);

        $this->expectException(BadRequestHttpException::class);

        $this->sut->assertValidSelections([$priority->getId() => [$noise]]);
    }

    public function testAssertValidSelectionsRejectsAnUnknownField(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $this->sut->assertValidSelections([Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()]]);
    }

    public function testAssertValidSelectionsRejectsAFieldKeyThatIsNoString(): void
    {
        $this->expectException(BadRequestHttpException::class);

        // e.g. `customField[][]=a`, PHP turns the missing key into the number 0
        $this->sut->assertValidSelections([0 => ['a']]);
    }

    public function testGetOptionLikePatternQuotesTheOptionId(): void
    {
        $optionId = Uuid::uuid4()->toString();

        self::assertSame('%"'.$optionId.'"%', $this->sut->getOptionLikePattern($optionId));
    }
}
