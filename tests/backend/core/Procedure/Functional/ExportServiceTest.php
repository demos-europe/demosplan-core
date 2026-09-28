<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\Logic\Procedure\ExportService;
use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use Tests\Base\FunctionalTestCase;

class ExportServiceTest extends FunctionalTestCase
{
    /**
     * @var ExportService
     */
    protected $sut;

    protected ?ZipExportService $zipExportService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ExportService::class);
        $this->zipExportService = $this->getContainer()->get(ZipExportService::class);
    }

    public function testGetInstitutionListPhrase(): void
    {
        $phrase = $this->sut->getInstitutionListPhrase();
        self::assertSame('Institution-Liste', $phrase);
    }

    /**
     * Zip entry paths in the procedure export are built from user-entered
     * element/category titles (see ExportService). A title with a leading or
     * trailing space, or a trailing dot, produces a directory segment that
     * Windows rejects on extraction (HDDP-25). Verify the service pulled from
     * the real container trims such segments while preserving interior dots.
     */
    public function testSanitizeZipPathTrimsWindowsInvalidSegments(): void
    {
        self::assertSame(
            'Amtliche Bekanntmachung Beteiligungsverfahren/Kapitel 4.5.1/file.pdf',
            $this->zipExportService->sanitizeZipPath(
                'Amtliche Bekanntmachung Beteiligungsverfahren / Kapitel 4.5.1./file.pdf'
            )
        );
    }
}
