<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use DateTime;
use demosplan\DemosPlanCoreBundle\Twig\Extension\DateExtension;
use Tests\Base\UnitTestCase;
use Twig\TwigFilter;

/**
 * Teste DateExtension
 * Class DateExtensionTest.
 *
 * @group UnitTest
 */
class DateExtensionTest extends UnitTestCase
{
    /**
     * @var DateExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new DateExtension(self::getContainer());
    }

    public function testGetFilters()
    {
        $result = $this->twigExtension->getFilters();
        static::assertTrue(is_array($result) && isset($result[0]));
        static::assertTrue($result[0] instanceof TwigFilter);
        static::assertTrue('dplanDate' === $result[0]->getName());
    }

    public function testEncode()
    {
        date_default_timezone_set('Europe/Berlin');
        $format = 'F j, Y H:i';

        $textToTest = '';
        $result = $this->twigExtension->dateFilter($textToTest);
        static::assertEquals($result, '');

        $textToTest = '';
        $result = $this->twigExtension->dateFilter($textToTest, $format);
        static::assertEquals($result, '');

        $textToTest = null;
        $result = $this->twigExtension->dateFilter($textToTest, $format);
        static::assertEquals($result, '');

        $textToTest = 1399536007;
        $result = $this->twigExtension->dateFilter($textToTest, $format);
        static::assertEquals($result, 'May 8, 2014 10:00');

        $textToTest = 1399536007;
        $result = $this->twigExtension->dateFilter($textToTest, 'd.m.Y H:i:s');
        static::assertEquals($result, '08.05.2014 10:00:07');

        $textToTest = new DateTime();
        $textToTest->setTimestamp(1399536007);
        $result = $this->twigExtension->dateFilter($textToTest, 'd.m.Y H:i:s');
        static::assertEquals($result, '08.05.2014 10:00:07');

        $textToTest = '2015-11-12T18:13:34+01:00';
        $result = $this->twigExtension->dateFilter($textToTest, 'd.m.Y H:i:s');
        static::assertEquals($result, '12.11.2015 18:13:34');

        $textToTest = new DateTime();
        $textToTest->setTimestamp(-10000510);
        $result = $this->twigExtension->dateFilter($textToTest, 'd.m.Y H:i:s');
        static::assertEquals($result, '');
    }

    public function testName()
    {
        $result = $this->twigExtension->getName();
        static::assertEquals($result, 'date_extension');
    }
}
