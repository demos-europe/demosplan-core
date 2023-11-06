<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Event\Plugin\TwigExtensionFormExtraFieldsEvent;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationStrictEvent;
use demosplan\DemosPlanCoreBundle\Exception\HoneypotException;
use demosplan\DemosPlanCoreBundle\Logic\FloodControlService;
use demosplan\DemosPlanCoreBundle\Repository\FloodRepository;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\FunctionalTestCase;
use Twig\Environment;

class HoneypotTest extends FunctionalTestCase
{
    /**
     * @var FloodControlService;
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $this->sut = new FloodControlService(
            $this->createMock(Environment::class),
            $this->createMock(FloodRepository::class),
            $this->createMock(GlobalConfigInterface::class),
            $this->createMock(MessageBagInterface::class),
        );
        $this->sut->setLogger(new NullLogger());
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
        // twig is mocked, so we can only check for no error thrown  and empty markup
        static::assertEquals('', $event->getMarkup());
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
