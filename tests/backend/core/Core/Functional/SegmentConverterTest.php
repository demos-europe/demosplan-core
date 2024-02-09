<?php


namespace Tests\Core\Core\Functional;


use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\SegmentConverter;
use Tests\Base\FunctionalTestCase;

class SegmentConverterTest extends FunctionalTestCase
{
    /**
     * @var SegmentConverter
     */
    protected $sut;

    public function setUp() : void
    {
        parent::setUp();
        $this->sut = new SegmentConverter();
    }

    public function testReduceToSegment()
    {
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One"},{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]}]}';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]}]}]}';
        $result = $this->sut->getSegmentProseMirror($json, 'XXX');
        self::assertEquals($expected, $result);

        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One"},{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]}]}';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]}]}';
        $result = $this->sut->getSegmentProseMirror($json, 'YYY');
        self::assertEquals($expected, $result);

        // multiple paragraphs
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One"},{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]},{"type":"paragraph","content":[{"type":"text","text":"Five"},{"type":"text","text":"six","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"ZZZ"}}]},{"type":"text","text":"seven","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"ZZZ"}}]},{"type":"text","text":"eight","marks":[{"type":"dp-segment","attrs":{"uuid":"AAA"}}]}]}]}';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]}]}]}';
        $result = $this->sut->getSegmentProseMirror($json, 'XXX');
        self::assertEquals($expected, $result);

        // multiple paragraphs pluck from second
        $json = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"One"},{"type":"text","text":"two","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"three","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"XXX"}}]},{"type":"text","text":"four","marks":[{"type":"dp-segment","attrs":{"uuid":"YYY"}}]}]},{"type":"paragraph","content":[{"type":"text","text":"Five"},{"type":"text","text":"six","marks":[{"type":"bold"},{"type":"dp-segment","attrs":{"uuid":"ZZZ"}}]},{"type":"text","text":"seven","marks":[{"type":"bold"},{"type":"italic"},{"type":"dp-segment","attrs":{"uuid":"ZZZ"}}]},{"type":"text","text":"eight","marks":[{"type":"dp-segment","attrs":{"uuid":"AAA"}}]}]}]}';
        $expected = '{"type":"doc","content":[{"type":"paragraph","content":[{"type":"text","text":"eight","marks":[{"type":"dp-segment","attrs":{"uuid":"AAA"}}]}]}]}';
        $result = $this->sut->getSegmentProseMirror($json, 'AAA');
        self::assertEquals($expected, $result);
    }
}
