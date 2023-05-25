<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Core\Unit\Response;

use DemosEurope\DemosplanAddon\Response\APIResponse;
use Tests\Base\UnitTestCase;

class APIResponseTest extends UnitTestCase
{
    /**
     * @var APIResponse
     */
    protected $sut;

    public function setUp(): void
    {
        $this->sut = new APIResponse();
    }

    protected function tearDown(): void
    {
        $this->sut = null;
    }

    public function testResponseHasCorrectContentType()
    {
        self::assertEquals('application/vnd.api+json; charset=utf-8', $this->sut->headers->get('Content-type'));
    }
}
