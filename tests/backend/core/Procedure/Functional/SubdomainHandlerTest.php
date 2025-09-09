<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Exception\CustomerNotFoundException;
use demosplan\DemosPlanCoreBundle\Repository\CustomerRepository;
use demosplan\DemosPlanCoreBundle\Services\SubdomainHandler;
use demosplan\DemosPlanCoreBundle\Services\SubdomainHandlerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Tests\Base\FunctionalTestCase;

class SubdomainHandlerTest extends FunctionalTestCase
{
    private const TESTDOMAIN = 'test';

    private SubdomainHandlerInterface $subdomainHandler;

    private GlobalConfigInterface $globalConfig;

    public function setUp(): void
    {
        parent::setUp();

        $this->globalConfig = $this->getContainer()->get(GlobalConfigInterface::class);
        $this->subdomainHandler = new SubdomainHandler(
            $this->globalConfig, new NullLogger(),
            $this->createMock(CustomerRepository::class)
        );
    }

    private function getTestRequest($url): Request
    {
        $request = Request::create($url);
        $request->server->set('REQUEST_URI', $url);

        return $request;
    }

    public function testGetUrlSubdomain(): void
    {
        $defaultTestSubdomain = self::TESTDOMAIN;
        $this->globalConfig->setSubdomain($defaultTestSubdomain);

        $this->setCustomerNotFoundRepository();

        $url = 'http://blp.dplan.local';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        static::assertEquals('blp', $this->subdomainHandler->getUrlSubdomain($request));

        $url = 'http://blp.dplan.local/app_dev.php/verfahren/verwalten';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        static::assertEquals('blp', $this->subdomainHandler->getUrlSubdomain($request));

        $url = 'http://blp-dev';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertSubdomainEmpty($request);
        $url = 'http://blp-dev/app_dev.php/dplan/login';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertSubdomainEmpty($request);
        $url = 'http://blp-dev/dplan/login';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertSubdomainEmpty($request);
        $url = 'http://www.blp-dev/dplan/login';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertSubdomainEmpty($request);

        $this->setCustomerFoundRepository();

        $url = 'http://www.test.blp.dplan.local/app_dev.php/verfahren/verwalten';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertUrlSubdomainEquals(self::TESTDOMAIN, $request);
        $url = 'http://test.blp.dplan.local/app_dev.php/verfahren/verwalten';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertUrlSubdomainEquals(self::TESTDOMAIN, $request);
        $url = 'http://www.test.blp.dplan.local/app_dev.php/verfahren/verwalten';
        $request = $this->getTestRequest($url);
        $this->assertSubdomainEquals(self::TESTDOMAIN, $request);
        $this->assertUrlSubdomainEquals(self::TESTDOMAIN, $request);

        // due to some weird error in the tearDown method, this test fails on CI
        // skip is below the assertions to let the test run anyhow
        // TypeError : Cannot assign null to property Tests\Core\Procedure\Functional\SubdomainHandlerTest::$subdomainHandler of type demosplan\DemosPlanCoreBundle\Services\SubdomainHandlerInterface
        // /srv/www/tests/backend/base/FunctionalTestCase.php:112
        self::markSkippedForCIIntervention();
    }

    private function assertSubdomainEquals(string $expected, Request $request): void
    {
        static::assertEquals($expected, $this->subdomainHandler->getSubdomain($request));
    }

    private function assertSubdomainEmpty(Request $request): void
    {
        static::assertEmpty($this->subdomainHandler->getUrlSubdomain($request));
    }

    private function assertUrlSubdomainEquals(string $expected, Request $request): void
    {
        static::assertEquals($expected, $this->subdomainHandler->getUrlSubdomain($request));
    }

    private function setCustomerNotFoundRepository(): void
    {
        $mock = $this->getMockBuilder(CustomerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $mock->method('findCustomerBySubdomain')
            ->willThrowException(new CustomerNotFoundException());
        $this->subdomainHandler = new SubdomainHandler(
            $this->globalConfig, new NullLogger(),
            $mock
        );
    }

    private function setCustomerFoundRepository(): void
    {
        $mock = $this->getMockBuilder(CustomerRepository::class)
            ->disableOriginalConstructor()
            ->getMock();
        $this->subdomainHandler = new SubdomainHandler(
            $this->globalConfig, new NullLogger(),
            $mock
        );
    }
}
