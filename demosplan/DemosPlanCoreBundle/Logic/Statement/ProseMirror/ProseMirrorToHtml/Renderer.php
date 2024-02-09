<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\ProseMirrorToHtml;


use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\ProseMirrorToHtml\Mark\DpObscure;
use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\ProseMirrorToHtml\Mark\DpSegment;

class Renderer extends \ProseMirrorToHtml\Renderer
{

    public function __construct()
    {
        $this->marks = array_merge($this->marks, [DpObscure::class, DpSegment::class]);
    }
}
