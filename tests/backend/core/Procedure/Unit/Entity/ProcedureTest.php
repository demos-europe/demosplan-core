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
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use Tests\Base\UnitTestCase;

class ProcedureTest extends UnitTestCase
{
    private const POSTCODE = '01979';
    private const MUNICIPAL_CODE = '12036616';
    private const ARS = '12036616176';

    /**
     * @var Procedure
     */
    protected $sut;

    public function setUp(): void
    {
        parent::setUp();
        $def = new ProcedurePhaseDefinition();
        $this->sut = new Procedure($def, $def);
    }

    public function testSetLocationNameStripsControlCharacters(): void
    {
        $this->sut->setLocationName("\tLausch\nammer\r");

        self::assertSame('Lauschammer', $this->sut->getLocationName());
    }

    public function testSetLocationPostCodeStripsControlCharacters(): void
    {
        $this->sut->setLocationPostCode("0\t1979");

        self::assertSame(self::POSTCODE, $this->sut->getLocationPostCode());
    }

    /**
     * Reproduces the production data that broke the procedure administration page:
     * a leading tab in the municipal code produced a raw control character inside
     * the JSON.parse() string literal rendered by the template.
     */
    public function testSetMunicipalCodeStripsControlCharacters(): void
    {
        $this->sut->setMunicipalCode("\t".self::MUNICIPAL_CODE);

        self::assertSame(self::MUNICIPAL_CODE, $this->sut->getMunicipalCode());
    }

    public function testSetArsStripsControlCharacters(): void
    {
        $this->sut->setArs("\x00".self::ARS."\x7f");

        self::assertSame(self::ARS, $this->sut->getArs());
    }

    public function testSettersKeepCleanValuesUnchanged(): void
    {
        $this->sut->setLocationName('Lauchhammer');
        $this->sut->setLocationPostCode(self::POSTCODE);
        $this->sut->setMunicipalCode(self::MUNICIPAL_CODE);
        $this->sut->setArs(self::ARS);

        self::assertSame('Lauchhammer', $this->sut->getLocationName());
        self::assertSame(self::POSTCODE, $this->sut->getLocationPostCode());
        self::assertSame(self::MUNICIPAL_CODE, $this->sut->getMunicipalCode());
        self::assertSame(self::ARS, $this->sut->getArs());
    }
}
