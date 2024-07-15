<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
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
use Twig\TwigFilter;

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
        self::markTestSkipped('This test was skipped because of pre-existing errors. They are most likely easily fixable but prevent us from getting to a usable state of our CI.');
        try {
            $result = $this->sut->getFunctions();
            static::assertTrue(is_array($result) && isset($result[0]));
            static::assertInstanceOf(TwigFilter::class, $result[0]);
            static::assertSame('getFormOption', $result[0]->getName());
        } catch (Exception $e) {
            $this->fail(false);
        }
    }

    public function testSaveLoginPath(): void
    {
        try {
            $loginPath = 'testValue';

            $result = $this->sut->getLoginPath();
            static::assertSame('', $result);

            $this->sut->setLoginPath($loginPath);
            $result = $this->sut->getLoginPath();
            static::assertEquals($loginPath, $result);

            $this->sut->setLoginPath($loginPath);
            $result = $this->sut->getLoginPath();
            static::assertEquals($loginPath, $result);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testSaveDisplayOrder(): void
    {
        try {
            $displayOrder = 2;

            $result = $this->sut->getDisplayOrder();
            static::assertSame(0, $result);

            $this->sut->setDisplayOrder($displayOrder);
            $result = $this->sut->getDisplayOrder();
            static::assertEquals($displayOrder, $result);

            $this->sut->setDisplayOrder($displayOrder);
            $result = $this->sut->getDisplayOrder();
            static::assertEquals($displayOrder, $result);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
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
