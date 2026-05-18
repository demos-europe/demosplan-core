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

    public function testStripXmlIllegalCharsLeavesEmptyStringEmpty(): void
    {
        self::assertSame('', $this->sut->stripXmlIllegalChars(''));
    }
}
