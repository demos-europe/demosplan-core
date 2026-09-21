<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\Logic\Procedure\NameGenerator;
use Tests\Base\FunctionalTestCase;

class NameGeneratorTest extends FunctionalTestCase
{
    /**
     * @var NameGenerator
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new NameGenerator();
    }

    public function testGenerateDownloadFilename()
    {
        $fileName = 'TestFi leN-am"eString.pdf';
        $expectedFileName = 'attachment;filename="TestFi leN-am\"eString.pdf"; filename*=UTF-8\'\'TestFi_leN-am%22eString.pdf';
        self::assertSame($expectedFileName, $this->sut->generateDownloadFilename($fileName));
    }

    public function testShortenProcedureNameForExportLeavesShortNameUntouched()
    {
        $procedureName = 'Bebauungsplan Nr. 42';

        self::assertSame($procedureName, $this->sut->shortenProcedureNameForExport($procedureName));
    }

    public function testShortenProcedureNameForExportTruncatesLongName()
    {
        $procedureName = 'Teilfortschreibung zum Thema Windenergie an Hand des Landesentwicklungsplans';

        self::assertSame(
            'Teilfortschreibung zum Thema W',
            $this->sut->shortenProcedureNameForExport($procedureName)
        );
    }

    public function testShortenProcedureNameForExportStripsTrailingSeparators()
    {
        // Truncating right before a word boundary must not leave a trailing space or dot,
        // which would end up directly in front of the appended separator or extension.
        self::assertSame(
            'Bebauungsplan Nr. 42 Ortsteil',
            $this->sut->shortenProcedureNameForExport('Bebauungsplan Nr. 42 Ortsteil.Nord')
        );
        self::assertSame(
            'Bebauungsplan Nr. 42 Ortsteil',
            $this->sut->shortenProcedureNameForExport('Bebauungsplan Nr. 42 Ortsteil Nord')
        );
    }

    public function testShortenProcedureNameForExportCountsMultibyteCharacters()
    {
        $procedureName = str_repeat('ä', 40);

        self::assertSame(str_repeat('ä', 30), $this->sut->shortenProcedureNameForExport($procedureName));
    }
}
