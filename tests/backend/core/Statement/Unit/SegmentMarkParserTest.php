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

    public function testSingleSegmentMark(): void
    {
        $html = '<p><segment-mark data-segment-id="5c1a0233-60d3-46d5-95b1-1e79a9649e8a">Die Nähe zum FFH-Gebiet</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('5c1a0233-60d3-46d5-95b1-1e79a9649e8a', $result[0]['segmentId']);
        self::assertSame('Die Nähe zum FFH-Gebiet', $result[0]['text']);
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
        self::assertSame('First segment', $result[0]['text']);
        self::assertSame('Second segment', $result[1]['text']);
        self::assertSame('Third segment', $result[2]['text']);
    }

    public function testUnsegmentedTextIsIgnored(): void
    {
        $html = '<p><segment-mark data-segment-id="aaa-111">Segmented text</segment-mark> This text is not in any segment.</p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('Segmented text', $result[0]['text']);
    }

    public function testSegmentMarkWithInnerHtmlStripsTagsForText(): void
    {
        $html = '<p><segment-mark data-segment-id="aaa-111">Text with <strong>bold</strong> and <em>italic</em> formatting</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('Text with bold and italic formatting', $result[0]['text']);
    }

    public function testSegmentMarkWithoutIdIsSkipped(): void
    {
        $html = '<p><segment-mark>No ID here</segment-mark><segment-mark data-segment-id="aaa-111">Valid segment</segment-mark></p>';

        $result = $this->sut->parse($html);

        self::assertCount(1, $result);
        self::assertSame('aaa-111', $result[0]['segmentId']);
    }

    public function testRealWorldExampleFromPrDescription(): void
    {
        $html = '<p><segment-mark data-segment-id="5c1a0233-60d3-46d5-95b1-1e79a9649e8a">Die Nähe zum FFH-Gebiet und das dortige Vorkommen des Rotmilans als charakteristische Art gibt zu Bedenken Anlass, zumindest im nördlichen Erweiterungsteil.</segment-mark> </p>'
            .'<p> <segment-mark data-segment-id="1ce76fde-3e9b-4afa-afc7-5c863c365923">Dies gilt z.T. auch für Fledermäuse und Gastvögel.</segment-mark></p>'
            .'<ul><li><p> <segment-mark data-segment-id="625f4aba-f2ff-439d-8345-851629f5f130">Aber auch die Gefahren durch Anlagenbrände sind gerade durch die starken Größenzuwächse der Anlagen enorm gestiegen.</segment-mark></p></li></ul>';

        $result = $this->sut->parse($html);

        self::assertCount(3, $result);

        self::assertSame('5c1a0233-60d3-46d5-95b1-1e79a9649e8a', $result[0]['segmentId']);
        self::assertStringContainsString('Die Nähe zum FFH-Gebiet', $result[0]['text']);

        self::assertSame('1ce76fde-3e9b-4afa-afc7-5c863c365923', $result[1]['segmentId']);
        self::assertStringContainsString('Dies gilt z.T. auch für Fledermäuse', $result[1]['text']);

        self::assertSame('625f4aba-f2ff-439d-8345-851629f5f130', $result[2]['segmentId']);
        self::assertStringContainsString('Aber auch die Gefahren durch Anlagenbrände', $result[2]['text']);
    }
}
