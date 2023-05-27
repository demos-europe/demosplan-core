<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use demosplan\DemosPlanCoreBundle\Http\JsonApiClient;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Tests\Base\FunctionalTestCase;

class JsonApiClientTest extends FunctionalTestCase
{
    /**
     * @var JsonApiClient
     */
    protected $sut;

    protected function setUp(): void
    {
        $this->sut = new JsonApiClient(new MockHttpClient());
        parent::setUp();
    }

    public function testApiRequest()
    {
        $method = 'POST';
        $url = 'https://my.url.de';
        $requestData = ['request' => 'data'];
        $extraHeader = ['X-MY-HEADER' => 'MyHeader'];
        $expected = [
            'json'    => $requestData,
            'headers' => array_merge(['Content-Type' => 'application/vnd.api+json'], $extraHeader),
        ];

        $mock = $this->createMock(HttpClientInterface::class);
        $mock->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo($method),
                $this->equalTo($url),
                $this->equalTo($expected),
            );

        $this->sut = new JsonApiClient($mock);
        $this->sut->apiRequest($method, $url, $requestData, $extraHeader);
    }

    public function testApiRequestOverrideContentType(): void
    {
        $method = 'POST';
        $url = 'https://my.url.de';
        $requestData = ['request' => 'data'];
        $extraHeader = ['Content-Type' => 'application/vnd.api+json-Overridden'];
        $expected = [
            'json'    => $requestData,
            'headers' => $extraHeader,
        ];

        $mock = $this->createMock(HttpClientInterface::class);
        $mock->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo($method),
                $this->equalTo($url),
                $this->equalTo($expected),
            );

        $sut = new JsonApiClient($mock);
        $sut->apiRequest($method, $url, $requestData, $extraHeader);
    }

    public function testGenerateRequestData(): void
    {
        $resourceType = 'any';
        $attributes = [
            'one'  => ['two' => 'this'],
            'next' => 'string',
        ];
        $expected = [
            'data' => [
                'type'       => $resourceType,
                'attributes' => $attributes,
            ],
        ];
        $requestData = $this->sut->createRequestData($resourceType, $attributes);
        static::assertEquals($expected, $requestData);
    }
}
