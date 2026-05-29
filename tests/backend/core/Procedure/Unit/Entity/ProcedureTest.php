<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Unit\Entity;

use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use Tests\Base\UnitTestCase;

class ProcedureTest extends UnitTestCase
{
    /**
     * @var Procedure
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();
        $this->sut = new Procedure();
    }

    public function testSetLocationNameStripsControlCharacters(): void
    {
        $this->sut->setLocationName("\tLausch\nammer\r");

        self::assertSame('Lauschammer', $this->sut->getLocationName());
    }

    public function testSetLocationPostCodeStripsControlCharacters(): void
    {
        $this->sut->setLocationPostCode("0\t1979");

        self::assertSame('01979', $this->sut->getLocationPostCode());
    }

    /**
     * Reproduces the production data that broke the procedure administration page:
     * a leading tab in the municipal code produced a raw control character inside
     * the JSON.parse() string literal rendered by the template.
     */
    public function testSetMunicipalCodeStripsControlCharacters(): void
    {
        $this->sut->setMunicipalCode("\t12036616");

        self::assertSame('12036616', $this->sut->getMunicipalCode());
    }

    public function testSetArsStripsControlCharacters(): void
    {
        $this->sut->setArs("\x0012036616176\x7f");

        self::assertSame('12036616176', $this->sut->getArs());
    }

    public function testSettersKeepCleanValuesUnchanged(): void
    {
        $this->sut->setLocationName('Lauchhammer');
        $this->sut->setLocationPostCode('01979');
        $this->sut->setMunicipalCode('12036616');
        $this->sut->setArs('12036616176');

        self::assertSame('Lauchhammer', $this->sut->getLocationName());
        self::assertSame('01979', $this->sut->getLocationPostCode());
        self::assertSame('12036616', $this->sut->getMunicipalCode());
        self::assertSame('12036616176', $this->sut->getArs());
    }
}
