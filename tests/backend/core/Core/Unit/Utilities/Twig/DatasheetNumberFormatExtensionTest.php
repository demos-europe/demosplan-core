<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\DatasheetNumberFormatExtension;
use Exception;
use Tests\Base\UnitTestCase;
use Twig\Environment;
use Twig\TwigFilter;

class DatasheetNumberFormatExtensionTest extends UnitTestCase
{
    /**
     * @var DatasheetNumberFormatExtension
     */
    private $twigExtension;

    /**
     * Set up Test.
     *
     * @return void|null
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new DatasheetNumberFormatExtension(self::getContainer(), self::getContainer()->get(Environment::class));
    }

    public function testGetFilters()
    {
        try {
            $result = $this->twigExtension->getFilters();

            static::assertTrue(is_array($result) && isset($result[0]));

            static::assertInstanceOf(TwigFilter::class, $result[0]);

            static::assertSame('datasheet_number_format', $result[0]->getName());
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    /**
     * @dataProvider numbersAndOtherValues
     */
    public function testNumberFormat($expected, $actual, $decimalPoints)
    {
        static::assertEquals(
            $expected,
            $this->twigExtension->numberFormatFilter($actual, $decimalPoints),
            "Failed converting {$actual} to {$expected} with {$decimalPoints} decimal points"
        );
    }

    public function numbersAndOtherValues()
    {
        return [
            ['0,0', 0, 1],
            ['0', 0, 0],
            ['-', '-', 0],
            ['1,6', 1.618, 1],
            ['1,62', 1.618, 2],
            ['1,618', 1.618, 3],
            ['2', 1.618, null],
            ['1.618', 1618, null],
            ['1.618,0', 1618, 1],
        ];
    }
}
