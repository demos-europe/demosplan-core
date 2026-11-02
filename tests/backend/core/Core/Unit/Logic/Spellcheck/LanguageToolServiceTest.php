<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Logic\Spellcheck;

use demosplan\DemosPlanCoreBundle\Exception\LanguageToolServiceException;
use demosplan\DemosPlanCoreBundle\Logic\Spellcheck\LanguageToolService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LanguageToolServiceTest extends TestCase
{
    private ?LanguageToolService $sut = null;

    /** @var list<string>|null */
    private ?array $sentTexts = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sentTexts = [];
        // Flags every occurrence of "Fehler" like LanguageTool would, with offsets relative to the received text.
        $this->sut = $this->createService(new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            parse_str($options['body'], $body);
            $this->sentTexts[] = $body['text'];

            $matches = [];
            $position = 0;
            while (false !== ($found = mb_strpos($body['text'], 'Fehler', $position))) {
                $matches[] = ['offset' => $found, 'length' => 6, 'message' => 'm'];
                $position = $found + 6;
            }

            return new MockResponse(json_encode(['software' => ['name' => 'LanguageTool'], 'matches' => $matches], JSON_THROW_ON_ERROR));
        }));
    }

    private function createService(HttpClientInterface $client): LanguageToolService
    {
        return new LanguageToolService(
            new ArrayAdapter(),
            $client,
            new ParameterBag(['languagetool_url' => 'http://lt:8010', 'languagetool_timeout' => 30]),
        );
    }

    public function testShortTextIsSentOnceWithUnchangedOffsets(): void
    {
        $result = $this->sut->checkText(['text' => 'Ein Fehler hier', 'language' => 'de-DE']);

        self::assertSame(['Ein Fehler hier'], $this->sentTexts);
        self::assertSame(4, $result['matches'][0]['offset']);
    }

    public function testEmptyTextIsNotSent(): void
    {
        self::assertSame(['matches' => []], $this->sut->checkText(['text' => '', 'language' => 'de-DE']));
        self::assertSame([], $this->sentTexts);
    }

    public function testLongTextIsChunkedAndOffsetsPointIntoTheFullText(): void
    {
        $text = '';
        foreach (['Ä', 'Ö', 'Ü'] as $letter) {
            $text .= str_repeat($letter, 6000)." Fehler\n";
        }

        $result = $this->sut->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertCount(3, $this->sentTexts);
        self::assertSame($text, implode('', $this->sentTexts));
        self::assertCount(3, $result['matches']);
        foreach ($result['matches'] as $match) {
            self::assertSame('Fehler', mb_substr($text, $match['offset'], $match['length']));
        }
    }

    public function testOffsetsCountUtf16CodeUnits(): void
    {
        // Emoji take two UTF-16 code units each, as in JavaScript string indices.
        $line = str_repeat('😀', 6000)."\n";
        $text = $line.$line.'Fehler';

        $result = $this->sut->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertCount(2, $this->sentTexts);
        // The first chunk is 12001 UTF-16 units long; the mock reports the match at code point 6001 of the second chunk.
        self::assertSame(12001 + 6001, $result['matches'][0]['offset']);
    }

    public function testOnlyChangedChunksAreSentAgain(): void
    {
        $unchanged = str_repeat('a', 9000)."\n";
        $this->sut->checkText(['text' => $unchanged.str_repeat('c', 9000)."\n", 'language' => 'de-DE']);
        self::assertCount(2, $this->sentTexts);

        $this->sut->checkText(['text' => $unchanged.str_repeat('b', 9000)."\n", 'language' => 'de-DE']);

        self::assertCount(3, $this->sentTexts);
    }

    public function testOversizedParagraphIsSplitIntoChunks(): void
    {
        $text = '';
        for ($i = 0; $i < 2500; ++$i) {
            $text .= "Satz Nummer $i ohne Ende. ";
        }

        $this->sut->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertSame($text, implode('', $this->sentTexts));
        foreach ($this->sentTexts as $sent) {
            self::assertLessThanOrEqual(10000, mb_strlen($sent));
        }
    }

    /**
     * @return array<string, array{MockResponse}>
     */
    public static function unavailableResponses(): array
    {
        return [
            'timeout'      => [new MockResponse('', ['error' => 'timeout'])],
            'server error' => [new MockResponse('{}', ['http_code' => 503])],
        ];
    }

    #[DataProvider('unavailableResponses')]
    public function testUnavailableLanguageToolOpensCircuitBreaker(MockResponse $response): void
    {
        $requests = 0;
        $this->sut = $this->createService(new MockHttpClient(function () use (&$requests, $response): MockResponse {
            ++$requests;

            return $response;
        }));

        try {
            $this->sut->checkText(['text' => 'Ein Fehler hier', 'language' => 'de-DE']);
            self::fail('Expected the LanguageTool error to be rethrown');
        } catch (TransportExceptionInterface|ServerExceptionInterface) {
        }

        $this->expectException(LanguageToolServiceException::class);
        try {
            $this->sut->checkText(['text' => 'Noch ein Fehler', 'language' => 'de-DE']);
        } finally {
            self::assertSame(1, $requests);
        }
    }

    public function testClientErrorDoesNotOpenCircuitBreaker(): void
    {
        $requests = 0;
        $this->sut = $this->createService(new MockHttpClient(function () use (&$requests): MockResponse {
            ++$requests;

            return new MockResponse('{}', ['http_code' => 400]);
        }));

        for ($i = 0; $i < 2; ++$i) {
            try {
                $this->sut->checkText(['text' => 'Ein Fehler hier', 'language' => 'xx']);
                self::fail('Expected a client exception');
            } catch (ClientExceptionInterface) {
            }
        }

        self::assertSame(2, $requests);
    }

    public function testMissingChunksAreRequestedInParallelUpToTheLimit(): void
    {
        $started = 0;
        $read = 0;
        $inFlightOnRead = [];
        $this->sut = $this->createService(new MockHttpClient(function () use (&$started, &$read, &$inFlightOnRead): MockResponse {
            ++$started;

            return new MockResponse(
                (static function () use (&$started, &$read, &$inFlightOnRead) {
                    $inFlightOnRead[] = $started - $read;
                    ++$read;
                    yield json_encode(['matches' => []], JSON_THROW_ON_ERROR);
                })()
            );
        }));
        $text = '';
        foreach (range('a', 'e') as $letter) {
            $text .= str_repeat($letter, 9000)."\n";
        }

        $this->sut->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertSame(5, $started);
        self::assertSame(3, $inFlightOnRead[0]);
        self::assertLessThanOrEqual(3, max($inFlightOnRead));
    }
}
