<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Statement;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentsByStatementsExporter;
use PhpOffice\PhpWord\Writer\WriterInterface;

class OriginalStatementDocxExporter extends CoreService
{
    public function __construct(
        private readonly SegmentsByStatementsExporter $segmentsByStatementsExporter)
    {
    }

    public function export(array $statements, Procedure $procedure): WriterInterface
    {
        return $this->segmentsByStatementsExporter->exportAll(
            [],
            $procedure,
            false,
            false,
            false,
            true,
            ...$statements
        );
    }
}
