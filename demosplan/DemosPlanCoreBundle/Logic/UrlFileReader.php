<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Gets the contents of a file in an url taking care of the Proxy configuration.
 *
 * Class UrlFileReader
 */
class UrlFileReader
{
    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(HttpClientInterface $httpClient, LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * @param string $url
     */
    public function getFileContents($url): string
    {
        $this->logger->info('Connect to host -> '.$url);
        $response = $this->httpClient->request('GET', $url, ['timeout' => 10]);
        $content = '';

        try {
            $statusCode = $response->getStatusCode();

            $responseInfo = $response->getInfo();

            if (200 !== $statusCode) {
                $this->logger->error('Error in cUrl transfer', [$responseInfo]);

                // When service is defined as tls certificate problems may be a reason of failure
                // In these cases try again without tls
                if (false !== stripos($url, 'https://')) {
                    $this->logger->info('Trying to get Layer via Port 80');
                    $noSslUrl = str_replace('https://', 'http://', $url);

                    return $this->getFileContents($noSslUrl);
                }

                return $content;
            }

            $content = $response->getContent();
        } catch (Throwable $exception) {
            $this->logger->error('Could not fetch url', [$exception]);
        }

        return $content;
    }
}
