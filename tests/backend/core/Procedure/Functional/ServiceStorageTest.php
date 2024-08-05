<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
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
use Symfony\Contracts\Translation\TranslatorInterface;
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

    /** @var ServiceStorage */
    protected $sut;

    /** @var Procedure */
    private $testProcedure;

    /** @var TranslatorInterface */
    protected $translator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(ServiceStorage::class);
        $this->translator = $this->getContainer()->get(TranslatorInterface::class);
        $this->testUser = $this->loginTestUser();
        $this->procedureType = $this->getReferenceProcedureType(LoadProcedureTypeData::BRK);
        $this->masterBlueprint = $this->getReferenceProcedure('masterBlaupause');
        $this->testProcedure = $this->fixtures->getReference('testProcedure');
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

    public function testUpdatePhaseIteration(): void
    {
        $iterationValue = '3';
        $publicIterationValue = '2';

        $data = [
            'action'                                    => 'edit',
            'r_ident'                                   => $this->testProcedure->getId(),
            'r_phase_iteration'                         => $iterationValue,
            'r_public_participation_phase_iteration'    => $publicIterationValue,
        ];

        $procedure = $this->sut->administrationEditHandler($data);
        static::assertIsArray($procedure);
        /** @var Procedure $procedure */
        $procedure = $this->find(Procedure::class, $procedure['id']);

        // use equals here, because values are incoming as string but are stored as integers.
        static::assertEquals($iterationValue, $procedure->getPhaseObject()->getIteration());
        static::assertEquals($publicIterationValue, $procedure->getPublicParticipationPhaseObject()->getIteration());
    }

    /**
     * @dataProvider phaseIterationDataProvider()
     */
    public function testMandatoryErrorOnUpdatePhaseIteration($data, $expectedMandatoryError): void
    {
        $procedure = $this->sut->administrationEditHandler($data);
        static::assertIsArray($procedure);
        /** @var Procedure $procedureObject */
        $procedureObject = $this->find(Procedure::class, $this->testProcedure->getId());

        if ([] === $expectedMandatoryError) {
            if (array_key_exists('r_phase_iteration', $data)) {
                static::assertEquals($data['r_phase_iteration'], $procedureObject->getPhaseObject()->getIteration());
            }

            if (array_key_exists('r_public_participation_phase_iteration', $data)) {
                static::assertEquals(
                    $data['r_public_participation_phase_iteration'],
                    $procedureObject->getPublicParticipationPhaseObject()->getIteration()
                );
            }
        } else {
            // use equals here, because values are incoming as string but are stored as integers.
            static::assertArrayHasKey('mandatoryfieldwarning', $procedure);
            self::assertSame('error', $procedure['mandatoryfieldwarning'][0]['type']);
            self::assertSame(
                $this->translator->trans('error.phaseIteration.invalid'),
                $procedure['mandatoryfieldwarning'][0]['message']
            );

            if (array_key_exists('r_phase_iteration', $data)) {
                static::assertNotEquals($data['r_phase_iteration'], $procedureObject->getPhaseObject()->getIteration());
            }
            if (array_key_exists('r_public_participation_phase_iteration', $data)) {
                static::assertNotEquals($data['r_public_participation_phase_iteration'], $procedureObject->getPublicParticipationPhaseObject()->getIteration());
            }
        }
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

    public function phaseIterationDataProvider(): array
    {
        $this->setUp();

        return [
            [[
                'action'                                    => 'edit',
                'r_ident'                                   => $this->testProcedure->getId(),
                'r_phase_iteration'                         => '2',
            ], 'mandatoryError' => []],
            [[
                'action'                                    => 'edit',
                'r_ident'                                   => $this->testProcedure->getId(),
                'r_public_participation_phase_iteration'    => '3',
            ], 'mandatoryError' => []],
            [[
                'action'                                    => 'edit',
                'r_ident'                                   => $this->testProcedure->getId(),
                'r_phase_iteration'                         => '-3',
            ], 'mandatoryError' => $this->translator->trans('error.phaseIteration.invalid')],
            [[
                'action'                                    => 'edit',
                'r_ident'                                   => $this->testProcedure->getId(),
                'r_public_participation_phase_iteration'    => '-2',
            ], 'mandatoryError' => $this->translator->trans('error.phaseIteration.invalid')],
        ];
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
