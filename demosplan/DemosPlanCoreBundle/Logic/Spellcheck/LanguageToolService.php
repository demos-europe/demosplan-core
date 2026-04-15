<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Spellcheck;

use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LanguageToolService
{
    private readonly string $languageToolUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly LoggerInterface $logger,
        ParameterBagInterface $parameterBag,
    ) {
        $this->languageToolUrl = $parameterBag->get('languagetool_url');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkText(array $formData): array
    {
        $response = $this->httpClient->request('POST', $this->getUrl('/v2/check'), [
            'body'    => $formData,
            'timeout' => 10,
        ]);

        return $response->toArray();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLanguages(): array
    {
        $response = $this->httpClient->request('GET', $this->getUrl('/v2/languages'), [
            'timeout' => 10,
        ]);

        return $response->toArray();
    }

    private function getUrl(string $path): string
    {
        if ('' === $this->languageToolUrl) {
            throw new RuntimeException('LanguageTool service not configured');
        }

        return $this->languageToolUrl.$path;
    }
}
