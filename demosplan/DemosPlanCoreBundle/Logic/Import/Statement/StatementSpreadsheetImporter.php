<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Import\Statement;

use Symfony\Component\Finder\SplFileInfo;

class StatementSpreadsheetImporter extends AbstractStatementSpreadsheetImporter
{
    public function process(SplFileInfo $workbook): void
    {
        // FIXME
    }
}
