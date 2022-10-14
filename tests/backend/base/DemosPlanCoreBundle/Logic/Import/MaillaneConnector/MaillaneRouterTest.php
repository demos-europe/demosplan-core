<?php
declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base\DemosPlanCoreBundle\Logic\Import\MaillaneConnector;

use demosplan\DemosPlanCoreBundle\Logic\Import\MaillaneConnector\MaillaneRouter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class MaillaneRouterTest extends TestCase
{
    /**
     * @var MaillaneRouter
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();

        $parameterBag = new ParameterBag([
            'maillane_api_baseurl' => 'https://maillane/',
        ]);

        $this->sut = new MaillaneRouter($parameterBag);
    }

    public function testRoutes(): void
    {
        $routes = [
            ['https://maillane/api/account/', $this->sut->accountList()],
            ['https://maillane/api/account/1234/', $this->sut->accountDetail('1234')],
            ['https://maillane/api/account/1234/user/', $this->sut->userList('1234')],
            [
                'https://maillane/api/account/1234/user/5678/',
                $this->sut->userDetail('1234', '5678'),
            ],
        ];

        foreach ($routes as $route) {
            [$expected, $actual] = $route;
            self::assertEquals($expected, $actual);
        }
    }
}
