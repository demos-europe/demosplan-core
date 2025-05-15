<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Services\HTMLSanitizer;
use demosplan\DemosPlanCoreBundle\Twig\Extension\WysiwygExtension;
use Exception;
use Tests\Base\UnitTestCase;
use Twig\TwigFilter;

/**
 * Teste WysiwygExtension
 * Class WysiwygExtensionTest.
 *
 * @group UnitTest
 */
class WysiwygExtensionTest extends UnitTestCase
{
    /**
     * @var WysiwygExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new WysiwygExtension(self::getContainer(), self::getContainer()->get(HTMLSanitizer::class));
    }

    public function testGetFilters()
    {
        try {
            $result = $this->twigExtension->getFilters();
            static::assertTrue(is_array($result) && isset($result[0]));
            static::assertInstanceOf(TwigFilter::class, $result[0]);
            static::assertSame('wysiwyg', $result[0]->getName());
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    public function testWysiwyg()
    {
        $textToTest = '';
        $expected = '';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = null;
        $expected = '';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = false;
        $expected = '';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><b>';
        $expected = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><b>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><img><sup><b>';
        $expected = '<p><br><a><ol><u><i><strike><ul><li><strong><em><span><img><sup><b>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p><br><a><ol><u><i><strike>Withtext<ul><li><strong><em><span><img><sup><b>';
        $expected = '<p><br><a><ol><u><i><strike>Withtext<ul><li><strong><em><span><img><sup><b>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p></p>';
        $expected = '<p></p>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p>Withtext</p>';
        $expected = '<p>Withtext</p>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '</p>';
        $expected = '</p>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<p class="something">';
        $expected = '<p class="something">';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<del>Withtext</del>';
        $expected = '<del>Withtext</del>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<mark>Withtext</mark>';
        $expected = '<mark>Withtext</mark>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);

        $textToTest = '<s>Withtext</s>';
        $expected = '<s>Withtext</s>';
        $result = $this->twigExtension->wysiwygFilter($textToTest);
        static::assertEquals($expected, $result);
    }

    public function testAdditionalTags()
    {
        $textToTest = '<p><img src="abc">';
        $expected = '<p><img src="abc">';
        $result = $this->twigExtension->wysiwygFilter($textToTest, ['img']);
        static::assertEquals($expected, $result);
    }

    public function testName()
    {
        $result = $this->twigExtension->getName();
        static::assertTrue('wysiwyg_extension' === $result);
    }
}
