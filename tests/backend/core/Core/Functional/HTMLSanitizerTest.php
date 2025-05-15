<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use Tests\Base\FunctionalTestCase;

class HTMLSanitizerTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(HTMLSanitizer::class);
    }

    public function testWysiwygFilter()
    {
        $textToTest = '';
        $expected = '';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = null;
        $expected = '';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = false;
        $expected = '';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><b>';
        $expected = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><b>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><img><sup><b>';
        $expected = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><img><sup><b>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike>Withtext<ul><li><strong><em><span><img><sup><b>';
        $expected = '<p><br><a><ol><u><i><strike>Withtext<ul><li><strong><em><span><img><sup><b>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p></p>';
        $expected = '<p></p>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p>Withtext</p>';
        $expected = '<p>Withtext</p>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '</p>';
        $expected = '</p>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p class="something">';
        $expected = '<p class="something">';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike><s>Withtext<ul><li><strong><em><span><img><sup><del><b><mark>';
        $expected = '<p><br><a><ol><u><i><strike><s>Withtext<ul><li><strong><em><span><img><sup><del><b><mark>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike>Withtext<ul><li><strong><em><span><img><sup><del><ins><b><mark>';
        $expected = '<p><br><a><ol><u><i><strike>Withtext<ul><li><strong><em><span><img><sup><del><ins><b><mark>';
        $result = $this->sut->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);
    }

    public function testAdditionalTags()
    {
        $textToTest = '<p><img src="abc">';
        $expected = '<p><img src="abc">';
        $result = $this->sut->wysiwygFilter($textToTest, ['img']);
        static::assertEquals($expected, $result);
    }

    /**
     * Kudos to https://owasp.org/www-community/xss-filter-evasion-cheatsheet
     * for the vectors.
     */
    public function testPurify()
    {
        $textToTest = '<p><img src="/me/abc">';
        $expected = '<p><img src="/me/abc" alt="abc" /></p>';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<a href="/me/abc">';
        $expected = '<a href="/me/abc"></a>';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<a href="abc" onclick="functionname">';
        $expected = '<a href="abc"></a>';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<span onclick="functionname()">';
        $expected = '<span></span>';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC="javascript:alert(\'XSS\');">';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=javascript:alert(\'XSS\')>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=JaVaScRiPt:alert(\'XSS\')>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=javascript:alert(&quot;XSS&quot;)>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=`javascript:alert("RSnake says, \'XSS\'")`>>';
        $expected = '<img src="%60javascript%3Aalert(" alt="`javascript:alert(&quot;RSnake" />&gt;';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '\<a onmouseover="alert(document.cookie)"\>xxs link\</a\>\>';
        $expected = '\<a>xxs link\</a>\&gt;';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG """><SCRIPT>alert("XSS")</SCRIPT>"\>';
        $expected = '"\&gt;';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=javascript:alert(String.fromCharCode(88,83,83))>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=# onmouseover="alert(\'xxs\')">';
        $expected = '<img src="#" alt="#" />';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG onmouseover="alert(\'xxs\')">';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=/ onerror="alert(String.fromCharCode(88,83,83))"></img>';
        $expected = '<img src="/" alt="" />';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<img src=x onerror="&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041">';
        $expected = '<img src="x" alt="x" />';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=&#106;&#97;&#118;&#97;&#115;&#99;&#114;&#105;&#112;&#116;&#58;&#97;&#108;&#101;&#114;&#116;&#40;&#39;&#88;&#83;&#83;&#39;&#41;>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=&#0000106&#0000097&#0000118&#0000097&#0000115&#0000099&#0000114&#0000105&#0000112&#0000116&#0000058&#0000097&#0000108&#0000101&#0000114&#0000116&#0000040&#0000039&#0000088&#0000083&#0000083&#0000039&#0000041>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC=&#x6A&#x61&#x76&#x61&#x73&#x63&#x72&#x69&#x70&#x74&#x3A&#x61&#x6C&#x65&#x72&#x74&#x28&#x27&#x58&#x53&#x53&#x27&#x29>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<IMG SRC="jav   ascript:alert(\'XSS\');">';
        $expected = '<img src="jav%20%20%20ascript%3Aalert(\'XSS\');" alt="jav   ascript:alert(\'XSS\');" />';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<SCRIPT/XSS SRC="http://xss.rocks/xss.js"></SCRIPT>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<BODY onload!#$%&()*~+-_.,:;?@[/|\]^`=alert("XSS")>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<iframe src=http://xss.rocks/scriptlet.html <';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '</TITLE><SCRIPT>alert("XSS");</SCRIPT>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<INPUT TYPE="IMAGE" SRC="javascript:alert(\'XSS\');">';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<BODY BACKGROUND="javascript:alert(\'XSS\')">';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<svg/onload=alert(\'XSS\')>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<BODY ONLOAD=alert(\'XSS\')>';
        $expected = '';
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        // test that purify does not kill dp-obscure
        $textToTest = '<p>testjo</p><ol><li><p><dp-obscure>obscured</dp-obscure></p></li><li><p>more</p></li><li><p>evenmore</p></li></ol>';
        $expected = $textToTest;
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p>testjo</p><ol><li><p><dp-obscure>obscured <strong>strong text</strong><div class="something">with div with class</div><p>and Paragraph</p></dp-obscure></p></li><li><p>more</p></li><li><p>evenmore</p></li></ol><p>something</p>';
        $expected = $textToTest;
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);

        // test that purify does not kill mark
        $textToTest = '<p>GENDERN IN DER GESPROCHENEN SPRACHE </p><p>Lange Zeit herrschte für die Menschen außerhalb der queeren <mark title="markierter Text"><s>Communities Unsicherheit</s> darüber, wie sich Gender-Kurzformen in der gesprochenen Sprache realisieren lassen. Beim Binnen-I setzte</mark> sich die Lösung durch, es als Doppelnennung auszusprechen: KollegInnen wird also zu Kollegen und <mark title="markierter Text">Kolleginnen</mark>. Dieses Verfahren kennt die Sprachgemeinschaft bereits von Kurzformen wie Hbf.oder usw., die automatisch beim Vorlesen aufgelöst werden. Für die Satz- und</p>';
        $expected = $textToTest;
        $result = $this->sut->purify($textToTest);
        static::assertEquals($expected, $result);
    }
}
