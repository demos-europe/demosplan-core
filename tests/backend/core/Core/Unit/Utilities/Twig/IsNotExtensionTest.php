<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\IsNotExtension;
use Tests\Base\UnitTestCase;

/**
 * Teste IfNotExtension
 * Class IfNotExtensionTest.
 *
 * @group UnitTest
 */
class IsNotExtensionTest extends UnitTestCase
{
    /**
     * @var IsNotExtension
     */
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new IsNotExtension(self::$container);
    }

    public function testGetFilters()
    {
        $result = $this->twigExtension->getFilters();
        static::assertTrue(is_array($result) && isset($result[0]));
        static::assertTrue($result[0] instanceof \Twig_SimpleFilter);
        static::assertTrue('isNot' === $result[0]->getName());
    }

    public function testEncode()
    {
        $returnValueNotDefined = 'return';
        $returnValueDefined = '';

        $textToTest = '';
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueNotDefined, $result);

        $result = $this->twigExtension->isNotDefined();
        static::assertEquals('', $result);

        $textToTest = '';
        $result = $this->twigExtension->isNotDefined($textToTest);
        static::assertEquals('', $result);

        $textToTest = null;
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueNotDefined, $result);

        $textToTest = false;
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueDefined, $result);

        $textToTest = true;
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueDefined, $result);

        $textToTest = [];
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueDefined, $result);

        $textToTest = ['something'];
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueDefined, $result);

        $textToTest = new \stdClass();
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueDefined, $result);

        $textToTest = 'Easy String';
        $result = $this->twigExtension->isNotDefined($textToTest, $returnValueNotDefined);
        static::assertEquals($returnValueDefined, $result);
    }

    public function testName()
    {
        $result = $this->twigExtension->getName();
        static::assertEquals('isNot_extension', $result);
    }
}
