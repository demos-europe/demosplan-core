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
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\Base\FunctionalTestCase;
use Twig\TwigFunction;

/**
 * Test TwigToolsExtension.
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
            $this->getContainer(),
            $this->getContainer()->get(ParameterBagInterface::class),
            $this->getContainer()->get(TranslatorInterface::class)
        );
    }

    public function testGetFilters(): void
    {
        $result = $this->sut->getFunctions();
        self::assertTrue(is_array($result) && isset($result[0]));
        self::assertInstanceOf(TwigFunction::class, $result[0]);
        self::assertSame('getFormOption', $result[0]->getName());
    }

    public function testSaveLoginPath(): void
    {
        try {
            $loginPath = 'testValue';

            $result = $this->sut->getLoginPath();
            self::assertSame('', $result);

            $this->sut->setLoginPath($loginPath);
            $result = $this->sut->getLoginPath();
            self::assertEquals($loginPath, $result);

            $this->sut->setLoginPath($loginPath);
            $result = $this->sut->getLoginPath();
            self::assertEquals($loginPath, $result);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testSaveDisplayOrder(): void
    {
        try {
            $displayOrder = 2;

            $result = $this->sut->getDisplayOrder();
            self::assertSame(0, $result);

            $this->sut->setDisplayOrder($displayOrder);
            $result = $this->sut->getDisplayOrder();
            self::assertEquals($displayOrder, $result);

            $this->sut->setDisplayOrder($displayOrder);
            $result = $this->sut->getDisplayOrder();
            self::assertEquals($displayOrder, $result);
        } catch (Exception $e) {
            $this->fail($e->getMessage());
        }
    }

    public function testName(): void
    {
        $result = $this->sut->getName();
        self::assertEquals('twigTools_extension', $result);
    }

    public function testGetFormOption(): void
    {
        $parameterBag = $this->getContainer()->get(ParameterBagInterface::class);
        $options = $parameterBag->get('form_options');

        self::assertEquals($options, $this->sut->getFormOption(null, false, 'KEEP'));
        self::assertStringNotMatchesFormat('/.+\./', $this->sut->getFormOption('statement_submit_types.values', false)['email']);
        self::assertNull($this->sut->getFormOption('i.don.t.exist.nor.will.i.ever.because.i.am.the.weirdest'));
    }
}
