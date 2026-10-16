<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\User\Functional;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Logic\User\OrgaHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\PublicAffairsAgentHandler;
use Tests\Base\FunctionalTestCase;
use Tests\Core\User\PublicAgencyOrgaTestTrait;

class PublicAffairsAgentHandlerTest extends FunctionalTestCase
{
    use PublicAgencyOrgaTestTrait;

    protected ?PublicAffairsAgentHandler $sut = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->createSut(false);
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

    public function testIsPublicAffairsAgentIsFalseForAcceptedPublicAgencyWithoutInstitutionRoleByDefault(): void
    {
        // Arrange
        $orga = $this->createPublicAgencyWithOnlyPlanningAgencyAdminUser();

        // Act
        $result = $this->createSut(false)->isPublicAffairsAgent($orga);

        // Assert
        self::assertFalse($result);
    }

    public function testIsPublicAffairsAgentIsTrueForAcceptedPublicAgencyWithoutInstitutionRoleIfAllowed(): void
    {
        // Arrange
        $orga = $this->createPublicAgencyWithOnlyPlanningAgencyAdminUser();

        // Act
        $result = $this->createSut(true)->isPublicAffairsAgent($orga);

        // Assert
        self::assertTrue($result);
    }

    private function createSut(bool $allowOrgasWithoutUsers): PublicAffairsAgentHandler
    {
        return new PublicAffairsAgentHandler(
            self::getContainer()->get(OrgaHandler::class),
            $this->createMock(MessageBagInterface::class),
            $allowOrgasWithoutUsers
        );
    }
}
