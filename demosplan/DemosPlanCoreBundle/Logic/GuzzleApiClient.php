<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\ApiClientInterface;
use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use DemosEurope\DemosplanAddon\Utilities\Json;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Psr\Http\Message\RequestInterface;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Response;

class GuzzleApiClient implements ApiClientInterface
{
    public function __construct(private readonly GlobalConfigInterface $globalConfig, private readonly LoggerInterface $logger)
    {
    }

    /**
     * @param string $method [ApiClientInterface::GET | ApiClientInterface::POST]
     */
    public function request(string $url, array $options, string $method): string
    {
        if (!in_array($method, [ApiClientInterface::GET, ApiClientInterface::POST], true)) {
            $this->logger->error("Method $method not supported");
            throw new RuntimeException('Generic Error');
        }

        $config = $this->getHttpClientConfig();
        $guzzleClient = new Client($config);

        $this->logger->info('API URL: >'.$url.'<');
        $this->logger->info('Options: >'.Json::encode($options, JSON_THROW_ON_ERROR).'<');
        /** @var \GuzzleHttp\Psr7\Response $response */
        $response = $guzzleClient->$method($url, $options);
        $statusCode = $response->getStatusCode();
        $this->logger->info('Status Code: '.$statusCode);

        if (!in_array($statusCode, [Response::HTTP_OK, Response::HTTP_ACCEPTED])) {
            $this->logger->error(
                'Error on call AI pipeline via api.',
                [
                    'responseCode'    => $response->getStatusCode(),
                    'responseContent' => $response->getBody()->getContents(), ]
            );

            throw new RuntimeException('Generic error');
        }

        return $response->getBody()->getContents();
    }

    private function getHttpClientConfig(): array
    {
        $config = [];

        if ($this->globalConfig->isProxyEnabled()) {
            $config['proxy'] = sprintf(
                '%s:%s',
                $this->globalConfig->getProxyHost(),
                $this->globalConfig->getProxyPort()
            );
        }

        $stack = HandlerStack::create();
        $stack->push(Middleware::mapRequest(static fn(RequestInterface $request) => $request));

        $config['handler'] = $stack;

        return $config;
    }
}
