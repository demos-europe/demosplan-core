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
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function file_put_contents;
use function uniqid;

class ExternalFileSaver implements ExternalFileSaverInterface
{
    public function __construct(private readonly FileService $fileService, private readonly HttpClientInterface $httpClient, private readonly LoggerInterface $logger)
    {
    }

    public function save(string $url, ?string $procedureId = null): File
    {
        $response = $this->httpClient->request('GET', $url);
        $imageContent = $response->getContent();

        if (!str_starts_with($imageContent, "\x89\x50\x4e\x47")) {
            $this->logger->info('Could not identify data as png', [bin2hex(substr($imageContent, 0, 4))]);
            throw new RuntimeException('Could not identify data as png');
        }

        $basename = uniqid(basename($url), true).'.png';
        $path = DemosPlanPath::getTemporaryPath($basename);

        // local file is valid, no need for flysystem
        file_put_contents($path, $imageContent);

        return $this->fileService->saveTemporaryLocalFile($path, $basename, null, $procedureId, FileService::VIRUSCHECK_NONE);
    }
}
