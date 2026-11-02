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

use demosplan\DemosPlanCoreBundle\Logic\Spellcheck\LanguageToolService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class LanguageToolServiceTest extends TestCase
{
    /** @var list<string> */
    private array $sentTexts = [];

    /**
     * Flags every occurrence of "Fehler" like LanguageTool would, with offsets relative to the received text.
     */
    private function createService(): LanguageToolService
    {
        $this->sentTexts = [];
        $client = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            parse_str($options['body'], $body);
            $this->sentTexts[] = $body['text'];

            $matches = [];
            $position = 0;
            while (false !== ($found = mb_strpos($body['text'], 'Fehler', $position))) {
                $matches[] = ['offset' => $found, 'length' => 6, 'message' => 'm'];
                $position = $found + 6;
            }

            return new MockResponse(json_encode(['software' => ['name' => 'LanguageTool'], 'matches' => $matches]));
        });

        return new LanguageToolService(
            new ArrayAdapter(),
            $client,
            new ParameterBag(['languagetool_url' => 'http://lt:8010', 'languagetool_timeout' => 30]),
        );
    }

    public function testShortTextIsSentOnceWithUnchangedOffsets(): void
    {
        $service = $this->createService();

        $result = $service->checkText(['text' => 'Ein Fehler hier', 'language' => 'de-DE']);

        self::assertSame(['Ein Fehler hier'], $this->sentTexts);
        self::assertSame(4, $result['matches'][0]['offset']);
    }

    public function testLongTextIsChunkedAndOffsetsPointIntoTheFullText(): void
    {
        $service = $this->createService();
        $text = '';
        foreach (['Ä', 'Ö', 'Ü'] as $letter) {
            $text .= str_repeat($letter, 6000)." Fehler\n";
        }

        $result = $service->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertCount(3, $this->sentTexts);
        self::assertSame($text, implode('', $this->sentTexts));
        self::assertCount(3, $result['matches']);
        foreach ($result['matches'] as $match) {
            self::assertSame('Fehler', mb_substr($text, $match['offset'], $match['length']));
        }
    }

    public function testOffsetsCountUtf16CodeUnits(): void
    {
        $service = $this->createService();
        // Emoji take two UTF-16 code units each, as in JavaScript string indices.
        $line = str_repeat('😀', 6000)."\n";
        $text = $line.$line.'Fehler';

        $result = $service->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertCount(2, $this->sentTexts);
        // The first chunk is 12001 UTF-16 units long; the mock reports the match at code point 6001 of the second chunk.
        self::assertSame(12001 + 6001, $result['matches'][0]['offset']);
    }

    public function testOnlyChangedChunksAreSentAgain(): void
    {
        $service = $this->createService();
        $unchanged = str_repeat('a', 9000)."\n";
        $service->checkText(['text' => $unchanged.str_repeat('c', 9000)."\n", 'language' => 'de-DE']);
        self::assertCount(2, $this->sentTexts);

        $service->checkText(['text' => $unchanged.str_repeat('b', 9000)."\n", 'language' => 'de-DE']);

        self::assertCount(3, $this->sentTexts);
    }

    public function testOversizedParagraphIsSplitIntoChunks(): void
    {
        $service = $this->createService();
        $text = '';
        for ($i = 0; $i < 2500; ++$i) {
            $text .= "Satz Nummer $i ohne Ende. ";
        }

        $service->checkText(['text' => $text, 'language' => 'de-DE']);

        self::assertSame($text, implode('', $this->sentTexts));
        foreach ($this->sentTexts as $sent) {
            self::assertLessThanOrEqual(10000, mb_strlen($sent));
        }
    }
}
