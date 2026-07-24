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

use demosplan\DemosPlanCoreBundle\Logic\Statement\SegmentMarkParser;
use PHPUnit\Framework\TestCase;

class SegmentMarkParserTest extends TestCase
{
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = new SegmentMarkParser();
    }

    public function testEmptyHtmlReturnsEmptyArray(): void
    {
        self::assertSame([], $this->sut->parse(''));
        self::assertSame([], $this->sut->parse('   '));
    }

    public function testHtmlWithoutSegmentMarksReturnsEmptyArray(): void
    {
        $html = '<p>This is a paragraph without any segment marks.</p>';

        self::assertSame([], $this->sut->parse($html));
    }

    public function testSingleSegmentMarkKeepsItsBlock(): void
    {
        $html = '<p><segment-mark data-segment-id="5c1a0233-60d3-46d5-95b1-1e79a9649e8a">Die Nähe zum FFH-Gebiet</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('5c1a0233-60d3-46d5-95b1-1e79a9649e8a', $result[0]['segmentId']);
        self::assertSame('<p>Die Nähe zum FFH-Gebiet</p>', $result[0]['text']);
    }

    public function testMultipleSegmentMarksPreserveDocumentOrder(): void
    {
        $html = '<p><segment-mark data-segment-id="aaa-111">First segment</segment-mark></p>'
            .'<p><segment-mark data-segment-id="bbb-222">Second segment</segment-mark></p>'
            .'<p><segment-mark data-segment-id="ccc-333">Third segment</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(3, $result);
        self::assertSame('aaa-111', $result[0]['segmentId']);
        self::assertSame('bbb-222', $result[1]['segmentId']);
        self::assertSame('ccc-333', $result[2]['segmentId']);
        self::assertSame('<p>First segment</p>', $result[0]['text']);
        self::assertSame('<p>Second segment</p>', $result[1]['text']);
        self::assertSame('<p>Third segment</p>', $result[2]['text']);
    }

    public function testUnsegmentedTextIsIgnored(): void
    {
        $html = '<p><segment-mark data-segment-id="aaa-111">Segmented text</segment-mark> This text is not in any segment.</p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('<p>Segmented text</p>', $result[0]['text']);
    }

    public function testSegmentMarkPreservesInlineFormatting(): void
    {
        $html = '<p><segment-mark data-segment-id="aaa-111">Text with <strong>bold</strong> and <em>italic</em> formatting</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('<p>Text with <strong>bold</strong> and <em>italic</em> formatting</p>', $result[0]['text']);
    }

    public function testSegmentMarkWithoutIdIsSkipped(): void
    {
        $html = '<p><segment-mark>No ID here</segment-mark><segment-mark data-segment-id="aaa-111">Valid segment</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('aaa-111', $result[0]['segmentId']);
        self::assertSame('<p>Valid segment</p>', $result[0]['text']);
    }

    /**
     * One paragraph that encloses a bold run: the inline mark is closed and
     * reopened around the bold, producing three adjacent same-id marks that all
     * share the same <p>. They must merge back into a single paragraph.
     */
    public function testAdjacentMarksInOneParagraphMergeIntoOneBlock(): void
    {
        $html = '<p>'
            .'<segment-mark data-segment-id="aaa-111">text </segment-mark>'
            .'<segment-mark data-segment-id="aaa-111"><strong>bold</strong></segment-mark>'
            .'<segment-mark data-segment-id="aaa-111"> more</segment-mark>'
            .'</p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('aaa-111', $result[0]['segmentId']);
        self::assertSame('<p>text <strong>bold</strong> more</p>', $result[0]['text']);
    }

    /**
     * Two paragraphs surrounding a bold run carry the same set of marks as the
     * single-paragraph case, but each mark lives in its own <p>. The block
     * boundaries must be preserved.
     */
    public function testAdjacentMarksAcrossParagraphsKeepBlockBoundaries(): void
    {
        $html = '<p><segment-mark data-segment-id="aaa-111">text</segment-mark></p>'
            .'<p><segment-mark data-segment-id="aaa-111"><strong>bold</strong></segment-mark></p>'
            .'<p><segment-mark data-segment-id="aaa-111">more</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('aaa-111', $result[0]['segmentId']);
        self::assertSame('<p>text</p><p><strong>bold</strong></p><p>more</p>', $result[0]['text']);
    }

    public function testListItemSegmentKeepsListStructure(): void
    {
        $html = '<ul><li><p><segment-mark data-segment-id="aaa-111"><strong>listenelement</strong></segment-mark></p></li></ul>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('<ul><li><p><strong>listenelement</strong></p></li></ul>', $result[0]['text']);
    }

    /**
     * Marks placed inside a shared inline element keep that element as a common
     * ancestor that is emitted once.
     */
    public function testMarksInsideSharedInlineElementMerge(): void
    {
        $html = '<ul><li><p><strong>'
            .'<segment-mark data-segment-id="aaa-111">Lis</segment-mark>'
            .'<segment-mark data-segment-id="aaa-111">ten</segment-mark>'
            .'</strong></p></li></ul>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('<ul><li><p><strong>Listen</strong></p></li></ul>', $result[0]['text']);
    }

    /**
     * Two segments inside the same list each get their own single-item list,
     * because the other segment's <li> is not on their ancestor chain.
     */
    public function testSharedListSplitsIntoPerSegmentLists(): void
    {
        $html = '<ul>'
            .'<li><p><segment-mark data-segment-id="aaa-111">item 1</segment-mark></p></li>'
            .'<li><p><segment-mark data-segment-id="bbb-222">item 2</segment-mark></p></li>'
            .'</ul>';

        $result = $this->sut->parse($html);

        self::assertCount(2, $result);
        self::assertSame('aaa-111', $result[0]['segmentId']);
        self::assertSame('<ul><li><p>item 1</p></li></ul>', $result[0]['text']);
        self::assertSame('bbb-222', $result[1]['segmentId']);
        self::assertSame('<ul><li><p>item 2</p></li></ul>', $result[1]['text']);
    }

    public function testRealWorldExampleFromPrDescription(): void
    {
        $html = '<p><segment-mark data-segment-id="5c1a0233-60d3-46d5-95b1-1e79a9649e8a">Die Nähe zum FFH-Gebiet und das dortige Vorkommen des Rotmilans als charakteristische Art gibt zu Bedenken Anlass, zumindest im nördlichen Erweiterungsteil.</segment-mark> </p>'
            .'<p> <segment-mark data-segment-id="1ce76fde-3e9b-4afa-afc7-5c863c365923">Dies gilt z.T. auch für Fledermäuse und Gastvögel.</segment-mark></p>'
            .'<ul><li><p> <segment-mark data-segment-id="625f4aba-f2ff-439d-8345-851629f5f130">Aber auch die Gefahren durch Anlagenbrände sind gerade durch die starken Größenzuwächse der Anlagen enorm gestiegen.</segment-mark></p></li></ul>';

        $result = $this->sut->parse($html);

        self::assertCount(3, $result);

        self::assertSame('5c1a0233-60d3-46d5-95b1-1e79a9649e8a', $result[0]['segmentId']);
        self::assertSame(
            '<p>Die Nähe zum FFH-Gebiet und das dortige Vorkommen des Rotmilans als charakteristische Art gibt zu Bedenken Anlass, zumindest im nördlichen Erweiterungsteil.</p>',
            $result[0]['text']
        );

        self::assertSame('1ce76fde-3e9b-4afa-afc7-5c863c365923', $result[1]['segmentId']);
        self::assertSame(
            '<p>Dies gilt z.T. auch für Fledermäuse und Gastvögel.</p>',
            $result[1]['text']
        );

        self::assertSame('625f4aba-f2ff-439d-8345-851629f5f130', $result[2]['segmentId']);
        self::assertSame(
            '<ul><li><p>Aber auch die Gefahren durch Anlagenbrände sind gerade durch die starken Größenzuwächse der Anlagen enorm gestiegen.</p></li></ul>',
            $result[2]['text']
        );
    }
}
