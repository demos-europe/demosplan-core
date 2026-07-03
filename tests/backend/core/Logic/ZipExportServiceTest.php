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

    public function testDropsSegmentConsistingOnlyOfSpacesOrDots(): void
    {
        // A title of only spaces ("   ") or dots ("...") trims to an empty
        // segment; keeping it would yield a "proc//file.pdf" double slash, the
        // very malformed entry name Windows rejects. The empty segment is dropped.
        self::assertSame(
            'proc/file.pdf',
            $this->sut->sanitizeZipPath('proc/   /file.pdf')
        );
        self::assertSame(
            'proc/file.pdf',
            $this->sut->sanitizeZipPath('proc/.../file.pdf')
        );
    }

    public function testKeepsSegmentNamedZero(): void
    {
        // Guard against a naive array_filter dropping the falsy "0" segment.
        self::assertSame(
            'proc/0/file.pdf',
            $this->sut->sanitizeZipPath('proc/0/file.pdf')
        );
    }
}
