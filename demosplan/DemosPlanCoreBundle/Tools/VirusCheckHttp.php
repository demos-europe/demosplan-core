<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Tools;

use DemosEurope\DemosplanAddon\Utilities\Json;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Class VirusCheckHttp uses https://github.com/benzino77/clamav-rest-api to check for viruses.
 */
class VirusCheckHttp implements VirusCheckInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function hasVirus(File $file): bool
    {
        try {
            $url = $this->parameterBag->get('avscan_url').'/api/v1/scan';

            $formFields = [
                'FILES' => DataPart::fromPath($file->getRealPath()),
            ];
            $formData = new FormDataPart($formFields);
            $response = $this->httpClient->request('POST', $url, [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body'    => $formData->bodyToIterable(),
            ]);
            if (200 !== $response->getStatusCode()) {
                $this->logger->error('Error in virusCheck:', [$response->getStatusCode(), $response->getContent(false)]);
                throw new Exception($response->getContent(false));
            }

            $content = $response->getContent();
            $this->logger->info('Virus check result content', [$content]);
            $result = Json::decodeToArray($response->getContent());

            if (!isset($result['data']['result'][0])) {
                $this->logger->error('Could not parse virus check result', [$result]);
                throw new InvalidArgumentException('Could not parse virus check result');
            }
            $scanResult = $result['data']['result'][0];

            if ($scanResult['is_infected']) {
                $this->logger->warning('Virus found', [$scanResult]);
            }

            return true === $scanResult['is_infected'];
        } catch (Throwable $e) {
            $this->logger->error('Error in virusCheck:', [$e]);
            throw new Exception($e->getMessage());
        }
    }
}
