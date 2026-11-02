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
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

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

    /**
     * While LanguageTool does not answer, further checks return no matches instead of each
     * waiting for the full request timeout.
     */
    private const CIRCUIT_BREAKER_KEY = 'languagetool_circuit_open';
    private const CIRCUIT_BREAKER_TTL = 60;

    private readonly string $url;
    private readonly int $timeout;

    public function __construct(
        private readonly CacheItemPoolInterface $cache,
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
        if ($this->cache->getItem(self::CIRCUIT_BREAKER_KEY)->isHit()) {
            return ['matches' => []];
        }

        if (!is_string($formData['text'] ?? null)) {
            return $this->requestCheck($formData)->toArray();
        }

        // Cache hits are resolved first and all missing chunks are requested before any response
        // is read, so a stalled LanguageTool costs one timeout per check, not one per chunk.
        $chunks = $this->splitIntoChunks($formData['text']) ?: [''];
        $results = [];
        $pending = [];
        foreach ($chunks as $index => $chunk) {
            $chunkData = ['text' => $chunk] + $formData;
            $item = $this->cache->getItem('languagetool_'.hash('sha256', json_encode($chunkData, JSON_THROW_ON_ERROR)));
            if ($item->isHit()) {
                $results[$index] = $item->get();
                continue;
            }
            $pending[$index] = [$item, $this->requestCheck($chunkData)];
        }

        try {
            foreach ($pending as $index => [$item, $chunkResponse]) {
                $results[$index] = $chunkResponse->toArray();
                $this->cache->save($item->set($results[$index])->expiresAfter(self::CHUNK_CACHE_TTL));
                unset($pending[$index]);
            }
        } catch (TransportExceptionInterface $e) {
            $this->openCircuitBreaker();
            foreach ($pending as [, $chunkResponse]) {
                $chunkResponse->cancel();
            }

            throw $e;
        }

        $offset = 0;
        $response = null;
        $matches = [];
        foreach ($chunks as $index => $chunk) {
            $response ??= $results[$index];

            foreach ($results[$index]['matches'] ?? [] as $match) {
                $match['offset'] += $offset;
                $matches[] = $match;
            }

            $offset += $this->utf16Length($chunk);
        }
        $response['matches'] = $matches;

        return $response;
    }

    private function openCircuitBreaker(): void
    {
        $item = $this->cache->getItem(self::CIRCUIT_BREAKER_KEY);
        $this->cache->save($item->set(true)->expiresAfter(self::CIRCUIT_BREAKER_TTL));
    }

    private function requestCheck(array $formData): ResponseInterface
    {
        return $this->httpClient->request('POST', $this->getUrl('/v2/check'), [
            'body'    => $formData,
            'timeout' => $this->timeout,
        ]);
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
