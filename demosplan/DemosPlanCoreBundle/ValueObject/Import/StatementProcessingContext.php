<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\ValueObject\Import;

use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Context for processing statement rows during Excel import.
 * Groups worksheet-related parameters to reduce parameter count.
 */
final class StatementProcessingContext
{
    public function __construct(
        public Worksheet $worksheet,
        public array $columnNamesMeta,
        public string $segmentWorksheetTitle,
        public string $statementWorksheetTitle,
        public int $processedStatements,
        public float $step,
        public string $highestDataColumn = 'Z',
    ) {
    }
}
