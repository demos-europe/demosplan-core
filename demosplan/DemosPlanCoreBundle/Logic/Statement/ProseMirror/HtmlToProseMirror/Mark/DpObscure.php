<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror\Mark;


use HtmlToProseMirror\Marks\Mark;

class DpObscure extends Mark
{
    public function matching()
    {
        return 'dp-obscure' === $this->DOMNode->nodeName;
    }

    public function data()
    {
        return [
            'type' => 'dp-obscure',
        ];
    }

}
