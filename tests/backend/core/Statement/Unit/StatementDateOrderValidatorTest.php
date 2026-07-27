<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Unit;

use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use demosplan\DemosPlanCoreBundle\Logic\Statement\StatementDateOrderValidator;
use PHPUnit\Framework\TestCase;

class StatementDateOrderValidatorTest extends TestCase
{
    private StatementDateOrderValidator $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new StatementDateOrderValidator();
    }

    /**
     * @dataProvider authoredAfterSubmittedProvider
     */
    public function testIsAuthoredAfterSubmitted(
        ?DateTimeInterface $authored,
        ?DateTimeInterface $submitted,
        bool $expected,
    ): void {
        self::assertSame($expected, $this->sut->isAuthoredAfterSubmitted($authored, $submitted));
    }

    /**
     * @return array<string, array{0: ?DateTimeInterface, 1: ?DateTimeInterface, 2: bool}>
     */
    public static function authoredAfterSubmittedProvider(): array
    {
        return [
            'authored after submitted -> true' => [
                new DateTime('2020-01-10'), new DateTime('2020-01-05'), true,
            ],
            'authored equals submitted (same day, different time) -> false' => [
                new DateTime('2020-01-05 00:00:00'), new DateTime('2020-01-05 12:00:00'), false,
            ],
            'authored before submitted -> false' => [
                new DateTime('2020-01-01'), new DateTime('2020-01-05'), false,
            ],
            'immutable inputs are accepted' => [
                new DateTimeImmutable('2020-02-02'), new DateTimeImmutable('2020-01-01'), true,
            ],
            'missing authored -> false' => [
                null, new DateTime('2020-01-05'), false,
            ],
            'missing submitted -> false' => [
                new DateTime('2020-01-05'), null, false,
            ],
            'both missing -> false' => [
                null, null, false,
            ],
        ];
    }
}
