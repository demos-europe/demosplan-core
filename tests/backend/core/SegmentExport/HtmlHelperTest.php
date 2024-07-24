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
    /** @var HtmlHelper */
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

    public function testExtractUrlsByClass(): void
    {
        // Test 1: <a> tag with class darstellung and href attribute
        $htmlWithClass = '<a class="darstellung" href="https://www.example1.com">Example 1</a>';
        $expectedUrlsWithClass = ['https://www.example1.com'];

        // Test 2: <a> tag without class darstellung but with href attribute
        $htmlWithoutClass = '<a href="https://www.example2.com">Example 2</a>';
        $expectedUrlsWithoutClass = [];

        // Test 3: Multiple <a> tags with and without class darstellung
        $htmlMixed = '<a class="darstellung" href="https://www.example3.com">Example 3</a>' .
            '<a class="other-class" href="https://www.example4.com">Example 4</a>' .
            '<a class="darstellung" href="https://www.example5.com">Example 5</a>';
        $expectedUrlsMixed = ['https://www.example3.com', 'https://www.example5.com'];

        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;
        $resultA = $this->sut->extractUrlsByClass($htmlWithClass, $class);
        $resultB = $this->sut->extractUrlsByClass($htmlWithoutClass, $class);
        $resultC = $this->sut->extractUrlsByClass($htmlMixed, $class);

        static::assertSame($expectedUrlsWithClass, $resultA);
        static::assertSame($expectedUrlsWithoutClass, $resultB);
        static::assertSame($expectedUrlsMixed, $resultC);
    }

    public function testUpdateLinkTextWithClass(): void
    {
        // Test 1: <a> tag with class darstellung
        $htmlWithClass = '<a class="darstellung" href="https://www.example1.com">Old Text</a>';
        $expectedWithClass = '<a class="darstellung" href="https://www.example1.com">New_Old Text</a>';
        // Test 2: <a> tag without class darstellung
        $htmlWithoutClass = '<a href="https://www.example2.com">Old Text</a>';
        $expectedWithoutClass = '<a href="https://www.example2.com">Old Text</a>';
        // Test 3: Multiple <a> tags, some with class darstellung and some without
        $htmlMixed = '<a class="darstellung" href="https://www.example3.com">Old Text 3</a>' .
            '<a class="other-class" href="https://www.example4.com">Old Text 4</a>' .
            '<a class="darstellung" href="https://www.example5.com">Old Text 5</a>';
        $expectedMixed = '<a class="darstellung" href="https://www.example3.com">New_Old Text 3</a>' .
            '<a class="other-class" href="https://www.example4.com">Old Text 4</a>' .
            '<a class="darstellung" href="https://www.example5.com">New_Old Text 5</a>';
        $prefix = 'New_';
        $resultA = $this->sut->updateLinkTextWithClass($htmlWithClass, 'darstellung', $prefix);
        $resultB = $this->sut->updateLinkTextWithClass($htmlWithoutClass, 'darstellung', $prefix);
        $resultC = $this->sut->updateLinkTextWithClass($htmlMixed, 'darstellung', $prefix);
        static::assertSame($expectedWithClass, $resultA);
        static::assertSame($expectedWithoutClass, $resultB);
        static::assertSame($expectedMixed, $resultC);
    }
}
