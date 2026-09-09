<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use demosplan\DemosPlanCoreBundle\Logic\Export\ExportJobFingerprint;
use PHPUnit\Framework\TestCase;

class ExportJobFingerprintTest extends TestCase
{
    public function testAssessmentTableRepeatedRequestMatches(): void
    {
        $parameters = ['procedureId' => 'p1', 'template' => 'portrait', 'exportFormat' => 'pdf'];
        $hashList = ['p1' => ['assessment' => ['hash' => 'abc']]];

        self::assertSame(
            ExportJobFingerprint::forAssessmentTable($parameters, $hashList),
            ExportJobFingerprint::forAssessmentTable($parameters, $hashList)
        );
    }

    public function testAssessmentTableIgnoresKeyOrder(): void
    {
        $hashList = ['p1' => ['assessment' => ['hash' => 'abc']]];

        self::assertSame(
            ExportJobFingerprint::forAssessmentTable(['a' => 1, 'b' => 2], $hashList),
            ExportJobFingerprint::forAssessmentTable(['b' => 2, 'a' => 1], $hashList)
        );
    }

    public function testAssessmentTableIgnoresNestedKeyOrder(): void
    {
        $left = ['filters' => ['x' => 1, 'y' => 2]];
        $right = ['filters' => ['y' => 2, 'x' => 1]];

        self::assertSame(
            ExportJobFingerprint::forAssessmentTable($left, []),
            ExportJobFingerprint::forAssessmentTable($right, [])
        );
    }

    public function testAssessmentTableDistinguishesExportFormat(): void
    {
        $hashList = ['p1' => ['assessment' => ['hash' => 'abc']]];

        self::assertNotSame(
            ExportJobFingerprint::forAssessmentTable(['exportFormat' => 'pdf'], $hashList),
            ExportJobFingerprint::forAssessmentTable(['exportFormat' => 'docx'], $hashList)
        );
    }

    /**
     * The filter selection is not part of the export parameters, so without it in the fingerprint a
     * re-export after changing the filter would be served the previous job's file.
     */
    public function testAssessmentTableDistinguishesFilterHash(): void
    {
        $parameters = ['procedureId' => 'p1', 'exportFormat' => 'pdf'];

        self::assertNotSame(
            ExportJobFingerprint::forAssessmentTable($parameters, ['p1' => ['assessment' => ['hash' => 'abc']]]),
            ExportJobFingerprint::forAssessmentTable($parameters, ['p1' => ['assessment' => ['hash' => 'xyz']]])
        );
    }

    public function testProcedureSelectionIgnoresOrder(): void
    {
        self::assertSame(
            ExportJobFingerprint::forProcedureSelection(['a', 'b', 'c'], false),
            ExportJobFingerprint::forProcedureSelection(['c', 'a', 'b'], false)
        );
    }

    public function testProcedureSelectionDistinguishesMembers(): void
    {
        self::assertNotSame(
            ExportJobFingerprint::forProcedureSelection(['a', 'b'], false),
            ExportJobFingerprint::forProcedureSelection(['a', 'b', 'c'], false)
        );
    }

    public function testProcedureSelectionDistinguishesExternalNameFlag(): void
    {
        self::assertNotSame(
            ExportJobFingerprint::forProcedureSelection(['a'], false),
            ExportJobFingerprint::forProcedureSelection(['a'], true)
        );
    }

    public function testAssessmentTableAndProcedureFingerprintsDoNotCollide(): void
    {
        self::assertNotSame(
            ExportJobFingerprint::forAssessmentTable([], []),
            ExportJobFingerprint::forProcedureSelection([], false)
        );
    }
}
