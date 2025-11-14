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
use PHPUnit\Framework\Attributes\DataProvider;
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
        $procedureData = $this->prepareNewProcedureDataArray();

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

    #[DataProvider('phaseIterationDataProvider')]
    public function testMandatoryErrorOnUpdatePhaseIteration($data, $expectedMandatoryError): void
    {
        $procedure = $this->sut->administrationEditHandler($data);
        static::assertIsArray($procedure);
        /** @var Procedure $procedureObject */
        $procedureObject = $this->find(Procedure::class, $this->testProcedure->getId());

        if (null === $expectedMandatoryError) {
            $this->assertValidPhaseIterationUpdate($data, $procedureObject);
        } else {
            $this->assertInvalidPhaseIterationUpdate($procedure, $data, $procedureObject, $expectedMandatoryError);
        }
    }

    private function assertValidPhaseIterationUpdate(array $data, Procedure $procedureObject): void
    {
        if (array_key_exists('r_phase_iteration', $data)) {
            static::assertEquals($data['r_phase_iteration'], $procedureObject->getPhaseObject()->getIteration());
        }

        if (array_key_exists('r_public_participation_phase_iteration', $data)) {
            static::assertEquals(
                $data['r_public_participation_phase_iteration'],
                $procedureObject->getPublicParticipationPhaseObject()->getIteration()
            );
        }
    }

    private function assertInvalidPhaseIterationUpdate(array $procedure, array $data, Procedure $procedureObject, string $expectedError): void
    {
        static::assertArrayHasKey('mandatoryfieldwarning', $procedure);
        self::assertSame('error', $procedure['mandatoryfieldwarning'][0]['type']);
        self::assertSame($expectedError, $procedure['mandatoryfieldwarning'][0]['message']);

        if (array_key_exists('r_phase_iteration', $data)) {
            static::assertNotEquals($data['r_phase_iteration'], $procedureObject->getPhaseObject()->getIteration());
        }
        if (array_key_exists('r_public_participation_phase_iteration', $data)) {
            static::assertNotEquals($data['r_public_participation_phase_iteration'], $procedureObject->getPublicParticipationPhaseObject()->getIteration());
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

        $validCases = [
            ['r_phase_iteration' => '2'],
            ['r_public_participation_phase_iteration' => '3'],
            ['r_phase_iteration'                      => '99'],
            ['r_public_participation_phase_iteration' => '98'],
        ];

        $invalidCases = [
            ['r_phase_iteration' => '-3', 'error' => 'error.phaseIteration.invalid'],
            ['r_public_participation_phase_iteration' => '-2', 'error' => 'error.publicPhaseIteration.invalid'],
            ['r_phase_iteration'                      => '101', 'error' => 'error.phaseIteration.invalid'],
            ['r_public_participation_phase_iteration' => '101', 'error' => 'error.publicPhaseIteration.invalid'],
        ];

        $testCases = [];

        // Add valid cases
        foreach ($validCases as $case) {
            $testCases[] = [$this->getPhaseIterationTestData($case), null];
        }

        // Add invalid cases
        foreach ($invalidCases as $case) {
            $testCases[] = [
                $this->getPhaseIterationTestData($case),
                $this->translator->trans($case['error']),
            ];
        }

        return $testCases;
    }

    public function exceptionDataProvider(): array
    {
        $this->setUp();

        $baseData = $this->getBaseProcedureData();

        return [
            [$baseData], // Missing action
            [array_merge($baseData, ['action' => 'wrong action'])], // Wrong action
        ];
    }

    private function getPhaseIterationTestData(array $phaseData): array
    {
        return array_merge([
            'action'  => 'edit',
            'r_ident' => $this->testProcedure->getId(),
        ], array_filter($phaseData, fn ($key) => 'error' !== $key, ARRAY_FILTER_USE_KEY));
    }

    private function getBaseProcedureData(): array
    {
        return [
            'r_copymaster'             => $this->masterBlueprint->getId(),
            'agencyMainEmailAddress'   => 'aValidMailAddress@daklfkls.de',
            'r_startdate'              => '01.02.2055',
            'r_enddate'                => '01.02.2056',
            'r_externalName'           => 'testAdded',
            'r_name'                   => 'testAdded',
            'r_master'                 => false,
            'orgaId'                   => $this->testUser->getOrganisationId(),
            'orgaName'                 => $this->testUser->getOrgaName(),
            'publicParticipationPhase' => 'configuration',
            'r_procedure_type'         => $this->procedureType->getId(),
        ];
    }

    #[DataProvider('settingDataProvider')]
    public function testProcedureSettingWithPermission(string $permission, string $attribute, string $method, bool $defaultValue): void
    {
        $this->enablePermissions([$permission]);
        $setting = $this->testProcedure->getSettings()->$method();
        static::assertSame($defaultValue, $setting);

        // Test with setting set to false (checkbox unchecked)
        $dataWithFalse = $this->getBaseEditData();
        $this->testProcedureSetting($dataWithFalse, false, $method);

        // Test with setting set to true
        $dataWithTrue = $this->getBaseEditData([$attribute => '1']);
        $this->testProcedureSetting($dataWithTrue, true, $method);
    }

    public function settingDataProvider(): array
    {
        return [
            'allowAnonymousStatementsWithPermission' => [
                'permission'   => 'field_submit_anonymous_statements',
                'attribute'    => 'allowAnonymousStatements',
                'method'       => 'getAllowAnonymousStatements',
                'defaultValue' => true,
            ],
            'allowExpandedProcedureDescriptionWithPermission' => [
                'permission'   => 'field_expand_procedure_description',
                'attribute'    => 'expandProcedureDescription',
                'method'       => 'getExpandProcedureDescription',
                'defaultValue' => false,
            ],
        ];
    }

    public function testAllowAnonymousStatementsWithoutPermission(): void
    {
        $this->disablePermissions(['field_submit_anonymous_statements']);

        // Store original value (which is set to true by default)
        $originalValue = $this->testProcedure->getSettings()->getAllowAnonymousStatements();
        static::assertTrue($originalValue);

        // Test that setting is ignored when permission is not present
        $dataWithAnonymousFalse = $this->getBaseEditData();
        $result = $this->sut->administrationEditHandler($dataWithAnonymousFalse);
        static::assertIsArray($result);

        /** @var Procedure $updatedProcedure */
        $updatedProcedure = $this->find(Procedure::class, $result['id']);
        // Should maintain original value since permission is not present
        static::assertEquals($originalValue, $updatedProcedure->getSettings()->getAllowAnonymousStatements());
    }

    private function getBaseEditData(array $additionalData = []): array
    {
        return array_merge([
            'action'            => 'edit',
            'r_ident'           => $this->testProcedure->getId(),
            'r_phase_iteration' => '1',
            'r_name'            => 'testAdded',
            'r_phase'           => 'configuration',
            'mandatoryError'    => [],
        ], $additionalData);
    }

    private function testProcedureSetting(array $data, bool $expectedValue, string $method): void
    {
        $result = $this->sut->administrationEditHandler($data);
        static::assertIsArray($result);

        /** @var Procedure $updatedProcedure */
        $updatedProcedure = $this->find(Procedure::class, $result['id']);
        static::assertEquals($expectedValue, $updatedProcedure->getSettings()->$method());
    }

    public function testAllowAnonymousStatementsDefaultValue(): void
    {
        // Create new procedure to test default value
        $procedureData = $this->prepareNewProcedureDataArray();

        $procedure = $this->sut->administrationNewHandler($procedureData, $this->testUser->getId());

        // Default value should be true
        static::assertTrue($procedure->getSettings()->getAllowAnonymousStatements());
    }

    private function prepareNewProcedureDataArray(): array
    {
        return array_merge($this->getBaseProcedureData(), [
            'agencyMainEmailAddress' => 'test@example.com',
            'action'                 => 'new',
            'r_externalName'         => 'testAnonymousDefault',
            'r_name'                 => 'testAnonymousDefault',
            'r_desc'                 => 'Test default anonymous statements value',
        ]);
    }
}
