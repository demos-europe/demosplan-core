<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\ProseMirrorToHtml\Mark;


use ProseMirrorToHtml\Marks\Mark;

class DpSegment extends Mark
{
    protected $markType = 'dp-segment';
    protected $tagName = 'dp-segment';

    public function tag()
    {
        $attrs = [];

        $attrs['uuid'] = $this->mark->attrs->uuid;

        return [
            [
                'tag' => $this->tagName,
                'attrs' => $attrs,
            ],
        ];
    }
}
