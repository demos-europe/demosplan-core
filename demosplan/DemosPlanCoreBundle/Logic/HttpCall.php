<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\Config\GlobalConfigInterface;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

class HttpCall
{
    /**
     * @var bool
     */
    protected $proxyEnabled;
    /**
     * @var string
     */
    protected $proxyHost;
    /**
     * @var int
     */
    protected $proxyPort;
    /**
     * @var string
     */
    protected $contentType = '';
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(
        GlobalConfigInterface $globalConfig,
        private readonly HttpClientInterface $client,
        LoggerInterface $logger
    ) {
        $this->logger = $logger;
        $this->proxyEnabled = $globalConfig->isProxyEnabled();
        $this->proxyHost = $globalConfig->getProxyHost();
        $this->proxyPort = $globalConfig->getProxyPort();
    }

    /**
     * Rufe Curl POST/GET auf.
     *
     * @param string|array $data
     *
     * @return mixed
     *
     * @throws InvalidArgumentException
     */
    public function request(string $method, ?string $path, $data): array
    {
        if (null === $path || '' === $path) {
            throw new InvalidArgumentException('URL path needs to be set');
        }

        $options = [];

        if (is_array($data) && 0 < count($data) && 'GET' === strtoupper($method)) {
            $options['query'] = $data;
        }

        // Set Requestfields
        if ('POST' === strtoupper($method)) {
            $options['body'] = $data;
        }

        // Set request content type if specified
        if (0 < strlen($this->contentType)) {
            $options['headers']['Content-Type'] = $this->contentType;
        }

        // set proxy
        if (true === $this->isProxyEnabled()) {
            $proxy = trim($this->proxyHost).':'.trim($this->proxyPort);
            $options['proxy'] = $proxy;
            $this->logger->info('Use Proxy', [$proxy]);
        }

        // Get results
        try {
            $response = $this->client->request($method, $path, $options);
            $responseStatus = $response->getStatusCode();
            $this->logger->debug($response->getInfo('debug'));
            $responseContent = $response->getContent();
        } catch (Throwable $e) {
            $this->logger->error('Exception thrown during transport', [$e]);

            return ['body' => '', 'responseCode' => 500];
        }

        return ['body' => $responseContent, 'responseCode' => $responseStatus];
    }

    public function setContentType(string $contentType): void
    {
        $this->contentType = $contentType;
    }

    public function isProxyEnabled(): bool
    {
        return filter_var($this->proxyEnabled, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Whether to use proxy might be overridden in interface.
     */
    public function setProxyEnabled(bool $proxyEnabled): void
    {
        $this->proxyEnabled = $proxyEnabled;
    }
}
