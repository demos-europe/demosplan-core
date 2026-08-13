<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Export;

use League\Csv\Bom;
use League\Csv\CannotInsertRecord;
use League\Csv\Exception;
use League\Csv\InvalidArgument;
use League\Csv\Writer;

class CsvExporter
{
    /**
     * @throws InvalidArgument
     * @throws CannotInsertRecord
     * @throws Exception
     */
    public function generate(array $formattedData, array $columnsDefinition): string
    {
        $csv = Writer::fromString('');
        $csv->setOutputBOM(Bom::Utf8); // Add UTF-8 BOM - Excel needs this to properly display special characters in CSV files

        $csv->setDelimiter(',');
        $csv->setEnclosure('"');
        $csv->setEscape('\\');

        // Add headers
        $headers = array_column($columnsDefinition, 'title');
        $csv->insertOne($headers);

        // Add data rows
        foreach ($formattedData as $row) {
            $csv->insertOne($row);
        }

        return $csv->toString();
    }
}
