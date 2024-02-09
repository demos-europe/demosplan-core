<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror\Node;


use HtmlToProseMirror\Nodes\Node;

class DpSegment extends Node
{
    public function matching()
    {
        return 'dp-segment' === $this->DOMNode->nodeName;
    }

    private function getId()
    {
        return preg_replace("/^id-/", "", $this->DOMNode->getAttribute('class'));
    }

    public function data()
    {
        if ($language = $this->getId()) {
            return [
                'type' => 'dp-segment',
                'attrs' => [
                    'id' => $this->getId(),
                ],
            ];
        }

        return [
            'type' => 'dp-segment',
        ];
    }
}
