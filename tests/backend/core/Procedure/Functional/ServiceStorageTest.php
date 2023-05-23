<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS E-Partizipation GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureTypeData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ServiceStorage;
use Tests\Base\FunctionalTestCase;

class ServiceStorageTest extends FunctionalTestCase
{
    /**
     * @var User
     */
    private $testUser;
    /**
     * @var ProcedureType
     */
    private $procedureType;
    /**
     * @var Procedure
     */
    private $masterBlueprint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = static::$container->get(ServiceStorage::class);

        $this->testUser = $this->loginTestUser();
        $this->procedureType = $this->getReferenceProcedureType(LoadProcedureTypeData::BRK);
        $this->masterBlueprint = $this->getReferenceProcedure('masterBlaupause');
    }

    public function testAdministrationNewHandler(): void
    {
        $procedureData = [
            'r_copymaster'                => $this->masterBlueprint->getId(),
            'agencyMainEmailAddress'      => 'aValidMailAddress@daklfkls.de',
            'action'                      => 'new',
            'r_startdate'                 => '01.02.2055',
            'r_enddate'                   => '01.02.2056',
            'r_externalName'              => 'testAdded',
            'r_name'                      => 'testAdded',
            'r_master'                    => false,
            'orgaId'                      => $this->testUser->getOrganisationId(),
            'orgaName'                    => $this->testUser->getOrgaName(),
            'publicParticipationPhase'    => 'configuration',
            'r_procedure_type'            => $this->procedureType->getId(),
            'r_desc'                      => 'Test für ReleaseVorstellung',
        ];

        $procedure = $this->sut->administrationNewHandler($procedureData, $this->testUser->getId());

        static::assertSame($procedureData['r_name'], $procedure->getName());
        static::assertSame($procedureData['r_externalName'], $procedure->getExternalName());
        static::assertSame($procedureData['agencyMainEmailAddress'], $procedure->getAgencyMainEmailAddress());
        static::assertEmpty($procedure->getAgencyExtraEmailAddresses());
        static::assertSame($procedureData['r_startdate'], $procedure->getStartDate()->format('d.m.Y'));
        static::assertSame($procedureData['r_enddate'], $procedure->getEndDate()->format('d.m.Y'));
        static::assertSame($procedureData['orgaId'], $procedure->getOrgaId());
        static::assertSame($procedureData['orgaName'], $procedure->getOrgaName());
        static::assertSame($procedureData['publicParticipationPhase'], $procedure->getPublicParticipationPhase());
        static::assertSame($procedureData['r_procedure_type'], $this->procedureType->getId());
    }

    /**
     * @dataProvider exceptionDataProvider()
     */
    public function testInvalidArgumentExceptionOnAdministrationNewHandler($procedureData): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->sut->administrationNewHandler($procedureData, $this->testUser->getId());
    }

    private function getReferenceProcedureType(string $name): ProcedureType
    {
        return $this->fixtures->getReference($name);
    }

    private function getReferenceProcedure(string $name): Procedure
    {
        return $this->fixtures->getReference($name);
    }

    public function exceptionDataProvider(): array
    {
        $this->setUp();

        return [
            [[
                'r_copymaster'              => $this->masterBlueprint->getId(),
                'agencyMainEmailAddress'    => 'aValidMailAddress@daklfkls.de',
                'r_startdate'               => '01.02.2055',
                'r_enddate'                 => '01.02.2056',
                'r_externalName'            => 'testAdded',
                'r_name'                    => 'testAdded',
                'r_master'                  => false,
                'orgaId'                    => $this->testUser->getOrganisationId(),
                'orgaName'                  => $this->testUser->getOrgaName(),
                'publicParticipationPhase'  => 'configuration',
                'r_procedure_type'          => $this->procedureType->getId(),
            ]],
            [[
                'r_copymaster'              => $this->masterBlueprint->getId(),
                'agencyMainEmailAddress'    => 'aValidMailAddress@daklfkls.de',
                'action'                    => 'wrong action',
                'r_startdate'               => '01.02.2055',
                'r_enddate'                 => '01.02.2056',
                'r_externalName'            => 'testAdded',
                'r_name'                    => 'testAdded',
                'r_master'                  => false,
                'orgaId'                    => $this->testUser->getOrganisationId(),
                'orgaName'                  => $this->testUser->getOrgaName(),
                'publicParticipationPhase'  => 'configuration',
                'r_procedure_type'          => $this->procedureType->getId(),
            ]],
        ];
    }
}
