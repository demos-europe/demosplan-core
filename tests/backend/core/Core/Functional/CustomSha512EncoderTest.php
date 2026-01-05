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

use demosplan\DemosPlanCoreBundle\Security\Encoder\CustomSha512Encoder;
use Tests\Base\FunctionalTestCase;

class CustomSha512EncoderTest extends FunctionalTestCase
{
    /**
     * @var CustomSha512Encoder
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sut = self::getContainer()->get(CustomSha512Encoder::class);
    }

    public function testNeedsRehash()
    {
        $needsRehash = $this->sut->needsRehash(md5('anything'));
        self::assertTrue($needsRehash);
        $needsRehash = $this->sut->needsRehash(hash('sha512', 'anything'));
        self::assertFalse($needsRehash);
    }

    public function testEncodePassword()
    {
        $pass = 'myPass';
        $expected = 'ffa0a3cf47172b22decedb54014fa40fa37237335de8a095d80681c8a5810ac29e71516ceb7dea9a858fd2c4a731201e9bd1ada7daac5682742b7ef6ad429863';
        $encodedPass = $this->sut->hash($pass, 'saltUsed');
        self::assertEquals($expected, $encodedPass);

        $expected = '16b554fe811ae8a0f21015245af1da8a22f26525455c5443169e28acde997d2a293993c0db084145141a3758d403f2220239b7d5494df52220f8e7158e517afb';
        $encodedPass = $this->sut->hash($pass, 'otherSaltUsed');
        self::assertEquals($expected, $encodedPass);

        $pass = 'otherPass';
        $expected = '831b99d67ec323eeea9a727dbb186e96be98041846b309e3b89c8298e1f539f2a2495d2165c1ac4bf46027abeaf45cfb49aa30380ec0495039d956226adc193c';
        $encodedPass = $this->sut->hash($pass, 'saltUsed');
        self::assertEquals($expected, $encodedPass);
    }
}
