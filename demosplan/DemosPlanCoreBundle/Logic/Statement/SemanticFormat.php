<?php


namespace demosplan\DemosPlanCoreBundle\Logic\Statement;


use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\HtmlToProseMirror\Renderer as HtmlToProseMirrorRenderer;
use demosplan\DemosPlanCoreBundle\Logic\Statement\ProseMirror\ProseMirrorToHtml\Renderer as ProseMirrorToHtmlRenderer;
use GuzzleHttp\Exception\InvalidArgumentException;

class SemanticFormat
{


    public function toHtml($prosemirrorJson)
    {
        return (new ProseMirrorToHtmlRenderer)->render($prosemirrorJson);
    }

    private function toArray($html)
    {
        return (new HtmlToProseMirrorRenderer)->render($html);
    }

    /**
     * @param $html
     * @return string
     * @throws InvalidArgumentException
     */
    public function toJson($html)
    {
        $array = $this->toArray($html);
        return \GuzzleHttp\json_encode($array, JSON_UNESCAPED_UNICODE);
    }
}
