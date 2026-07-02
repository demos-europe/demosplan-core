<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic;

use demosplan\DemosPlanCoreBundle\Logic\ZipExportService;
use Tests\Base\FunctionalTestCase;

class ZipExportServiceTest extends FunctionalTestCase
{
    /**
     * @var ZipExportService|null
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ZipExportService::class);
    }

    public function testTrimsTrailingSpaceFromDirectorySegment(): void
    {
        self::assertSame(
            'Amtliche Bekanntmachung Beteiligungsverfahren/file.pdf',
            $this->sut->sanitizeZipPath('Amtliche Bekanntmachung Beteiligungsverfahren /file.pdf')
        );
    }

    public function testTrimsLeadingSpaceFromDirectorySegment(): void
    {
        self::assertSame(
            'Dokumente/Dokumenter pa dansk/file.pdf',
            $this->sut->sanitizeZipPath('Dokumente/ Dokumenter pa dansk/file.pdf')
        );
    }

    public function testStripsTrailingDotFromSegment(): void
    {
        self::assertSame(
            'category/file.pdf',
            $this->sut->sanitizeZipPath('category./file.pdf')
        );
    }

    public function testPreservesInteriorDots(): void
    {
        self::assertSame(
            'Kapitel 4.5.1.1/Udkast_plantekst.pdf',
            $this->sut->sanitizeZipPath('Kapitel 4.5.1.1/Udkast_plantekst.pdf')
        );
    }

    public function testFoldsNonAsciiCharacters(): void
    {
        // "ä"/"ö" fold to "a"/"o", "ü" to "u".
        self::assertSame(
            'danischer/Ollegard.pdf',
            $this->sut->sanitizeZipPath('dänischer/Öllegård.pdf')
        );
    }

    public function testLeavesAlreadyValidPathUntouched(): void
    {
        self::assertSame(
            'procedure/Planungsdokumente/file.pdf',
            $this->sut->sanitizeZipPath('procedure/Planungsdokumente/file.pdf')
        );
    }
}
