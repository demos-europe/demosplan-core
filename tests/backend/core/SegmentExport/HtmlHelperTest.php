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
use demosplan\DemosPlanCoreBundle\ValueObject\SegmentExport\ImageReference;
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
        $prefix = 'New_';
        // Test 1: <a> tag with class darstellung and href attribute
        $htmlWithClass = '<a href="https://www.example1.com" class="'.
            HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.'">Example 1</a>';
        $expectedWithClass = [
            new ImageReference($prefix.'Example 1', 'https://www.example1.com'),
        ];

        // Test 2: <a> tag without class darstellung but with href attribute
        $htmlWithoutClass = '<a href="https://www.example2.com">Example 2</a>';
        $expectedWithoutClass = [];

        // Test 3: Multiple <a> tags with and without class darstellung
        $htmlMixed = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '" href="https://www.example3.com">Example 3</a>'.
            '<a class="other-class" href="https://www.example4.com">Example 4</a>'.
            '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.'" href="https://www.example5.com">Example 5</a>';
        $expectedMixed = [
            new ImageReference($prefix.'Example 3', 'https://www.example3.com'),
            new ImageReference($prefix.'Example 5', 'https://www.example5.com'),
        ];

        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;

        $resultA = $this->sut->extractImageDataByClass($htmlWithClass, $class, $prefix);
        $resultB = $this->sut->extractImageDataByClass($htmlWithoutClass, $class, $prefix);
        $resultC = $this->sut->extractImageDataByClass($htmlMixed, $class, $prefix);

        static::assertIsArray($resultA);
        static::assertCount(1, $resultA);
        static::assertInstanceOf(ImageReference::class, $resultA[0]);
        static::assertSame($expectedWithClass[0]->getImageReference(), $resultA[0]->getImageReference());
        static::assertSame($expectedWithClass[0]->getImagePath(), $resultA[0]->getImagePath());

        static::assertIsArray($resultB);
        static::assertSame($expectedWithoutClass, $resultB);

        static::assertIsArray($resultC);
        static::assertCount(2, $resultC);
        static::assertInstanceOf(ImageReference::class, $resultC[0]);
        static::assertInstanceOf(ImageReference::class, $resultC[1]);
        static::assertSame($expectedMixed[0]->getImageReference(), $resultC[0]->getImageReference());
        static::assertSame($expectedMixed[0]->getImagePath(), $resultC[0]->getImagePath());
        static::assertSame($expectedMixed[1]->getImageReference(), $resultC[1]->getImageReference());
        static::assertSame($expectedMixed[1]->getImagePath(), $resultC[1]->getImagePath());
    }

    public function testUpdateLinkTextWithClass(): void
    {
        // Test 1: <a> tag with class darstellung
        $htmlWithClass = '<a href="https://www.example1.com" class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '">Old Text</a>';
        $expectedWithClass = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.'" href="#New_Old Text"'
            .' style="color: blue; text-decoration: underline;">New_Old Text</a>';
        // Test 2: <a> tag without class darstellung
        $htmlWithoutClass = '<a href="https://www.example2.com">Old Text</a>';
        $expectedWithoutClass = '<a href="https://www.example2.com">Old Text</a>';
        // Test 3: Multiple <a> tags, some with class darstellung and some without
        $htmlMixed = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '" href="https://www.example3.com">Old Text 3</a>'.
            '<a class="other-class" href="https://www.example4.com">Old Text 4</a>'.
            '<a  href="https://www.example5.com" style="color: blue;" class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '">Old Text 5</a>';
        $expectedMixed = '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.'" href="#New_Old Text 3"'
            .' style="color: blue; text-decoration: underline;">New_Old Text 3</a>'.
            '<a class="other-class" href="https://www.example4.com">Old Text 4</a>'.
            '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.'" href="#New_Old Text 5"'
            .' style="color: blue; text-decoration: underline;">New_Old Text 5</a>';
        $prefix = 'New_';
        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;
        $resultA = $this->sut->updateLinkTextWithClass($htmlWithClass, $class, $prefix);
        $resultB = $this->sut->updateLinkTextWithClass($htmlWithoutClass, $class, $prefix);
        $resultC = $this->sut->updateLinkTextWithClass($htmlMixed, $class, $prefix);
        static::assertSame($expectedWithClass, $resultA);
        static::assertSame($expectedWithoutClass, $resultB);
        static::assertSame($expectedMixed, $resultC);
    }

    public function testRemoveLinkTagsByClass(): void
    {
        $prefix = 'New_';
        $htmlMixed = '<a href="https://www.example1.com" class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '">Old Text 1</a>'.
            '<a class="other-class" href="https://www.example2.com">Old Text 2</a>'.
            '<a class="'.HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL.
            '" href="https://www.example3.com">Old Text 3</a>';
        $expectedMixed = $prefix
            .'Old Text 1<a class="other-class" href="https://www.example2.com">Old Text 2</a>'.$prefix.'Old Text 3';
        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;

        $result = $this->sut->removeLinkTagsByClass($htmlMixed, $class, $prefix);
        static::assertSame($expectedMixed, $result);
    }
}
