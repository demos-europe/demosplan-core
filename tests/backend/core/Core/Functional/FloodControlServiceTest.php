<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Event\RequestValidationStrictEvent;
use demosplan\DemosPlanCoreBundle\Exception\CookieException;
use demosplan\DemosPlanCoreBundle\Exception\IpFloodException;
use demosplan\DemosPlanCoreBundle\Logic\FloodControlService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Base\FunctionalTestCase;

class FloodControlServiceTest extends FunctionalTestCase
{
    /** @var FloodControlService */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(FloodControlService::class);
    }

    public function testCheckCookieEmptyCookie(): void
    {
        $cookieValue = '1-b-c-d';
        $event = new RequestValidationStrictEvent(
            new Request(),
            new Response(),
            'statementId',
            $cookieValue
        );
        $this->sut->checkCookie($event);

        // tests
        $responseWithCookie = $event->getResponse();
        $cookies = $responseWithCookie->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(FloodControlService::COOKIE_KEY, $cookies[0]->getName());
        static::assertSame(Json::encode(['statementId_'.$cookieValue], JSON_THROW_ON_ERROR), $cookies[0]->getValue());
    }

    public function testCheckCookieValueExists(): void
    {
        $this->expectException(CookieException::class);

        $cookieValue = '1-b-c-d';

        $request = new Request();
        $request->cookies->set(FloodControlService::COOKIE_KEY,
            Json::encode(['statementId_'.$cookieValue], JSON_THROW_ON_ERROR)
        );

        $event = new RequestValidationStrictEvent(
            $request,
            new Response(),
            'statementId',
            $cookieValue
        );
        $this->sut->checkCookie($event);
    }

    public function testCheckCookieAddValue(): void
    {
        $cookieValue = 'statementId_1-b-c-d';
        $cookieValueToAdd = '2-b-c-d';

        $request = new Request();
        $request->cookies->set(FloodControlService::COOKIE_KEY,
            Json::encode([$cookieValue], JSON_THROW_ON_ERROR)
        );
        $event = new RequestValidationStrictEvent(
            $request,
            new Response(),
            'statementId',
            $cookieValueToAdd
        );
        $this->sut->checkCookie($event);

        // tests
        $responseWithCookie = $event->getResponse();
        $cookies = $responseWithCookie->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(FloodControlService::COOKIE_KEY, $cookies[0]->getName());
        static::assertSame(
            Json::encode([$cookieValue, 'statementId_'.$cookieValueToAdd], JSON_THROW_ON_ERROR), $cookies[0]->getValue());
    }

    public function testIpFlood(): void
    {
        $reachedBreak = false;

        $requestStub = $this->getMockBuilder(Request::class)
            ->disableOriginalConstructor()
            ->getMock();
        $requestStub->method('getClientIp')
            ->willReturn('13.37.42.23');

        for ($i = 0; $i < FloodControlService::IP_FLOOD_THRESHOLD + 1; ++$i) {
            $event = new RequestValidationStrictEvent(
                $requestStub,
                null,
                'statementId',
                'NUM'
            );
            try {
                $this->sut->checkFlood($event);
            } catch (IpFloodException $e) {
                static::assertSame(
                    FloodControlService::IP_FLOOD_THRESHOLD,
                    $i,
                    'Ip flood seems to have fired prematurely'
                );
                $reachedBreak = true;
            }
        }
        static::assertTrue($reachedBreak);
    }
}
