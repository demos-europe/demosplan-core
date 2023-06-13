<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Functional;

use DateTime;
use demosplan\DemosPlanCoreBundle\Logic\DateHelper;
use Tests\Base\FunctionalTestCase;

class DateHelperTest extends FunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = new DateHelper();
    }

    public function testConvertDateToString(): void
    {
        $dateStringToCreate = '2014-10-26T02:30:00';
        $date = new DateTime($dateStringToCreate);
        $convertedString = $this->sut->convertDateToString($date);

        static::assertEquals($dateStringToCreate.'+0100', $convertedString);
        static::assertEquals($date, new DateTime($dateStringToCreate));
    }
}
