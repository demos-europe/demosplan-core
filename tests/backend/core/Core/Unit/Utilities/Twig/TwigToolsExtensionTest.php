<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Utilities\Twig;

use demosplan\DemosPlanCoreBundle\Twig\Extension\TwigToolsExtension;
use Exception;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Twig_SimpleFunction;

/**
 * Teste TwigToolsExtension.
 *
 * @group UnitTest
 */
class TwigToolsExtensionTest extends FunctionalTestCase
{
    /**
     * @var TwigToolsExtension
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = new TwigToolsExtension(
            self::$container,
            self::$container->get(ParameterBagInterface::class),
            self::$container->get(TranslatorInterface::class)
        );
    }

    public function testGetFilters()
    {
        try {
            $result = $this->sut->getFunctions();
            static::assertTrue(is_array($result) && isset($result[0]));
            static::assertTrue($result[0] instanceof Twig_SimpleFunction);
            $callable = $result[0]->getCallable();
            static::assertTrue('getFormOption' === $callable[1]);
            static::assertTrue('getFormOption' === $result[0]->getName());
        } catch (Exception $e) {
            $this->fail(false);
        }
    }

    public function testSaveStatic()
    {
        try {
            $variableKey = 'testKey';
            $variableValue = 'testValue';

            $result = $this->sut->getStaticVariable($variableKey);
            static::assertNull($result);

            $this->sut->setStaticVariable($variableKey, $variableValue);
            $result = $this->sut->getStaticVariable($variableKey);
            static::assertEquals($variableValue, $result);

            $this->sut->setStaticVariable($variableKey, $variableValue);
            $result = $this->sut->getStaticVariable($variableKey);
            static::assertEquals($variableValue, $result);
        } catch (Exception $e) {
            $this->fail();
        }
    }

    public function testName()
    {
        $result = $this->sut->getName();
        static::assertEquals('twigTools_extension', $result);
    }

    public function testGetFormOption()
    {
        self::markSkippedForCIIntervention();
        // This test fails yet depending on which project config is used as form_options may be overridden

        $yaml = Yaml::parseFile(__DIR__.'/../../../../config/form_options.yml');
        $options = $yaml['parameters']['form_options'];

        static::assertEquals($options, $this->sut->getFormOption(null, false, 'KEEP'));

        static::assertStringNotMatchesFormat('/.+\./', $this->sut->getFormOption('statement_submit_types.values', false)['email']);
        static::assertNull($this->sut->getFormOption('i.don.t.exist.nor.will.i.ever.because.i.am.the.weirdest'));
    }
}
