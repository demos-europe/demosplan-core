<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic;

use DemosEurope\DemosplanAddon\Contracts\ExternalFileSaverInterface;
use demosplan\DemosPlanCoreBundle\Entity\File;
use demosplan\DemosPlanCoreBundle\Utilities\DemosPlanPath;

use function file_put_contents;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

use function uniqid;

class ExternalFileSaver implements ExternalFileSaverInterface
{
    /**
     * @var FileService
     */
    private $fileService;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var HttpClientInterface
     */
    private $httpClient;

    public function __construct(
        FileService $fileService,
        HttpClientInterface $httpClient,
        LoggerInterface $logger
    ) {
        $this->fileService = $fileService;
        $this->logger = $logger;
        $this->httpClient = $httpClient;
    }

    /**
     * @throws Throwable
     */
    public function save(string $url, ?string $procedureId = null): File
    {
        $response = $this->httpClient->request('GET', $url);
        $imageContent = $response->getContent();

        if (0 !== strpos($imageContent, "\x89\x50\x4e\x47")) {
            $this->logger->info('Could not identify data as png', [bin2hex(substr($imageContent, 0, 4))]);
            throw new RuntimeException('Could not identify data as png');
        }

        $basename = uniqid(basename($url), true).'.png';
        $path = DemosPlanPath::getTemporaryPath($basename);

        file_put_contents($path, $imageContent);

        return $this->fileService->saveTemporaryFile($path, $basename, null, $procedureId, FileService::VIRUSCHECK_NONE);
    }
}
