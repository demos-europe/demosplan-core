<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class JsonApiClient implements HttpClientInterface
{
    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Sends API request with json content type.
     *
     * @param array<string, mixed> $requestData
     *
     * @throws TransportExceptionInterface
     */
    public function apiRequest(string $method, string $url, array $requestData, array $customHeaders = []): ResponseInterface
    {
        $defaultHeaders = [
            'Content-Type' => 'application/vnd.api+json',
        ];

        return $this->request($method, $url, [
            'json'    => $requestData,
            'headers' => array_merge($defaultHeaders, $customHeaders),
        ]);
    }

    /**
     * Compares the status code of a response with an array of expected status codes.
     *
     * @param array<int, int> $expectedStatusCodes
     *
     * @throws TransportExceptionInterface
     * @throws HttpException
     */
    public function verifyApiResponse(ResponseInterface $response, array $expectedStatusCodes): void
    {
        $responseStatusCode = $response->getStatusCode();
        if (!in_array($responseStatusCode, $expectedStatusCodes, true)) {
            throw new HttpException($responseStatusCode, 'Unexpected status code was returned');
        }
    }

    /**
     * Send request.
     *
     * @param array<string, mixed> $options
     *
     * @throws TransportExceptionInterface
     */
    public function request(string $method, string $url, array $options = []): ResponseInterface
    {
        return $this->httpClient->request($method, $url, $options);
    }

    /**
     * Get streamed response.
     *
     * @param ResponseInterface|iterable<array-key, ResponseInterface> $responses
     */
    public function stream($responses, float $timeout = null): ResponseStreamInterface
    {
        return $this->httpClient->stream($responses, $timeout);
    }

    /**
     * Create a request data block to update or create a single resource.
     *
     * @param array<string, string> $attributes
     *
     * @return array<string, array<string, mixed>>
     */
    public function createRequestData(string $resourceType, array $attributes): array
    {
        return [
            'data' => [
                'type'       => $resourceType,
                'attributes' => $attributes,
            ],
        ];
    }
}
