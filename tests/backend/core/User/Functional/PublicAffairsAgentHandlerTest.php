<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use demosplan\DemosPlanCoreBundle\Logic\User\PublicAffairsAgentHandler;
use Tests\Base\FunctionalTestCase;

class PublicAffairsAgentHandlerTest extends FunctionalTestCase
{
    /**
     * @var PublicAffairsAgentHandler
     */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(PublicAffairsAgentHandler::class);
    }

    /**
     * Grep some organisations from the fixtures and check if
     * {@link PublicAffairsAgentHandler::isPublicAffairsAgent} returns the expected result
     * for each one..
     */
    public function testIsPublicAffairsAgent()
    {
        $orga = $this->getOrgaReference('testOrgaFP');
        $orga2 = $this->getOrgaReference('testOrgaPB');
        $orga3 = $this->getOrgaReference('testOrgaInvitableInstitution');
        $orga4 = $this->getOrgaReference('testOrgaPB2');
        $orga5 = $this->getOrgaReference('testOrgaInvitableInstitutionOnly');
        $orga7 = $this->getOrgaReference('deletedOrga');
        $orga8 = $this->getOrgaReference('dataInputOrga');
        $orga9 = $this->getOrgaReference('dataInputOrga2');
        self::assertTrue($this->sut->isPublicAffairsAgent($orga));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga2));
        self::assertTrue($this->sut->isPublicAffairsAgent($orga3));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga4));
        self::assertTrue($this->sut->isPublicAffairsAgent($orga5));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga7));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga8));
        self::assertFalse($this->sut->isPublicAffairsAgent($orga9));
    }
}
