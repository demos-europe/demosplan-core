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
     * @var GenerateName
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
}
