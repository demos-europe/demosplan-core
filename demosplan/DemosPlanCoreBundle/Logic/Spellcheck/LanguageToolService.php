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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
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
     * LanguageTool queues requests beyond its check threads, and queued chunks run into the
     * request timeout. Shared with other users, so kept well below the server's thread count.
     */
    private const MAX_PARALLEL_REQUESTS = 3;

    /**
     * While LanguageTool does not answer, further checks fail fast instead of each waiting for
     * the full request timeout.
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
            throw new LanguageToolServiceException('LanguageTool is unavailable, check skipped');
        }

        if (!is_string($formData['text'] ?? null)) {
            return $this->requestCheck($formData)->toArray();
        }

        $chunks = $this->splitIntoChunks($formData['text']);
        $results = [];
        $missing = [];
        foreach ($chunks as $index => $chunk) {
            $chunkData = ['text' => $chunk] + $formData;
            $item = $this->cache->getItem('languagetool_'.hash('sha256', json_encode($chunkData, JSON_THROW_ON_ERROR)));
            if ($item->isHit()) {
                $results[$index] = $item->get();
                continue;
            }
            $missing[$index] = [$item, $chunkData];
        }
        $results += $this->requestMissingChunks($missing);

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

    /**
     * Missing chunks run in parallel up to MAX_PARALLEL_REQUESTS, so a stalled LanguageTool
     * costs one timeout per check, not one per chunk.
     *
     * @param array<int, array{CacheItemInterface, array}> $missing cache item and form data per chunk index
     *
     * @return array<int, array<string, mixed>>
     */
    private function requestMissingChunks(array $missing): array
    {
        $results = [];
        $queue = array_keys($missing);
        $inFlight = [];
        try {
            while ([] !== $queue || [] !== $inFlight) {
                while ([] !== $queue && count($inFlight) < self::MAX_PARALLEL_REQUESTS) {
                    $index = array_shift($queue);
                    $inFlight[$index] = $this->requestCheck($missing[$index][1]);
                }

                $index = array_key_first($inFlight);
                $results[$index] = $inFlight[$index]->toArray();
                unset($inFlight[$index]);
                $this->cache->save($missing[$index][0]->set($results[$index])->expiresAfter(self::CHUNK_CACHE_TTL));
            }
        } catch (ExceptionInterface $e) {
            foreach ($inFlight as $chunkResponse) {
                $chunkResponse->cancel();
            }
            // Client errors stem from the request itself and say nothing about LanguageTool's health.
            if ($e instanceof TransportExceptionInterface || $e instanceof ServerExceptionInterface) {
                $this->openCircuitBreaker();
            }

            throw $e;
        }

        return $results;
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
