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
use demosplan\DemosPlanCoreBundle\Utils\CustomField\Constraint\CustomFieldOptionSelection;
use Ramsey\Uuid\Uuid;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Core\JsonApi\StatementSegment\SegmentCustomFieldTestTrait;

class CustomFieldOptionSelectionConstraintValidatorTest extends FunctionalTestCase
{
    use SegmentCustomFieldTestTrait;

    private ?ValidatorInterface $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ValidatorInterface::class);
    }

    public function testAcceptsAnEmptySelection(): void
    {
        self::assertCount(0, $this->sut->validate([], new CustomFieldOptionSelection()));
    }

    public function testAcceptsOptionsOfTheirOwnField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High', 'Low']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise', 'Traffic'], multiSelect: true);
        [$high, $low] = $this->getOptionIds($priority);
        [$noise] = $this->getOptionIds($topics);

        $violations = $this->sut->validate(
            [$priority->getId() => [$high, $low], $topics->getId() => [$noise]],
            new CustomFieldOptionSelection()
        );

        self::assertCount(0, $violations);
    }

    public function testRejectsAnOptionOfAnotherField(): void
    {
        $procedure = ProcedureFactory::new()->withDefaultSettings()->create()->_real();
        $priority = $this->createSegmentCustomField($procedure, 'Priority', ['High']);
        $topics = $this->createSegmentCustomField($procedure, 'Topics', ['Noise']);
        [$noise] = $this->getOptionIds($topics);

        $violations = $this->sut->validate([$priority->getId() => [$noise]], new CustomFieldOptionSelection());

        self::assertCount(1, $violations);
        self::assertStringContainsString($priority->getId(), $violations[0]->getMessage());
        self::assertStringContainsString($noise, $violations[0]->getMessage());
    }

    public function testRejectsAnUnknownField(): void
    {
        $violations = $this->sut->validate(
            [Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()]],
            new CustomFieldOptionSelection()
        );

        self::assertCount(1, $violations);
    }

    public function testRejectsAFieldKeyThatIsNoString(): void
    {
        // e.g. `customField[][]=a`, PHP turns the missing key into the number 0
        self::assertCount(1, $this->sut->validate([0 => ['a']], new CustomFieldOptionSelection()));
    }

    public function testReportsEveryInvalidField(): void
    {
        $violations = $this->sut->validate(
            [
                Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()],
                Uuid::uuid4()->toString() => [Uuid::uuid4()->toString()],
            ],
            new CustomFieldOptionSelection()
        );

        self::assertCount(2, $violations);
    }
}
