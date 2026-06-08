<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\HeightLimitExtension;
use Tests\Base\UnitTestCase;
use Twig\TwigFilter;

/**
 * Teste HeightLimitExtension
 * Class HeightLimitExtensionTest.
 *
 * @group UnitTest
 */
class HeightLimitExtensionTest extends UnitTestCase
{
    /**
     * @var HeightLimitExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new HeightLimitExtension(self::getContainer());
    }

    public function testGetFilters()
    {
        $result = $this->twigExtension->getFilters();
        static::assertTrue(is_array($result) && isset($result[0]));
        static::assertInstanceOf(TwigFilter::class, $result[0]);
        static::assertEquals('heightLimitShorten', $result[0]->getName());
    }

    public function testHeightLimit()
    {
        $textToTest = 'Some text with a little bit more than 500 characters just to have a result '.
            'that bears the resemblance of an actual real world result without it being a real world '.
            'result. Damn it 500 characters is a long stretch of text. Hm.. what else could I write '.
            'here. Let\'s just start from the top. Some text with a little bit more than 500 '.
            'characters just to have a result that bears the resemblance of an actual real world '.
            'result without it being a real world result. Damn it 500 characters is a long '.
            'stretch of text. Hm.. what else could I write here.';

        $expected = 'Some text with a little bit more than 500 characters just to have a result '.
            'that bears the resemblance of an actual real world result without it being a real '.
            'world result. Damn it 500 characters is a long stretch of text. Hm.. what else '.
            'could I write here. Let\'s just start from the top. Some text with a little bit '.
            'more than 500 characters just to have a result that bears the resemblance of an '.
            'actual real world result without it being a real world result. Damn it 500 '.
            'characters is a long stretch of';

        $result = $this->twigExtension->heightLimitShorten($textToTest);
        static::assertEquals($expected, $result);
    }
}
