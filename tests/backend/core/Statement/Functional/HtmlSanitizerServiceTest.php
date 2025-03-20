<?php

namespace backend\core\Statement\Functional;

use demosplan\DemosPlanCoreBundle\Logic\Statement\HtmlSanitizerService;
use Tests\Base\FunctionalTestCase;

class HtmlSanitizerServiceTest extends FunctionalTestCase
{
    /**
     * @var HtmlSanitizerService;
     */
    protected $sut;
    public function setUp(): void
    {
        parent::setUp();

        $this->sut = static::getContainer()->get(HtmlSanitizerService::class);
    }


    public function testEscapeDisallowedTagsRemovesDisallowedTags()
    {
        $input = '<div>Hallo <strong>Welt!</strong> Dies ist ein Test mit <em>Kursiv</em> und > und < Zeichen innerhalb und außerhalb. <Rn. 43>) ,ththththt.httthththt <262>ggg. the number < 10> and s</div>';
        $expected = '<div>Hallo <strong>Welt!</strong> Dies ist ein Test mit <em>Kursiv</em> und &gt; und &lt; Zeichen innerhalb und außerhalb. &lt;Rn. 43&gt;) ,ththththt.httthththt &lt;262&gt;ggg. the number &lt; 10&gt; and s</div>';
        $result = $this->sut->escapeDisallowedTags($input);
        $this->assertEquals($expected, $result);
    }

    public function testEscapeDisallowedTagsHandlesOnlyDisallowedTags()
    {
        $input = '<No-html-tag>alert("XSS")</No-html-tag>';
        $expected = '&lt;No-html-tag&gt;alert("XSS")&lt;/No-html-tag&gt;';
        $result = $this->sut->escapeDisallowedTags($input);
        $this->assertEquals($expected, $result);
    }

    public function testEscapeDisallowedTagsKeepsAllowedTags()
    {
        $input = '<div><p>Allowed<img src="link"></p><a href="#">Link</a></div>';
        $expected = '<div><p>Allowed<img src="link"></p><a href="#">Link</a></div>';
        $result = $this->sut->escapeDisallowedTags($input);
        $this->assertEquals($expected, $result);
    }
}
