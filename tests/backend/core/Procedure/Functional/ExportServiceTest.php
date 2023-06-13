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
use Tests\Base\FunctionalTestCase;

class ExportServiceTest extends FunctionalTestCase
{
    /**
     * @var ExportService
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ExportService::class);
    }

    public function testGetInstitutionListPhrase(): void
    {
        $phrase = $this->sut->getInstitutionListPhrase();
        self::assertSame('Institution-Liste', $phrase);
    }
}
