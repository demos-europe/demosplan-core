<?php

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Logic\Statement\SemanticFormat;
use Tests\Base\FunctionalTestCase;

class SemanticFormatTest extends FunctionalTestCase
{
    /**
     * @var SemanticFormat
     */
    protected $sut;

    public function setUp() : void
    {
        parent::setUp();
        $this->sut = new SemanticFormat();
    }

    public function testToJson()
    {
        $html = '<p>One <strong>two <i>three</i></strong> four</p>';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One "},{"type":"text","text":"two ","marks":[{"type":"bold"}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"}]},{"type":"text","text":" four"}]}]}';
        $json = $this->sut->toJson($html);
        self::assertEquals($expected, $json);
    }

    public function testToJsonUmlaut()
    {
        $html = '<p>One <strong>Präsident <i>three</i></strong> four</p>';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One "},{"type":"text","text":"Präsident ","marks":[{"type":"bold"}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"}]},{"type":"text","text":" four"}]}]}';
        $json = $this->sut->toJson($html);
        self::assertEquals($expected, $json);
    }

    public function testToJsonObscure()
    {
        $html = '<p>One <dp-obscure>obscured text</dp-obscure> four</p>';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One "},{"type":"text","text":"obscured text","marks":[{"type":"dp-obscure"}]},{"type":"text","text":" four"}]}]}';
        $json = $this->sut->toJson($html);
        self::assertEquals($expected, $json);
    }

    public function testToJsonSegmentMark()
    {
        $html = '<p>One<strong><dp-segment uuid="XXX">two</dp-segment></strong><strong><em><dp-segment uuid="XXX">three</dp-segment></em></strong><dp-segment uuid="YYY">four</dp-segment></p>';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One"},{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]}]}';
        $json = $this->sut->toJson($html);
        self::assertEquals($expected, $json);
    }

    public function testToHtmlObscure()
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One "},{"type":"text","text":"obscured text","marks":[{"type":"dp-obscure"}]},{"type":"text","text":" four"}]}]}';
        $expected = '<p>One <dp-obscure>obscured text</dp-obscure> four</p>';
        $html = $this->sut->toHtml($json);
        self::assertEquals($expected, $html);
    }

    public function testToHtmlSegmentMark()
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One"},{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]}]}';
        $expected = '<p>One<strong><dp-segment uuid="XXX">two</dp-segment></strong><strong><em><dp-segment uuid="XXX">three</dp-segment></em></strong><dp-segment uuid="YYY">four</dp-segment></p>';
        $html = $this->sut->toHtml($json);
        self::assertEquals($expected, $html);
    }


}
