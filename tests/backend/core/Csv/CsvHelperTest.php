<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Csv;

use demosplan\DemosPlanCoreBundle\Logic\CsvHelper;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class CsvHelperTest extends TestCase
{
    private CsvHelper $sut;
    private NameGenerator $nameGenerator;
    private Response $response;

    protected function setUp(): void
    {
        $this->nameGenerator = $this->createMock(NameGenerator::class);
        $this->nameGenerator->method('generateDownloadFilename')
            ->willReturn('attachment; filename="export_test_2023_10_10_123456.csv"');
        $this->response = new Response('test content with umlauts: äöüß');

        $this->sut = new CsvHelper();
    }

    public function testPrepareCsvResponse(): void
    {
        $preparedResponse = $this->sut->prepareCsvResponse($this->response, 'test', $this->nameGenerator);

        // Assert that the content has the UTF-8 BOM
        static::assertStringStartsWith("\xEF\xBB\xBF", $preparedResponse->getContent());
        // Assert that the content is correct
        static::assertStringContainsString('test content with umlauts: äöüß', $preparedResponse->getContent());
        // Assert that the headers are set correctly
        static::assertSame('text/csv', $preparedResponse->headers->get('Content-Type'));
        static::assertSame(
            'attachment; filename="export_test_2023_10_10_123456.csv"',
            $preparedResponse->headers->get('Content-Disposition')
        );
        // Assert that the charset is set to UTF-8
        static::assertSame('UTF-8', $preparedResponse->getCharset());
    }
}
