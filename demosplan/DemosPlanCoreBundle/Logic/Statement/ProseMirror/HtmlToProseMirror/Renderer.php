<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror;


use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror\Mark\DpObscure;
use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror\Mark\DpSegment;

class Renderer extends \HtmlToProseMirror\Renderer
{

    public function __construct()
    {
        $this->marks = array_merge($this->marks, [DpObscure::class, DpSegment::class]);
    }
}
