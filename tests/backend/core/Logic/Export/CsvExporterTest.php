<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Export;

use demosplan\DemosPlanCoreBundle\Logic\Export\CsvExporter;
use League\Csv\Reader;
use PHPUnit\Framework\TestCase;

class CsvExporterTest extends TestCase
{
    private CsvExporter $sut;

    protected function setUp(): void
    {
        $this->sut = new CsvExporter();
    }

    public function testGenerateWritesHeaderAndDataRows(): void
    {
        $columnsDefinition = [
            ['key' => 'externId', 'title' => 'ID'],
            ['key' => 'text', 'title' => 'Text'],
        ];
        $formattedData = [
            ['1', 'first row'],
            ['2', 'second row'],
        ];

        $csv = $this->sut->generate($formattedData, $columnsDefinition);

        static::assertStringStartsWith("\xEF\xBB\xBF", $csv);

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords(), false);

        static::assertSame(['ID', 'Text'], $reader->getHeader());
        static::assertCount(2, $records);
        static::assertSame(['ID' => '1', 'Text' => 'first row'], $records[0]);
        static::assertSame(['ID' => '2', 'Text' => 'second row'], $records[1]);
    }

    public function testGenerateEscapesValuesContainingDelimiterAndQuotes(): void
    {
        $columnsDefinition = [['key' => 'value', 'title' => 'Value']];
        $formattedData = [['contains, comma and "quotes"']];

        $csv = $this->sut->generate($formattedData, $columnsDefinition);

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords(), false);

        static::assertSame('contains, comma and "quotes"', $records[0]['Value']);
    }

    public function testGenerateWithNoDataRowsReturnsHeaderOnly(): void
    {
        $columnsDefinition = [['key' => 'externId', 'title' => 'ID']];

        $csv = $this->sut->generate([], $columnsDefinition);

        $reader = Reader::fromString($csv);
        $reader->setHeaderOffset(0);

        static::assertSame(['ID'], $reader->getHeader());
        static::assertCount(0, iterator_to_array($reader->getRecords()));
    }

    public function testGenerateWithCustomDelimiterUsesThatDelimiter(): void
    {
        $columnsDefinition = [
            ['key' => 'topic', 'title' => 'Thema'],
            ['key' => 'tagName', 'title' => 'Schlagwortname'],
        ];
        $formattedData = [['Grundtenor der Stellungnahme', 'Positiv, Zustimmung']];

        $csv = $this->sut->generate($formattedData, $columnsDefinition, ';');

        static::assertStringContainsString('Thema;Schlagwortname', $csv);

        $reader = Reader::fromString($csv);
        $reader->setDelimiter(';');
        $reader->setHeaderOffset(0);
        $records = iterator_to_array($reader->getRecords(), false);

        static::assertSame(
            ['Thema' => 'Grundtenor der Stellungnahme', 'Schlagwortname' => 'Positiv, Zustimmung'],
            $records[0]
        );
    }
}
