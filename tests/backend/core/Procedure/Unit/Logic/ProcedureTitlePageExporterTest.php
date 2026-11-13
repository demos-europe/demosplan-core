<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Unit\Logic;

use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureTitlePageExporter;
use Tests\Base\FunctionalTestCase;
use PhpOffice\PhpWord\Settings;

class ProcedureTitlePageExporterTest extends FunctionalTestCase
{
    public function testCreatesPdf(): void
    {
        /** @var Procedure $procedure */
        $procedure = $this->getReference('testProcedure');
        $sut = new ProcedureTitlePageExporter(self::getContainer()->get('translator'));

        Settings::setPdfRendererPath(self::getContainer()->getParameter('pdf_renderer_path'));
        Settings::setPdfRendererName(self::getContainer()->getParameter('pdf_renderer_name'));

        $path = tempnam(sys_get_temp_dir(), 'titlepage_').'.pdf';
        $sut->createWriter($procedure)->save($path);

        $content = file_get_contents($path);
        unlink($path);

        self::assertStringStartsWith('%PDF', $content);
    }
}
