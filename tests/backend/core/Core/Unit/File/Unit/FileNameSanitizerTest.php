<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\File\Unit;

use demosplan\DemosPlanCoreBundle\Logic\FileService;
use Tests\Base\UnitTestCase;

class FileNameSanitizerTest extends UnitTestCase
{
    /**
     * @var FileService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(FileService::class);
    }

    /**
     * @dataProvider getInvalidChars
     *
     * Test that all characters which are not letters or numbers are replaced by a dash.
     */
    public function testReplaceInvalidCharacters($invalid, $expected): void
    {
        $sanitizedFileName = $this->sut->sanitizeFileName($invalid);
        static::assertEquals($expected, $sanitizedFileName);
    }

    public function getInvalidChars()
    {
        $expected = 'myawesomefile.pdf';

        return [
            ['myawesomefile&%.pdf', $expected],
            [':myawesomefile.pdf', $expected],
            [':myawes&omefile%.pdf', $expected],
            ['%&:myawes&omefile%.pdf', $expected],
            ['%&myawe&%some&fi%le&%.pdf', $expected],
            ['myawesome file.pdf', 'myawesome_file.pdf'],
        ];
    }
}
