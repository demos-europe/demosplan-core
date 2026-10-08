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

use demosplan\DemosPlanCoreBundle\Utils\CustomField\SegmentCustomFieldFilter;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Tests\Base\FunctionalTestCase;

class SegmentCustomFieldFilterTest extends FunctionalTestCase
{
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

    public function testGetOptionLikePatternQuotesTheOptionId(): void
    {
        $optionId = Uuid::uuid4()->toString();

        self::assertSame('%"'.$optionId.'"%', $this->sut->getOptionLikePattern($optionId));
    }
}
