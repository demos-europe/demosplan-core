<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Base;

class FunctionalTestCaseTest extends FunctionalTestCase
{
    public function testHasValidUUIDFormat()
    {
        self::assertFalse($this->hasValidUUIDFormat(''));
        self::assertFalse($this->hasValidUUIDFormat(null));
        self::assertFalse($this->hasValidUUIDFormat('invalid-uuid'));
        self::assertTrue($this->hasValidUUIDFormat('84876825-9c36-4e33-ae24-95d14a39579D'));
        self::assertFalse($this->hasValidUUIDFormat('P9086bcf9-ee3c-4db4-8e6a-9b413e3c19cc'));
        self::assertFalse($this->hasValidUUIDFormat('zzzzzzzz-zzzz-zzzz-zzzz-zzzzzzzzzzzz'));
        self::assertTrue($this->hasValidUUIDFormat('00000000-0000-0000-0000-000000000000'));
        self::assertFalse($this->hasValidUUIDFormat('00000000-0000-0000-0000-0000-0000000'));
    }
}
