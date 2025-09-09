<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\Base64Extension;
use Exception;
use stdClass;
use Tests\Base\UnitTestCase;
use Twig\TwigFilter;

/**
 * Teste Base64Extension
 * Class Base64ExtensionTest.
 *
 * @group UnitTest
 */
class Base64ExtensionTest extends UnitTestCase
{
    private $twigExtension;

    public function setUp(): void
    {
        parent::setUp();

        $this->twigExtension = new Base64Extension(self::getContainer());
    }

    public function testGetFilters()
    {
        try {
            $result = $this->twigExtension->getFilters();
            static::assertTrue(is_array($result) && isset($result[0]));
            static::assertTrue($result[0] instanceof TwigFilter);
            static::assertTrue('base64' === $result[0]->getName());
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    public function testEncode()
    {
        try {
            $textToTest = '';
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = null;
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = false;
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = true;
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = [];
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = ['something'];
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = new stdClass();
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue('' === $result);

            $textToTest = 'Easy String';
            $expectedResult = 'RWFzeSBTdHJpbmc=';
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue($result === $expectedResult);

            $textToTest = "^1234567890ß´`+*üpoiuztrewqasdfghjklöä#'-_.:,;mnbvcxy<>|µ~}][{³²QWERTZUIOPÜ+ASDFGHJKLÖÄ#YXCVBNM<!-- uzsdfjhb";
            $expectedResult = base64_encode($textToTest);
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue($result === $expectedResult);

            $textToTest = "^1234567890ß´`+*üpoiuztrewqasdfghjklöä#'-_.:,;mnbvcxy<>|µ~}][{³²QWERTZUIOPÜ+ASDFGHJKLÖÄ#YXCVBNM<!-- uzsdfjhb

newline";
            $expectedResult = base64_encode($textToTest);
            $result = $this->twigExtension->base64Filter($textToTest);
            static::assertTrue($result === $expectedResult);
        } catch (Exception $e) {
            static::assertTrue(false);

            return;
        }
    }

    public function testName()
    {
        $result = $this->twigExtension->getName();
        static::assertEquals('base64_extension', $result);
    }
}
