<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\SegmentExport;

use demosplan\DemosPlanCoreBundle\Logic\Segment\Export\Utils\HtmlHelper;
use Tests\Base\FunctionalTestCase;

class HtmlHelperTest extends FunctionalTestCase
{
    /** @var HtmlHelper $sut */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = $this->getContainer()->get(HtmlHelper::class);
    }

    public function testGetHtmlValidText(): void
    {
        // Test 1: <a> tag with href attribute
        $textWithHref = '<a href="https://www.example.com">Example</a><br>Hallo Test';
        $expectedWithATags = '<a href="https://www.example.com">Example</a><br />Hallo Test';

        // Test 2: <a> tag without href attribute
        $textWithoutHref = '<a>Example</a><br>Hallo Test';
        $expectedWithoutATags = 'Example<br />Hallo Test';

        // Test 3: <a> tag without href but with other attributes
        $textWithOtherAttributes = '<a id="example" class="example-class">Example</a><br>Hallo Test';
        $expectedOtherAttributesRemoved = 'Example<br />Hallo Test';

        $resultA = $this->sut->getHtmlValidText($textWithHref);
        $resultB = $this->sut->getHtmlValidText($textWithoutHref);
        $resultC = $this->sut->getHtmlValidText($textWithOtherAttributes);

        static::assertSame($expectedWithATags, $resultA);
        static::assertSame($expectedWithoutATags, $resultB);
        static::assertSame($expectedOtherAttributesRemoved, $resultC);
    }
}
