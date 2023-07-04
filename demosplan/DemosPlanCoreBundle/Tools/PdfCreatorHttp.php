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

use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PdfCreatorHttp implements PdfCreatorInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function createPdf(string $content, array $pictures = []): string
    {
        try {
            $url = $this->parameterBag->get('tex2pdf_url').'/latex';
            $response = $this->httpClient->request('POST', $url, [
                'json' => [
                    'latex_code' => $content,
                    'pictures'   => $pictures,
                ],
            ]);
            // result needs to be base64 encoded
            $response = base64_encode($response->getContent());
        } catch (Exception $e) {
            $this->logger->error('Error while creating pdf with http: '.$e->getMessage());
            throw $e;
        }

        return $response;
    }
}
