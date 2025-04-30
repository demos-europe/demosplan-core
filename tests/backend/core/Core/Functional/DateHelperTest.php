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
        
        // Test that the date string follows ISO 8601 format with a timezone offset
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/', $convertedString);
        
        // Test that we can parse it back to the same date
        $parsedDate = new DateTime($convertedString);
        $this->assertEquals($date->getTimestamp(), $parsedDate->getTimestamp());
    }
    
    public function testConvertDateToStringUsesSystemTimezone(): void
    {
        // Create a date with the system's timezone
        $date = new DateTime();
        $convertedString = $this->sut->convertDateToString($date);
        
        // The timezone in the output should match PHP's current timezone
        $expectedTimezoneOffset = (new DateTime())->format('P');
        $this->assertStringEndsWith($expectedTimezoneOffset, $convertedString);
    }
}
