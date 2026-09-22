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

    public function testExtractImageDataFromImgTags(): void
    {
        $prefix = 'New_';
        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;

        // Test 1: <img> tag with class and alt
        $htmlImg = '<img class="'.$class.'" src="https://www.example1.com/hash1.jpg" alt="Image 1">';
        $resultA = $this->sut->extractImageDataByClass($htmlImg, $class, $prefix);
        static::assertCount(1, $resultA);
        static::assertSame($prefix.'Image 1', $resultA[0]->getImageReference());
        static::assertSame('https://www.example1.com/hash1.jpg', $resultA[0]->getImagePath());
        static::assertSame('hash1.jpg', $resultA[0]->getFileHash());

        // Test 2: <img> without the target class is also extracted — every <img> is an image reference
        $htmlImgOtherClass = '<img class="other-class" src="https://www.example2.com/hash2.jpg" alt="Image 2">';
        $resultB = $this->sut->extractImageDataByClass($htmlImgOtherClass, $class, $prefix);
        static::assertCount(1, $resultB);
        static::assertSame($prefix.'Image 2', $resultB[0]->getImageReference());

        // Test 3: <img> without any class is extracted too
        $htmlImgNoClass = '<img src="https://www.example3.com/hash3.jpg" alt="Image 3">';
        $resultC = $this->sut->extractImageDataByClass($htmlImgNoClass, $class, $prefix);
        static::assertCount(1, $resultC);
        static::assertSame($prefix.'Image 3', $resultC[0]->getImageReference());

        // Test 4: <img> without alt -> empty label
        $htmlImgNoAlt = '<img class="'.$class.'" src="https://www.example4.com/hash4.jpg">';
        $resultD = $this->sut->extractImageDataByClass($htmlImgNoAlt, $class, $prefix);
        static::assertCount(1, $resultD);
        static::assertSame($prefix, $resultD[0]->getImageReference());
        static::assertSame('hash4.jpg', $resultD[0]->getFileHash());

        // Test 5: self-closing form `<img … />`
        $htmlSelfClosing = '<img src="https://www.example5.com/hash5.jpg" alt="Image 5" />';
        $resultE = $this->sut->extractImageDataByClass($htmlSelfClosing, $class, $prefix);
        static::assertCount(1, $resultE);
        static::assertSame($prefix.'Image 5', $resultE[0]->getImageReference());
    }

    public function testExtractImageDataFromMixedForms(): void
    {
        $prefix = 'New_';
        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;

        $htmlMixed = '<a class="'.$class.'" href="https://www.example.com/hashA.jpg">Anchor Label</a>'.
            ' some text '.
            '<img class="'.$class.'" src="https://www.example.com/hashB.jpg" alt="Img Label">';

        $result = $this->sut->extractImageDataByClass($htmlMixed, $class, $prefix);

        static::assertCount(2, $result);
        $references = array_map(fn ($r) => $r->getImageReference(), $result);
        static::assertContains($prefix.'Anchor Label', $references);
        static::assertContains($prefix.'Img Label', $references);
    }

    public function testUpdateLinkTextWithClassFromImgTags(): void
    {
        $prefix = 'New_';
        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;

        // Test 1: <img> is rewritten to cross-reference anchor regardless of class
        $htmlImg = '<img src="https://www.example1.com/img.jpg" alt="Old Text">';
        $expected = '<a class="'.$class.'" href="#'.$prefix.'Old Text"'
            .' style="color: blue; text-decoration: underline;">'.$prefix.'Old Text</a>';
        static::assertSame($expected, $this->sut->updateLinkTextWithClass($htmlImg, $class, $prefix));

        // Test 2: Mixed <a> and <img> forms both get rewritten
        $htmlMixed = '<a class="'.$class.'" href="https://www.example.com/imgA.jpg">Label A</a>'.
            ' between '.
            '<img src="https://www.example.com/imgB.jpg" alt="Label B">';
        $result = $this->sut->updateLinkTextWithClass($htmlMixed, $class, $prefix);
        static::assertStringContainsString('href="#'.$prefix.'Label A"', $result);
        static::assertStringContainsString('href="#'.$prefix.'Label B"', $result);
        static::assertStringNotContainsString('<img', $result);

        // Test 3: <a> without the target class is left alone (real hyperlinks, not image references)
        $htmlOtherAnchor = '<a class="other-class" href="https://example.com">a real link</a>';
        static::assertSame($htmlOtherAnchor, $this->sut->updateLinkTextWithClass($htmlOtherAnchor, $class, $prefix));
    }

    public function testRemoveLinkTagsByClassFromImgTags(): void
    {
        $prefix = 'New_';
        $class = HtmlHelper::LINK_CLASS_FOR_DARSTELLUNG_STELL;

        // Test 1: <img> is replaced with prefix + alt regardless of class
        $htmlImg = '<img src="https://www.example1.com/img.jpg" alt="Image 1">';
        static::assertSame($prefix.'Image 1', $this->sut->removeLinkTagsByClass($htmlImg, $class, $prefix));

        // Test 2: Mixed <a> (with class) and <img> forms both stripped; unrelated <a> kept
        $htmlMixed = '<a class="'.$class.'" href="https://www.example.com/imgA.jpg">Label A</a>'.
            '<a class="other-class" href="https://example.com">keep me</a>'.
            '<img src="https://www.example.com/imgB.jpg" alt="Label B">';
        $expected = $prefix.'Label A'.
            '<a class="other-class" href="https://example.com">keep me</a>'.
            $prefix.'Label B';
        static::assertSame($expected, $this->sut->removeLinkTagsByClass($htmlMixed, $class, $prefix));
    }
}
