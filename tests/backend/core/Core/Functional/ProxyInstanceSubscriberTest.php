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
use demosplan\DemosPlanCoreBundle\EventSubscriber\ProxyInstanceSubscriber;
use demosplan\DemosPlanCoreBundle\Logic\ProcedureStatisticsService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Base\FunctionalTestCase;
use Tests\Base\MockMethodDefinition;

class ProxyInstanceSubscriberTest extends FunctionalTestCase
{
    private const URL = 'https://something.de/';

    /** @var ProcedureStatisticsService */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $mockMethods = [
            new MockMethodDefinition('getGatewayRedirectURL', self::URL),
        ];
        $globalConfig = $this->getMock(GlobalConfigInterface::class, $mockMethods);
        $this->sut = new ProxyInstanceSubscriber($globalConfig);
    }

    /**
     * @dataProvider getDataProviderToken
     */
    public function testSanitizeToken($data): void
    {
        $requestEvent = $this->getRequestEvent($data['input']);
        $this->sut->onKernelRequest($requestEvent);
        $this->assertToken($data['expected'], $requestEvent->getResponse());
    }

    public function getDataProviderToken(): array
    {
        return [
            [
                [
                    'input'    => '123kdLo',
                    'expected' => '123kdLo',
                ],
            ],
            [
                [
                    'input'    => '123ä?!"§$%&/()=?:kdLox',
                    'expected' => '123kdLox',
                ],
            ],
            [
                [
                    'input'    => '123%E4%3F%21%22%A7%24%25%26/%28%29%3D%3F%3AkdLo3',
                    'expected' => '123kdLo3',
                ],
            ],
            [
                [
                    'input'    => 'CC99FCB3-7E40-4830-BB1D-2EF31DABEBCB',
                    'expected' => 'CC99FCB3-7E40-4830-BB1D-2EF31DABEBCB',
                ],
            ],
            [
                [
                    // broken Token is a valid result on invalid input
                    'input'    => '123&#228;?!&quot;&#167;$%&amp;/()=?:kdLo7',
                    'expected' => '123228quot167ampkdLo7',
                ],
            ],
        ];
    }

    private function getRequestEvent(string $token): RequestEvent
    {
        $request = Request::create('any', 'GET', ['Token' => $token]);

        return new RequestEvent(self::$kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function assertToken(string $expectedToken, Response $response): void
    {
        self::assertEquals(self::URL.'redirect/?Token='.$expectedToken, $response->getTargetUrl());
    }
}
