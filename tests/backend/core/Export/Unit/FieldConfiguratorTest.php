<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Export\Unit;

use demosplan\DemosPlanCoreBundle\Entity\ExportFieldsConfiguration;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Exception\MissingDataException;
use demosplan\DemosPlanCoreBundle\Logic\Export\FieldConfigurator;
use demosplan\DemosPlanProcedureBundle\Logic\ProcedureService;
use Tests\Base\UnitTestCase;

class FieldConfiguratorTest extends UnitTestCase
{
    /** @var FieldConfigurator */
    protected $sut;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = $this->getContainer()->get(FieldConfigurator::class);
    }

    public function testDefaultEntity(): void
    {
        $defaultExportFieldsConfiguration = $this->getTestExportFieldConfiguration();

        static::assertIsObject($defaultExportFieldsConfiguration);
        static::assertEquals(true, $defaultExportFieldsConfiguration->isIdExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isStatementNameExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isCreationDateExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isProcedureNameExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isProcedurePhaseExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isVotesNumExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isUserStateExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isUserGroupExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isUserOrganisationExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isUserPositionExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isInstitutionExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isPublicParticipationExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isOrgaNameExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isDepartmentNameExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isSubmitterNameExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isShowInPublicAreaExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isDocumentExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isParagraphExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isFilesExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isAttachmentsExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isPriorityExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isEmailExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isPhoneNumberExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isStreetExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isStreetNumberExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isPostalCodeExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isCityExportable());
        static::assertEquals(true, $defaultExportFieldsConfiguration->isInstitutionOrCitizenExportable());
    }

    private function getTestProcedure($name = 'testProcedure2'): Procedure
    {
        return $this->getProcedureReference($name);
    }

    private function getTestExportFieldConfiguration($name = 'defaultExportFieldsConfiguration'): ExportFieldsConfiguration
    {
        return $this->getReference($name);
    }

    public function testAdd(): void
    {
        $procedure = $this->getTestProcedure();
        static::assertCount(1, $procedure->getExportFieldsConfigurations());
        $countBefore = $this->countEntries(ExportFieldsConfiguration::class);

        $newExportFieldsConfiguration = new ExportFieldsConfiguration($procedure);
        $this->sut->add($newExportFieldsConfiguration);
        static::assertCount(2, $procedure->getExportFieldsConfigurations());
        static::assertSame($newExportFieldsConfiguration->getProcedure()->getId(), $procedure->getId());
        static::assertSame($procedure->getExportFieldsConfigurations()[1]->getId(), $newExportFieldsConfiguration->getId());

        $countAfter = $this->countEntries(ExportFieldsConfiguration::class);
        static::assertSame($countBefore + 1, $countAfter);
    }

    public function testGet(): void
    {
        $defaultExportFieldsConfiguration = $this->getTestExportFieldConfiguration();

        $foundExportFieldsConfiguration1 = $this->sut->get($defaultExportFieldsConfiguration->getId());
        static::assertEquals($defaultExportFieldsConfiguration, $foundExportFieldsConfiguration1);
    }

    public function testUpdate(): void
    {
        $defaultExportFieldsConfiguration = $this->getTestExportFieldConfiguration();

        $defaultExportFieldsConfiguration->setInstitutionOrCitizenExportable(false);
        $defaultExportFieldsConfiguration->setOrgaNameExportable(false);
        $defaultExportFieldsConfiguration->setEmailExportable(false);
        $defaultExportFieldsConfiguration->setPhoneNumberExportable(false);
        $defaultExportFieldsConfiguration->setProcedurePhaseExportable(false);
        $defaultExportFieldsConfiguration->setStreetNumberExportable(false);
        $defaultExportFieldsConfiguration->setDocumentExportable(false);
        $defaultExportFieldsConfiguration->setParagraphExportable(false);
        $defaultExportFieldsConfiguration->setUserGroupExportable(false);
        $defaultExportFieldsConfiguration->setUserOrganisationExportable(false);

        $this->sut->update($defaultExportFieldsConfiguration);
        $foundExportFieldsConfiguration2 = $this->sut->get($defaultExportFieldsConfiguration->getId());

        static::assertEquals($defaultExportFieldsConfiguration, $foundExportFieldsConfiguration2);
        static::assertFalse($foundExportFieldsConfiguration2->isInstitutionOrCitizenExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isOrgaNameExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isEmailExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isPhoneNumberExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isProcedurePhaseExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isStreetNumberExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isDocumentExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isParagraphExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isUserGroupExportable());
        static::assertFalse($foundExportFieldsConfiguration2->isUserOrganisationExportable());
    }

    public function testDelete(): void
    {
        $testProcedure = $this->getTestProcedure();

        /** @var ExportFieldsConfiguration $exportConfig */
        $exportConfig = $this->find(ExportFieldsConfiguration::class, $testProcedure->getDefaultExportFieldsConfiguration()->getId());
        static::assertCount(1, $testProcedure->getExportFieldsConfigurations());
        static::assertInstanceOf(ExportFieldsConfiguration::class, $exportConfig);

        $id = $exportConfig->getId();
        $this->sut->delete($exportConfig);
        // DB-Entry deleted?
        static::assertNull($this->sut->get($id));

        static::assertCount(0, $testProcedure->getExportFieldsConfigurations());
        $this->expectException(MissingDataException::class);
        static::assertFalse($testProcedure->getDefaultExportFieldsConfiguration());
    }

    /**
     * Procedure - ExportFieldConfiguration is nullable = false.
     * ExportFieldConfiguration - Procedure is nullable = false.
     * This test covering the test-setup to ensure, each procedure has an related ExportFieldConfiguration and vice versa.
     */
    public function testTheTestSetup(): void
    {
        /** @var ExportFieldsConfiguration[] $configs */
        $configs = $this->getEntries(ExportFieldsConfiguration::class);
        foreach ($configs as $config) {
            static::assertInstanceOf(Procedure::class, $config->getProcedure());
            static::assertInstanceOf(ExportFieldsConfiguration::class, $config->getProcedure()->getDefaultExportFieldsConfiguration());
        }

        /** @var Procedure[] $allProcedures */
        $allProcedures = $this->getEntries(Procedure::class);
        foreach ($allProcedures as $procedure) {
            static::assertInstanceOf(ExportFieldsConfiguration::class, $procedure->getDefaultExportFieldsConfiguration());
        }
    }

    public function getTestMasterBlueprint(): Procedure
    {
        return $this->getEntries(Procedure::class, ['masterTemplate' => true])[0];
    }

    public function getProcedureType($name = 'testProcedureType1'): ProcedureType
    {
        return $this->getProcedureTypeReference($name);
    }

    public function testValuesOnCreateProcedure(): void
    {
        self::markSkippedForCIIntervention();

        $masterBlueprint = $this->getTestMasterBlueprint();
        static::assertCount(1, $masterBlueprint->getExportFieldsConfigurations());
        $masterExportConfig = $masterBlueprint->getDefaultExportFieldsConfiguration();
        $amountOfProceduresBefore = $this->countEntries(Procedure::class);
        $amountOfExportFieldConfigurationBefore = $this->countEntries(ExportFieldsConfiguration::class);

        $procedureService = $this->getContainer()->get(ProcedureService::class);

        $procedureArray = [
            'copymaster'                => $masterBlueprint->getId(),
            'desc'                      => '',
            'startDate'                 => '01.02.2012',
            'endDate'                   => '01.02.2012',
            'externalName'              => 'testAdded',
            'name'                      => 'testAdded',
            'master'                    => false,
            'orgaId'                    => $this->getTestProcedure()->getOrgaId(),
            'orgaName'                  => $this->getTestProcedure()->getOrga()->getName(),
            'logo'                      => 'some:logodata:string',
            'publicParticipationPhase'  => 'closed',
            'procedureType'             => $this->getProcedureType(),
        ];

        $newCreatedProcedure = $procedureService->addProcedureEntity(
            $procedureArray,
            $this->getUserReference('testUser')->getId()
        );

        $amountOfProceduresAfter = $this->countEntries(Procedure::class);
        $amountOfExportFieldConfigurationAfter = $this->countEntries(ExportFieldsConfiguration::class);
        static::assertSame($amountOfProceduresBefore + 1, $amountOfProceduresAfter);
        static::assertSame($amountOfExportFieldConfigurationBefore + 1, $amountOfExportFieldConfigurationAfter);

        // reload and check new created procedure:
        $newCreatedProcedure = $this->find(Procedure::class, $newCreatedProcedure->getId());
        static::assertCount(1, $newCreatedProcedure->getExportFieldsConfigurations());
        static::assertNotSame($masterExportConfig->getId(), $newCreatedProcedure->getDefaultExportFieldsConfiguration()->getId());
        static::assertSame($newCreatedProcedure->getId(), $newCreatedProcedure->getDefaultExportFieldsConfiguration()->getProcedure()->getId());

        // reload and check master:
        $masterBlueprint = $this->getTestMasterBlueprint();
        static::assertCount(1, $masterBlueprint->getExportFieldsConfigurations());
        static::assertSame($masterExportConfig->getId(), $masterBlueprint->getDefaultExportFieldsConfiguration()->getId());
        static::assertSame($masterBlueprint->getId(), $masterBlueprint->getDefaultExportFieldsConfiguration()->getProcedure()->getId());
    }
}
