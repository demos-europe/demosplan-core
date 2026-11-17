<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Customer;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\CustomerInterface;
use demosplan\DemosPlanCoreBundle\Logic\Customer\CustomerUrlGenerator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;
use Throwable;

class CustomerUrlGeneratorTest extends TestCase
{
    private const ROUTE = 'test_route';

    private ?CustomerUrlGenerator $sut = null;
    private ?RequestContext $context = null;
    private GlobalConfigInterface|MockObject $globalConfig;

    protected function setUp(): void
    {
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->globalConfig->method('getProjectDomain')->willReturn('');
    }

    #[DataProvider('contextHostProvider')]
    public function testReplacesCustomerLabelOfContextHost(string $contextHost, string $expectedUrl): void
    {
        $this->createSut($contextHost);

        self::assertSame($expectedUrl, $this->sut->generate($this->createCustomer('nw'), self::ROUTE, ['id' => '1']));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function contextHostProvider(): array
    {
        return [
            'customer label is replaced'   => ['hh.example.org', 'http://nw.example.org/p/1'],
            'deeper host keeps its domain' => ['hh.stage.example.org', 'http://nw.stage.example.org/p/1'],
        ];
    }

    #[DataProvider('projectDomainContextHostProvider')]
    public function testPrefersProjectDomainOverContextHost(string $contextHost): void
    {
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->globalConfig->method('getProjectDomain')->willReturn('project-dev.example.org');
        $this->createSut($contextHost);

        self::assertSame(
            'http://nw.project-dev.example.org/p/1',
            $this->sut->generate($this->createCustomer('nw'), self::ROUTE, ['id' => '1'])
        );
    }

    /**
     * @return array<string, array{string}>
     */
    public static function projectDomainContextHostProvider(): array
    {
        return [
            'context host without customer label' => ['localhost'],
            'context host is the bare project host' => ['project-dev.example.org'],
            'context host is another customer host' => ['hh.project-dev.example.org'],
        ];
    }

    public function testPrefixesContextHostIfNoProjectDomainIsSet(): void
    {
        $this->createSut('localhost');

        self::assertSame(
            'http://nw.localhost/p/1',
            $this->sut->generate($this->createCustomer('nw'), self::ROUTE, ['id' => '1'])
        );
    }

    public function testKeepsContextHostWithoutCustomerOrSubdomain(): void
    {
        $this->createSut('hh.example.org');

        self::assertSame('http://hh.example.org/p/1', $this->sut->generate(null, self::ROUTE, ['id' => '1']));
        self::assertSame(
            'http://hh.example.org/p/1',
            $this->sut->generate($this->createCustomer(''), self::ROUTE, ['id' => '1'])
        );
    }

    public function testKeepsContextHostIfConfigured(): void
    {
        $this->globalConfig = $this->createMock(GlobalConfigInterface::class);
        $this->globalConfig->method('getProjectDomain')->willReturn('example.org');
        $this->createSut('stage.example.org', true);

        self::assertSame(
            'http://stage.example.org/p/1',
            $this->sut->generate($this->createCustomer('nw'), self::ROUTE, ['id' => '1'])
        );
    }

    public function testRestoresContextHostAfterGeneration(): void
    {
        $this->createSut('hh.example.org');

        $this->sut->generate($this->createCustomer('nw'), self::ROUTE, ['id' => '1']);

        self::assertSame('hh.example.org', $this->context->getHost());
    }

    public function testRestoresContextHostIfGenerationFails(): void
    {
        $this->createSut('hh.example.org');

        try {
            $this->sut->generate($this->createCustomer('nw'), 'unknown_route');
            self::fail('Expected the router to reject the unknown route');
        } catch (RuntimeException) {
            // expected, the host must be restored nevertheless
        }

        self::assertSame('hh.example.org', $this->context->getHost());
    }

    private function createSut(string $contextHost, bool $keepContextHost = false): void
    {
        $this->context = new RequestContext();
        $this->context->setHost($contextHost);
        $routes = new RouteCollection();
        $routes->add(self::ROUTE, new Route('/p/{id}'));
        $urlGenerator = new UrlGenerator($routes, $this->context);

        $router = $this->createMock(RouterInterface::class);
        $router->method('getContext')->willReturn($this->context);
        $router->method('generate')->willReturnCallback(
            static function (string $route, array $parameters, int $referenceType) use ($urlGenerator): string {
                try {
                    return $urlGenerator->generate($route, $parameters, $referenceType);
                } catch (Throwable $exception) {
                    throw new RuntimeException($exception->getMessage(), 0, $exception);
                }
            }
        );

        $this->sut = new CustomerUrlGenerator($this->globalConfig, $this->createMock(LoggerInterface::class), $router, $keepContextHost);
    }

    private function createCustomer(string $subdomain): CustomerInterface
    {
        $customer = $this->createMock(CustomerInterface::class);
        $customer->method('getSubdomain')->willReturn($subdomain);

        return $customer;
    }
}
