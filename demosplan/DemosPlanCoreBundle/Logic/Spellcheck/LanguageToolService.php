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
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LanguageToolService
{
    /**
     * LanguageTool needs minutes for a single text beyond ~20k characters, longer than the
     * request timeout, while texts up to 10k characters finish in seconds.
     */
    private const MAX_CHUNK_LENGTH = 10000;

    /**
     * The editor re-sends the whole text after every change. Chunk results are cached so that
     * only the chunks that changed reach LanguageTool again.
     */
    private const CHUNK_CACHE_TTL = 3600;

    private readonly string $url;
    private readonly int $timeout;

    public function __construct(
        private readonly CacheInterface $cache,
        private readonly HttpClientInterface $httpClient,
        ParameterBagInterface $parameterBag,
    ) {
        $this->url = $parameterBag->get('languagetool_url');
        $this->timeout = (int) $parameterBag->get('languagetool_timeout');
    }

    /**
     * @return array<string, mixed>
     */
    public function checkText(array $formData): array
    {
        if (!is_string($formData['text'] ?? null)) {
            return $this->requestCheck($formData);
        }

        $offset = 0;
        $response = null;
        $matches = [];
        foreach ($this->splitIntoChunks($formData['text']) as $chunk) {
            $chunkResponse = $this->checkChunk($formData, $chunk);
            $response ??= $chunkResponse;

            foreach ($chunkResponse['matches'] ?? [] as $match) {
                $match['offset'] += $offset;
                $matches[] = $match;
            }

            $offset += $this->utf16Length($chunk);
        }

        $response ??= $this->checkChunk($formData, '');
        $response['matches'] = $matches;

        return $response;
    }

    /**
     * @return array<string, mixed>
     */
    private function checkChunk(array $formData, string $chunk): array
    {
        $formData['text'] = $chunk;
        $cacheKey = 'languagetool_'.hash('sha256', json_encode($formData, JSON_THROW_ON_ERROR));

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($formData): array {
            $item->expiresAfter(self::CHUNK_CACHE_TTL);

            return $this->requestCheck($formData);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function requestCheck(array $formData): array
    {
        $response = $this->httpClient->request('POST', $this->getUrl('/v2/check'), [
            'body'    => $formData,
            'timeout' => $this->timeout,
        ]);

        return $response->toArray();
    }

    /**
     * Splits at line breaks, then sentence ends, then hard, and packs the pieces into chunks of
     * at most MAX_CHUNK_LENGTH characters. Concatenating the chunks yields the original text, so
     * match offsets only need to be shifted by the length of the preceding chunks.
     *
     * @return list<string>
     */
    private function splitIntoChunks(string $text): array
    {
        $pieces = [];
        foreach (preg_split('/(?<=\n)/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text] as $line) {
            if (mb_strlen($line) <= self::MAX_CHUNK_LENGTH) {
                $pieces[] = $line;
                continue;
            }

            foreach (preg_split('/(?<=[.!?])(?=\s)/u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [$line] as $sentence) {
                array_push($pieces, ...mb_str_split($sentence, self::MAX_CHUNK_LENGTH));
            }
        }

        $chunks = [];
        $current = '';
        foreach ($pieces as $piece) {
            if ('' !== $current && mb_strlen($current) + mb_strlen($piece) > self::MAX_CHUNK_LENGTH) {
                $chunks[] = $current;
                $current = '';
            }
            $current .= $piece;
        }
        if ('' !== $current) {
            $chunks[] = $current;
        }

        return $chunks;
    }

    /**
     * LanguageTool reports offsets in UTF-16 code units, like JavaScript string indices.
     */
    private function utf16Length(string $text): int
    {
        return intdiv(strlen(mb_convert_encoding($text, 'UTF-16LE', 'UTF-8')), 2);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getLanguages(): array
    {
        $response = $this->httpClient->request('GET', $this->getUrl('/v2/languages'), [
            'timeout' => $this->timeout,
        ]);

        return $response->toArray();
    }

    private function getUrl(string $path): string
    {
        if ('' === $this->url) {
            throw new LanguageToolServiceException('LanguageTool service not configured: missing languagetool_url parameter');
        }

        return $this->url.$path;
    }
}
