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
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\Multipart\FormDataPart;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class DocxImporterHttp implements DocxImporterInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        private readonly ParameterBagInterface $parameterBag,
    ) {
    }

    public function importDocx(File $file, string $elementId, string $procedure, string $category): array
    {
        $response = null;
        try {
            $formFields = [
                'docxFile' => DataPart::fromPath($file->getRealPath()),
            ];
            $formData = new FormDataPart($formFields);
            $url = $this->parameterBag->get('docx_importer_route').'/docx/import';

            $response = $this->httpClient->request(Request::METHOD_POST, $url, [
                'headers' => $formData->getPreparedHeaders()->toArray(),
                'body'    => $formData->bodyToIterable(),
            ]);

            $result = [
                'procedure'  => $procedure,
                'category'   => $category,
                'elementId'  => $elementId,
                'path'       => $file->getRealPath(),
                'paragraphs' => Json::decodeToArray($response->getContent()),
            ];
        } catch (Exception $e) {
            $this->logger->error('Error while creating docx with http: '.$e->getMessage());
            $this->logger->error('Response body: '.$response?->getContent(false) ?? '');
            throw $e;
        }

        return $result;
    }
}
