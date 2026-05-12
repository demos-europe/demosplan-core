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

use demosplan\DemosPlanCoreBundle\Exception\LanguageToolServiceException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LanguageToolService
{
    private readonly string $languageToolUrl;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
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
            throw new LanguageToolServiceException('LanguageTool service not configured: missing languagetool_url parameter');
        }

        return $this->languageToolUrl.$path;
    }
}
