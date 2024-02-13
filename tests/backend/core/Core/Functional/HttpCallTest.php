<?php
declare(strict_types=1);

namespace Tests\Core\Core\Functional;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Logic\HttpCall;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class HttpCallTest extends TestCase
{

    public function testRequestThrowsExceptionIfPathNotSet(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $logger = $this->createMock(LoggerInterface::class);
        $globalConfig = $this->createMock(GlobalConfigInterface::class);

        $httpCall = new HttpCall($globalConfig, $httpClient, $logger);
        $httpCall->request('GET', null, []);
    }

    public function testRequestUsesGetParametersWhenRequestMethodIsGet(): void
    {
        $path = '/example';
        $data = ['key' => 'value'];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with('GET', $path, ['query' => $data])
            ->willReturn(new MockResponse('Response content'));

        $logger = $this->createMock(LoggerInterface::class);
        $globalConfig = $this->createMock(GlobalConfigInterface::class);

        $httpCall = new HttpCall($globalConfig, $httpClient, $logger);
        $httpCall->request('GET', $path, $data);
    }

    /**
     * @dataProvider provideRequestMethods
     */
    public function testRequestUsesOptionsCorrectly($proxyHost, $proxyPort, $expected): void
    {
        $method = 'POST';
        $path = '/example';
        $data = ['key' => 'value'];

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient->expects($this->once())
            ->method('request')
            ->with(
                $method,
                $path,
                [
                    'body' => $data,
                    'headers' => ['Content-Type' => 'application/json'], // Assuming content type is set
                    'proxy' => $expected,
                ]
            )
            ->willReturn(new MockResponse('Response content'));

        $logger = $this->createMock(LoggerInterface::class);
        $globalConfig = $this->createMock(GlobalConfigInterface::class);
        $globalConfig->method('isProxyEnabled')->willReturn(true);
        $globalConfig->method('getProxyHost')->willReturn($proxyHost);
        $globalConfig->method('getProxyPort')->willReturn($proxyPort);

        $httpCall = new HttpCall($globalConfig, $httpClient, $logger);
        $httpCall->setContentType('application/json'); // Set content type

        $httpCall->request($method, $path, $data);
    }

    public function provideRequestMethods(): array
    {
        return [
            ['proxy.example.com', '8080', 'proxy.example.com:8080'],
            [' proxy.example.com ', ' 8080 ', 'proxy.example.com:8080'],
        ];
    }
}
