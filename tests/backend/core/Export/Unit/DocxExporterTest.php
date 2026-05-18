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

use demosplan\DemosPlanCoreBundle\Logic\Export\DocxExporter;
use Tests\Base\UnitTestCase;

class DocxExporterTest extends UnitTestCase
{
    /** @var DocxExporter */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::$container->get(DocxExporter::class);
    }

    public function testStripXmlIllegalCharsRemovesPdfSoftHyphenArtifact(): void
    {
        $input = "ge\x02planten Windenergiegebieten";
        $expected = 'geplanten Windenergiegebieten';

        self::assertSame($expected, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsRemovesAllC0ControlsExceptTabNewlineCr(): void
    {
        $kept = "\t\n\r";
        $stripped = "\x00\x01\x02\x03\x04\x05\x06\x07\x08\x0B\x0C\x0E\x0F\x1F";

        self::assertSame($kept, $this->sut->stripXmlIllegalChars($kept.$stripped));
    }

    public function testStripXmlIllegalCharsRemovesVerticalTabAndFormFeed(): void
    {
        $input = "before\x0Bmid\x0Cafter";
        $expected = 'beforemidafter';

        self::assertSame($expected, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsKeepsDelByDesign(): void
    {
        // DEL (\x7F) is legal in XML 1.0 (#x20-#xD7FF) and is therefore kept.
        $input = "before\x7Fafter";

        self::assertSame($input, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsKeepsC1ControlsByDesign(): void
    {
        // C1 controls (U+0080-U+009F) are legal in XML 1.0 and must survive.
        $input = "before\u{0080}mid\u{0085}end\u{009F}";

        self::assertSame($input, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsKeepsPrintableAsciiAndUmlauts(): void
    {
        $input = 'Grüße aus Köln – ÄÖÜß!';

        self::assertSame($input, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsKeepsAstralPlaneEmoji(): void
    {
        $input = 'Hallo 😀 Welt';

        self::assertSame($input, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsRemovesNonCharacters(): void
    {
        $input = "before\u{FFFE}middle\u{FFFF}after";
        $expected = 'beforemiddleafter';

        self::assertSame($expected, $this->sut->stripXmlIllegalChars($input));
    }

    public function testStripXmlIllegalCharsHandlesInvalidUtf8WithoutCrashing(): void
    {
        // "\xC3\x28" is a broken 2-byte UTF-8 sequence (continuation byte missing).
        // Without mb_scrub, preg_replace would return null and trigger a TypeError.
        $input = "valid\xC3\x28tail";

        $result = $this->sut->stripXmlIllegalChars($input);

        self::assertIsString($result);
        self::assertStringContainsString('valid', $result);
        self::assertStringContainsString('tail', $result);
    }

    public function testStripXmlIllegalCharsLeavesEmptyStringEmpty(): void
    {
        self::assertSame('', $this->sut->stripXmlIllegalChars(''));
    }
}
