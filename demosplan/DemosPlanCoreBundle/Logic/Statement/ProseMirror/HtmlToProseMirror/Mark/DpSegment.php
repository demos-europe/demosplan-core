<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror\Mark;


use HtmlToProseMirror\Marks\Mark;

class DpSegment extends Mark
{
    public function matching()
    {
        return 'dp-segment' === $this->DOMNode->nodeName;
    }

    public function data()
    {
        $data = [
            'type' => 'dp-segment',
        ];

        $attrs = [];

        $attrs['uuid'] = $this->DOMNode->getAttribute('uuid');

        $data['attrs'] = $attrs;

        return $data;
    }

}
