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

    public function testLeavesFileNameAtExactMaxLengthUntouched(): void
    {
        $fileName = str_repeat('d', 196).'.pdf';
        self::assertSame(200, strlen($fileName));

        self::assertSame(
            $fileName,
            $this->sut->sanitizeZipPath($fileName)
        );
    }

    public function testTruncatesOverlongFileNamePreservingExtension(): void
    {
        $fileName = str_repeat('a', 250).'.pdf';

        $result = $this->sut->sanitizeZipPath($fileName);

        self::assertSame(str_repeat('a', 196).'.pdf', $result);
        self::assertSame(200, strlen($result));
    }

    public function testTruncatesOverlongFileNameWithoutExtension(): void
    {
        $fileName = str_repeat('c', 250);

        $result = $this->sut->sanitizeZipPath($fileName);

        self::assertSame(str_repeat('c', 200), $result);
    }

    public function testTruncationAccountsForPrecedingDirectorySegments(): void
    {
        $fileName = str_repeat('b', 300).'.txt';

        $result = $this->sut->sanitizeZipPath('procedure1/'.$fileName);

        self::assertSame('procedure1/'.str_repeat('b', 185).'.txt', $result);
        self::assertSame(200, strlen($result));
    }

    public function testTruncationPreservesUniquenessPrefix(): void
    {
        // The extern-id/hash that keeps entries unique is always prefixed to
        // the file name in this codebase (e.g. "STN-42_..."), so truncating
        // the tail instead of the head must not clip it.
        $fileName = 'STN-42_'.str_repeat('x', 300).'.pdf';

        $result = $this->sut->sanitizeZipPath($fileName);

        self::assertSame('STN-42_'.str_repeat('x', 189).'.pdf', $result);
        self::assertStringStartsWith('STN-42_', $result);
        self::assertStringEndsWith('.pdf', $result);
    }
}
