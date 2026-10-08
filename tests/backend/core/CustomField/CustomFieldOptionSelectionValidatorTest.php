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
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Validator\CustomFieldOptionSelectionValidator;
use Ramsey\Uuid\Uuid;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

class CustomFieldOptionSelectionValidatorTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?CustomFieldOptionSelectionValidator $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(CustomFieldOptionSelectionValidator::class);
    }

    public function testAcceptsAnEmptySelection(): void
    {
        $this->sut->validate([]);

        $this->addToAssertionCount(1);
    }

    public function testAcceptsOptionsOfTheirOwnField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise] = $this->getOptionIds($topics);

        $this->sut->validate([$priority->getId() => [$high, $low], $topics->getId() => [$noise]]);

        $this->addToAssertionCount(1);
    }

    public function testRejectsAnOptionOfAnotherField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise']);
        [$noise] = $this->getOptionIds($topics);

        $this->expectException(InvalidArgumentException::class);

        $this->sut->validate([$priority->getId() => [$noise]]);
    }

    public function testRejectsAnUnknownField(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sut->validate([Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()]]);
    }

    public function testRejectsAFieldKeyThatIsNoString(): void
    {
        $this->expectException(InvalidArgumentException::class);

        // e.g. `customField[][]=a`, PHP turns the missing key into the number 0
        $this->sut->validate([0 => ['a']]);
    }
}
