<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Context\PluginContext;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationStrictEvent;
use demosplan\DemosPlanCoreBundle\Logic\MessageBag;
use demosplan\DemosPlanCoreBundle\Resources\config\GlobalConfig;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanTools;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormExtraFieldsEvent;
use demosplan\plugins\FloodControl\Exception\HoneypotException;
use demosplan\plugins\FloodControl\FloodControl;
use demosplan\plugins\FloodControl\Logic\FloodControlService;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\FunctionalTestCase;
use Tests\Base\PluginTestTrait;

class HoneypotTest extends FunctionalTestCase
{
    /**
     * @var FloodControlService;
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(FloodControlService::class);
    }

    /**
     * @dataProvider honeypotExceptionProvider
     */
    public function testHoneypotException($url, $loadtime): void
    {
        $paramBag = $this->getMockBuilder(ParameterBag::class)
            ->getMock();
        $paramBag->method('has')
            ->will(self::onConsecutiveCalls(true, true));
        $paramBag->method('get')
            ->will(self::onConsecutiveCalls($url, $loadtime));

        $stub = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stub->request = $paramBag;

        $event = new RequestValidationStrictEvent(
            $stub,
            $this->getMockBuilder(Response::class)->disableOriginalConstructor()->getMock(),
            'scope',
            'identifier'
        );

        try {
            $this->sut->checkHoneypot($event);
        } catch (HoneypotException $e) {
            static::assertTrue(true);

            return;
        }

        self::fail('Test Honeypot mit Url: '.$url.' und Loadtime: '.$loadtime.' fehlgeschlagen');
    }

    /**
     * @dataProvider honeypotExceptionProvider
     */
    public function testHoneypotExceptionNoLoadtime($url): void
    {
        $paramBag = $this->getMockBuilder(ParameterBag::class)
            ->getMock();
        $paramBag->method('has')
            ->will(self::onConsecutiveCalls(true, false));
        $paramBag->method('get')
            ->will(self::onConsecutiveCalls($url));

        $stub = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stub->request = $paramBag;

        $event = new RequestValidationStrictEvent(
            $stub,
            null,
            'scope',
            'identifier'
        );

        try {
            $this->sut->checkHoneypot($event);
        } catch (HoneypotException $e) {
            static::assertTrue(true);

            return;
        }

        self::fail('Test Honeypot mit Url: '.$url.' und keiner Loadtime fehlgeschlagen');
    }

    /**
     * @dataProvider honeypotValidProvider
     */
    public function testHoneypotValid($loadtime): void
    {
        $paramBag = $this->getMockBuilder(ParameterBag::class)
            ->getMock();
        $paramBag->method('has')
            ->will(self::onConsecutiveCalls(false, true));
        $paramBag->method('get')
            ->will(self::onConsecutiveCalls($loadtime));

        $stub = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $stub->request = $paramBag;

        $event = new RequestValidationStrictEvent(
            $stub,
            null,
            'scope',
            'identifier'
        );

        try {
            $this->sut->checkHoneypot($event);
        } catch (\demosplan\plugins\FloodControl\Exception\HoneypotException $e) {
            self::fail('Test Honeypot loadtime '.$loadtime.' failed but shouldnt ');
        }
        self::assertTrue(true);
    }

    public function testHoneypotMarkup(): void
    {
        $event = new TwigExtensionFormExtraFieldsEvent();
        $this->sut->getHoneypotMarkup(
            $event
        );
        $rawTwigMarkup = file_get_contents(DemosPlanPath::getRootPath('templates/bundles/DemosPlanCoreBundle/DemosPlanCore/floodControl/honeypotFields.html.twig'));
        $rawTwigMarkup = str_replace('{{ "now"|date("U") }}"', date('U'), $rawTwigMarkup);

        // check that markup differs at maximum in some seconds diff in r_loadtime
        static::assertLessThan(2, levenshtein($event->getMarkup(), $rawTwigMarkup));
    }

    public function honeypotExceptionProvider(): array
    {
        return [
            ['someData', time()],
            ['someData', time() - 20],
            ['', time() + 200],
        ];
    }

    public function honeypotValidProvider(): array
    {
        return [
            [time() - 200],
            [time() - 20000],
            [time() - 20],
        ];
    }
}
