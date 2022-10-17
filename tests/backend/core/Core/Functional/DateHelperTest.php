<?php

declare(strict_types=1);

namespace Tests\Core\Core\Functional;

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
        $date = new \DateTime($dateStringToCreate);
        $convertedString = $this->sut->convertDateToString($date);

        static::assertEquals($dateStringToCreate.'+0100', $convertedString);
        static::assertEquals($date, new \DateTime($dateStringToCreate));
    }
}
