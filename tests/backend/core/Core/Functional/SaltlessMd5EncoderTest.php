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

use demosplan\DemosPlanCoreBundle\Security\Encoder\SaltlessMd5Encoder;
use Tests\Base\FunctionalTestCase;

class SaltlessMd5EncoderTest extends FunctionalTestCase
{
    /**
     * @var SaltlessMd5Encoder
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(SaltlessMd5Encoder::class);
    }

    public function testNeedsRehash()
    {
        $needsRehash = $this->sut->needsRehash('anything');
        self::assertFalse($needsRehash);
    }

    public function testEncodePassword()
    {
        $pass = 'myPass';
        $encodedPass = $this->sut->hash($pass, 'saltNotUsed');
        self::assertEquals(md5($pass), $encodedPass);
    }
}
