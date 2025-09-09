<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureBehaviorDefinitionData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureTypeData;
use demosplan\DemosPlanCoreBundle\DataFixtures\ORM\TestData\LoadProcedureUiDefinitionData;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureBehaviorDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureType;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedureUiDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFieldDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\StatementFormDefinition;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureTypeService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\ProcedureTypeResourceType;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Exception;
use Tests\Base\FunctionalTestCase;

class ProcedureTypeServiceTest extends FunctionalTestCase
{
    /**
     * @var ProcedureTypeService
     */
    protected $sut;

    /**
     * @var ProcedureType
     */
    private $testProcedureType1;

    /**
     * @var ProcedureType
     */
    protected $testProcedureType2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get(ProcedureTypeService::class);

        $this->testProcedureType1 = $this->getProcedureTypeReference('testProcedureType1');
        $this->testProcedureType2 = $this->getProcedureTypeReference(LoadProcedureTypeData::BRK);
    }

    public function testProcedureBehaviorDefinition(): void
    {
        $procedureBehaviorDefinition1 = $this->getProcedureBehaviorDefinitionReference(LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_1);
        static::assertNotEmpty($procedureBehaviorDefinition1->getProcedureType());
        static::assertIsBool($procedureBehaviorDefinition1->isAllowedToEnableMap());
        static::assertIsBool($procedureBehaviorDefinition1->hasPriorityArea());
        static::assertIsBool($procedureBehaviorDefinition1->isParticipationGuestOnly());
        static::assertNotEmpty($procedureBehaviorDefinition1->getModificationDate());
        static::assertNotEmpty($procedureBehaviorDefinition1->getCreationDate());
    }

    /**
     * Currently, only the project BRK has `participationGuestOnly` set to true. All others have it set to false.
     * If that changes, this test needs to be updated. Until then, this test ensures that, at least the test data,
     * accounts for this. Please make sure all test data ProcedureBehaviorDefinitions are listed and checked here.
     */
    public function testProcedureBehaviorDefinitionForProjects(): void
    {
        self::markSkippedForCIIntervention();

        $expectedValuesOfIsParticipationGuestOnly = [
            // true only for Project (= ProcedureType) BRK or Procedures with ProcedureType BRK
            LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_BRK       => true,

            // false for all other cases
            LoadProcedureBehaviorDefinitionData::PROCEDURE_TESTPROCEDURE => false,
            LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_BPLAN     => false,
            LoadProcedureBehaviorDefinitionData::PROCEDURETYPE_1         => false,
        ];

        foreach ($expectedValuesOfIsParticipationGuestOnly as $reference => $expectedBooleanValue) {
            $bool = $this->getProcedureBehaviorDefinitionReference($reference)->isParticipationGuestOnly();
            if ($expectedBooleanValue) {
                static::assertTrue($bool);
            } else {
                static::assertFalse($bool);
            }
        }
    }

    public function testProcedureUiDefinition(): void
    {
        $procedureUiDefinition1 = $this->getProcedureUiDefinitionReference(LoadProcedureUiDefinitionData::PROCEDURETYPE_1);
        static::assertNotEmpty($procedureUiDefinition1->getProcedureType());
        static::assertNotEmpty($procedureUiDefinition1->getMapHintDefault());
        static::assertNotEmpty($procedureUiDefinition1->getStatementFormHintPersonalData());
        static::assertNotEmpty($procedureUiDefinition1->getStatementFormHintRecheck());
        static::assertNotEmpty($procedureUiDefinition1->getStatementFormHintStatement());
        static::assertNotEmpty($procedureUiDefinition1->getModificationDate());
        static::assertNotEmpty($procedureUiDefinition1->getCreationDate());
    }

    public function testStatementFieldDefinition(): void
    {
        $testStatementFormDefinition1 = $this->getStatementFormDefinitionReference('statementFormDefinition1');
        static::assertNotEmpty($testStatementFormDefinition1->getFieldDefinitions());
        static::assertNotEmpty($testStatementFormDefinition1->getProcedureType());

        $statementFieldDefinitions = $testStatementFormDefinition1->getFieldDefinitions();
        $statementFieldDefinitionNames = collect($statementFieldDefinitions)->map(
            function (StatementFieldDefinition $sfd) {
                return $sfd->getName();
            });

        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::NAME));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::MAP_AND_COUNTY_REFERENCE));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::COUNTY_REFERENCE));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::POSTAL_AND_CITY));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::GET_EVALUATION_MAIL_VIA_EMAIL));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::GET_EVALUATION_MAIL_VIA_SNAIL_MAIL_OR_EMAIL));
    }

    public function testDeleteStatementFormDefinition(): void
    {
        $testStatementFormDefinition1 = $this->getStatementFormDefinitionReference('statementFormDefinition1');

        $amountOfProcedureTypesBefore = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionsBefore = $this->countEntries(StatementFieldDefinition::class);
        $amountOfStatementFieldDefinitionsOfTestProcedureType1 =
            $this->testProcedureType1->getStatementFormDefinition()->getFieldDefinitions()->count();

        $this->sut->deleteStatementFormDefinition($testStatementFormDefinition1);

        $amountOfProcedureTypesAfter = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);

        static::assertEquals($amountOfStatementFormDefinitionBefore - 1, $amountOfStatementFormDefinitionAfter);
        static::assertEquals($amountOfProcedureTypesBefore, $amountOfProcedureTypesAfter);
        static::assertEquals($amountOfProcedureBehaviorDefinitionBefore, $amountOfProcedureBehaviorDefinitionAfter);
        static::assertEquals($amountOfProcedureUiDefinitionBefore, $amountOfProcedureUiDefinitionAfter);
        static::assertEquals(
            $amountOfStatementFieldDefinitionsBefore - $amountOfStatementFieldDefinitionsOfTestProcedureType1,
            $amountOfStatementFieldDefinitionAfter
        );
    }

    public function testDeleteProcedureBehaviorDefinition(): void
    {
        $testProcedureBehaviorDefinition1 = $this->getProcedureBehaviorDefinitionReference('procedureBehaviorDefinition1');

        $amountOfProcedureTypesBefore = $this->countEntries(ProcedureType::class);
        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfProcedureBehaviorDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);

        $this->sut->deleteProcedureBehaviorDefinition($testProcedureBehaviorDefinition1);

        $amountOfProcedureTypesAfter = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);

        static::assertEquals(
            $amountOfProcedureBehaviorDefinitionBefore - 1,
            $amountOfProcedureBehaviorDefinitionAfter
        );
        static::assertEquals($amountOfProcedureTypesBefore, $amountOfProcedureTypesAfter);
        static::assertEquals($amountOfProcedureUiDefinitionBefore, $amountOfProcedureUiDefinitionAfter);
        static::assertEquals($amountOfStatementFormDefinitionBefore, $amountOfStatementFormDefinitionAfter);
    }

    public function testDeleteProcedureUiDefinition(): void
    {
        $testProcedureUiDefinition1 = $this->getProcedureUiDefinitionReference('procedureUiDefinition1');

        $amountOfProcedureTypesBefore = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);

        $this->sut->deleteProcedureUiDefinition($testProcedureUiDefinition1);

        $amountOfProcedureTypesAfter = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);

        static::assertEquals($amountOfProcedureUiDefinitionBefore - 1, $amountOfProcedureUiDefinitionAfter);
        static::assertEquals($amountOfProcedureTypesBefore, $amountOfProcedureTypesAfter);
        static::assertEquals($amountOfStatementFormDefinitionBefore, $amountOfStatementFormDefinitionAfter);
        static::assertEquals($amountOfProcedureBehaviorDefinitionBefore, $amountOfProcedureBehaviorDefinitionAfter);
    }

    public function testDeleteProcedureType(): void
    {
        self::markSkippedForCIIntervention();

        $testProcedureType1 = $this->getProcedureTypeReference('testProcedureType1');
        $amountOfProcedureTypesBefore = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionBefore = $this->countEntries(StatementFieldDefinition::class);
        $amountOfStatementFieldDefinitionsOfTestProcedureType1 =
            $testProcedureType1->getStatementFormDefinition()->getFieldDefinitions()->count();

        $this->sut->deleteProcedureType($testProcedureType1);

        $amountOfProcedureTypesAfter = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);

        static::assertEquals($amountOfProcedureTypesBefore - 1, $amountOfProcedureTypesAfter);
        static::assertEquals($amountOfProcedureBehaviorDefinitionBefore - 1, $amountOfProcedureBehaviorDefinitionAfter);
        static::assertEquals($amountOfProcedureUiDefinitionBefore - 1, $amountOfProcedureUiDefinitionAfter);
        static::assertEquals($amountOfStatementFormDefinitionBefore - 1, $amountOfStatementFormDefinitionAfter);
        static::assertEquals(
            $amountOfStatementFieldDefinitionBefore - $amountOfStatementFieldDefinitionsOfTestProcedureType1,
            $amountOfStatementFieldDefinitionAfter
        );
    }

    public function testProcedureType(): void
    {
        $testProcedureType1 = $this->getProcedureTypeReference('testProcedureType1');

        static::assertEquals('Wind', $testProcedureType1->getName());
        static::assertEquals('Wind Verfahrensbeschreibung', $testProcedureType1->getDescription());
        static::assertNotEmpty($testProcedureType1->getStatementFormDefinition()->getFieldDefinitions());
    }

    public function testCreateProcedureType(): void
    {
        $testStatementFormDefinition1 = $this->getStatementFormDefinitionReference('statementFormDefinition1');
        $amountOfProcedureTypesBefore = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionBefore = $this->countEntries(StatementFieldDefinition::class);
        $amountOfStatementFieldDefinitionsPerForm = $testStatementFormDefinition1->getFieldDefinitions()->count();

        $this->sut->createProcedureType(
            'Landesplanung',
            'Beschreibung der Landesplanung',
            new StatementFormDefinition(),
            new ProcedureBehaviorDefinition(),
            new ProcedureUiDefinition(),
        );

        $amountOfProcedureTypesAfter = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);

        static::assertEquals($amountOfProcedureTypesBefore + 1, $amountOfProcedureTypesAfter);
        static::assertEquals($amountOfProcedureBehaviorDefinitionBefore + 1, $amountOfProcedureBehaviorDefinitionAfter);
        static::assertEquals($amountOfProcedureUiDefinitionBefore + 1, $amountOfProcedureUiDefinitionAfter);
        static::assertEquals($amountOfStatementFormDefinitionBefore + 1, $amountOfStatementFormDefinitionAfter);
        static::assertEquals(
            $amountOfStatementFieldDefinitionBefore + $amountOfStatementFieldDefinitionsPerForm,
            $amountOfStatementFieldDefinitionAfter
        );

        // StatementFormDefinition as well as ProcedureUiDefinition and ProcedureBehaviorDefinition
        // should be unable to set directly to the Procedure, because of special copy logic and uniqueness
    }

    public function testGetProcedureTypes(): void
    {
        self::markSkippedForCIIntervention();

        /** @var ProcedureTypeResourceType $procedureTypeResourceType */
        $procedureTypeResourceType = self::getContainer()->get(ProcedureTypeResourceType::class);
        $procedureTypes = $procedureTypeResourceType->getEntities([], []);
        static::assertCount($this->countEntries(ProcedureType::class), $procedureTypes);
    }

    public function testGetProcedureType(): void
    {
        $testProcedureType1 = $this->testProcedureType1;
        $procedureType = $this->sut->getProcedureType($testProcedureType1->getId());

        static::assertEquals($testProcedureType1->getName(), $procedureType->getName());
        static::assertEquals($testProcedureType1->getDescription(), $procedureType->getDescription());
        static::assertEquals($testProcedureType1->getStatementFormDefinition(), $procedureType->getStatementFormDefinition());
        static::assertEquals($testProcedureType1->getProcedureBehaviorDefinition(), $procedureType->getProcedureBehaviorDefinition());
        static::assertEquals($testProcedureType1->getProcedureUiDefinition(), $procedureType->getProcedureUiDefinition());
        static::assertEquals($testProcedureType1->getId(), $procedureType->getId());
    }

    public function testStatementFormDefinition(): void
    {
        $statementFormDefinition = $this->getStatementFormDefinitionReference('statementFormDefinition1');
        $referenceStatementFieldDefinitions = collect($statementFormDefinition->getFieldDefinitions())->sort()->toArray();
        $statementFieldDefinitions = $this->testProcedureType1->getStatementFormDefinition()->getFieldDefinitions();

        $statementFieldDefinitions = collect($statementFieldDefinitions)->sort()->toArray();
        $count = count($statementFieldDefinitions);

        // compare fieldDefinitions by comparing ids sequentially
        for ($i = 0; $i < $count; ++$i) {
            static::assertEquals(
                $referenceStatementFieldDefinitions[$i]->getId(),
                $statementFieldDefinitions[$i]->getId()
            );

            static::assertEquals(
                $referenceStatementFieldDefinitions[$i]->getName(),
                $statementFieldDefinitions[$i]->getName()
            );
        }

        $statementFieldDefinitionNames = collect($statementFieldDefinitions)->map(function (StatementFieldDefinition $sfd) {
            return $sfd->getName();
        })->sort();

        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::NAME));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::MAP_AND_COUNTY_REFERENCE));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::COUNTY_REFERENCE));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::POSTAL_AND_CITY));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::GET_EVALUATION_MAIL_VIA_EMAIL));
        static::assertTrue($statementFieldDefinitionNames->contains(StatementFormDefinition::GET_EVALUATION_MAIL_VIA_SNAIL_MAIL_OR_EMAIL));
    }

    /**
     * @throws Exception
     */
    public function testCopyProcedureBehaviorDefinitionOnCopyProcedureType(): void
    {
        $testProcedureType1 = $this->testProcedureType1;
        $testProcedure2 = $this->getProcedureReference('testProcedure2');

        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);

        $testProcedure2 = $this->sut->copyProcedureTypeContent($testProcedureType1, $testProcedure2);
        $testProcedure2 = $this->sut->updateProcedure($testProcedure2);

        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);

        static::assertEquals($amountOfProcedureUiDefinitionBefore + 1, $amountOfProcedureUiDefinitionAfter);
        static::assertNotEquals(
            $testProcedureType1->getProcedureBehaviorDefinition()->getId(),
            $testProcedure2->getProcedureBehaviorDefinition()->getId(),
        );
        static::assertEquals(
            $testProcedureType1->getProcedureBehaviorDefinition()->hasPriorityArea(),
            $testProcedure2->getProcedureBehaviorDefinition()->hasPriorityArea(),
        );
        static::assertEquals(
            $testProcedureType1->getProcedureBehaviorDefinition()->isAllowedToEnableMap(),
            $testProcedure2->getProcedureBehaviorDefinition()->isAllowedToEnableMap(),
        );
    }

    public function testCopyProcedureUiDefinitionOnCopyProcedureType(): void
    {
        $testProcedureType1 = $this->testProcedureType1;
        $testProcedure2 = $this->getProcedureReference('testProcedure2');

        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);

        $testProcedure2 = $this->sut->copyProcedureTypeContent($testProcedureType1, $testProcedure2);
        $testProcedure2 = $this->sut->updateProcedure($testProcedure2);

        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);

        static::assertEquals($amountOfProcedureUiDefinitionBefore + 1, $amountOfProcedureUiDefinitionAfter);
        static::assertNotEquals(
            $testProcedureType1->getProcedureUiDefinition()->getId(),
            $testProcedure2->getProcedureUiDefinition()->getId(),
        );
        static::assertEquals(
            $testProcedureType1->getProcedureUiDefinition()->getStatementFormHintPersonalData(),
            $testProcedure2->getProcedureUiDefinition()->getStatementFormHintPersonalData(),
        );
        static::assertEquals(
            $testProcedureType1->getProcedureUiDefinition()->getMapHintDefault(),
            $testProcedure2->getProcedureUiDefinition()->getMapHintDefault(),
        );
        static::assertEquals(
            $testProcedureType1->getProcedureUiDefinition()->getStatementFormHintRecheck(),
            $testProcedure2->getProcedureUiDefinition()->getStatementFormHintRecheck(),
        );
        static::assertEquals(
            $testProcedureType1->getProcedureUiDefinition()->getStatementFormHintStatement(),
            $testProcedure2->getProcedureUiDefinition()->getStatementFormHintStatement(),
        );
    }

    /**
     * @throws Exception
     */
    public function testCopyStatementFormDefinitionOnCopyProcedureType(): void
    {
        $testProcedureType1 = $this->testProcedureType1;
        $testProcedure2 = $this->getProcedureReference('testProcedure2');

        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);
        $amountOfFieldsPerForm = $testProcedureType1->getStatementFormDefinition()->getFieldDefinitions()->count();

        $testProcedure2 = $this->sut->copyProcedureTypeContent($testProcedureType1, $testProcedure2);
        $testProcedure2 = $this->sut->updateProcedure($testProcedure2);

        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);

        static::assertEquals($amountOfStatementFormDefinitionBefore + 1, $amountOfStatementFormDefinitionAfter);
        static::assertNotEquals(
            $testProcedureType1->getStatementFormDefinition()->getId(),
            $testProcedure2->getStatementFormDefinition()->getId(),
        );

        static::assertCount($amountOfFieldsPerForm, $testProcedure2->getStatementFormDefinition()->getFieldDefinitions());
        static::assertCount($amountOfFieldsPerForm, $testProcedure2->getStatementFormDefinition()->getFieldDefinitions());
        static::assertNull($testProcedure2->getStatementFormDefinition()->getProcedureType());
    }

    /**
     * @throws Exception
     */
    public function testCopyStatementFieldDefinitionOnCopyProcedureType(): void
    {
        $testProcedureType1 = $this->testProcedureType1;
        $testProcedure2 = $this->getProcedureReference('testProcedure2');

        $amountOfStatementFieldDefinitionBefore = $this->countEntries(StatementFieldDefinition::class);
        $amountOfFieldsPerForm = $testProcedureType1->getStatementFormDefinition()->getFieldDefinitions()->count();

        $testProcedure2 = $this->sut->copyProcedureTypeContent($testProcedureType1, $testProcedure2);
        $testProcedure2 = $this->sut->updateProcedure($testProcedure2);

        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);

        static::assertEquals($amountOfStatementFieldDefinitionBefore + $amountOfFieldsPerForm, $amountOfStatementFieldDefinitionAfter);
        static::assertNotEquals(
            $testProcedureType1->getStatementFormDefinition()->getId(),
            $testProcedure2->getStatementFormDefinition()->getId(),
        );

        $formOfOriginProcedureType = $testProcedureType1->getStatementFormDefinition();
        foreach ($testProcedure2->getStatementFormDefinition()->getFieldDefinitions() as $field) {
            static::assertEquals(
                $testProcedure2->getStatementFormDefinition()->getId(),
                $field->getStatementFormDefinition()->getId()
            );
            static::assertNotEquals(
                $formOfOriginProcedureType->getFieldDefinitionByName($field->getName())->getId(),
                $field->getId()
            );
            static::assertEquals(
                $formOfOriginProcedureType->getFieldDefinitionByName($field->getName())->isEnabled(),
                $field->isEnabled()
            );
            static::assertEquals(
                $formOfOriginProcedureType->getFieldDefinitionByName($field->getName())->isRequired(),
                $field->isRequired()
            );
        }

        static::assertCount($amountOfFieldsPerForm, $testProcedure2->getStatementFormDefinition()->getFieldDefinitions());
        static::assertCount($amountOfFieldsPerForm, $testProcedure2->getStatementFormDefinition()->getFieldDefinitions());
        static::assertNull($testProcedure2->getStatementFormDefinition()->getProcedureType());
    }

    /**
     * @throws Exception
     */
    public function testCopyProcedureType(): void
    {
        $testProcedureType1 = $this->testProcedureType1;
        $testProcedure2 = $this->getProcedureReference('testProcedure2');

        $amountOfProcedureTypesBefore = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionBefore = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionBefore = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionBefore = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionBefore = $this->countEntries(StatementFieldDefinition::class);
        $amountOfProceduresBefore = $this->countEntries(Procedure::class);
        $amountOfFieldsPerForm = $testProcedureType1->getStatementFormDefinition()->getFieldDefinitions()->count();

        $testProcedure2 = $this->sut->copyProcedureTypeContent($testProcedureType1, $testProcedure2);
        $testProcedure2 = $this->sut->updateProcedure($testProcedure2);

        $amountOfProcedureTypesAfter = $this->countEntries(ProcedureType::class);
        $amountOfProcedureBehaviorDefinitionAfter = $this->countEntries(ProcedureBehaviorDefinition::class);
        $amountOfProcedureUiDefinitionAfter = $this->countEntries(ProcedureUiDefinition::class);
        $amountOfStatementFormDefinitionAfter = $this->countEntries(StatementFormDefinition::class);
        $amountOfStatementFieldDefinitionAfter = $this->countEntries(StatementFieldDefinition::class);
        $amountOfProceduresAfter = $this->countEntries(Procedure::class);

        static::assertEquals($amountOfProcedureTypesBefore, $amountOfProcedureTypesAfter);
        static::assertEquals($amountOfProcedureBehaviorDefinitionBefore + 1, $amountOfProcedureBehaviorDefinitionAfter);
        static::assertEquals($amountOfProcedureUiDefinitionBefore + 1, $amountOfProcedureUiDefinitionAfter);
        static::assertEquals($amountOfStatementFormDefinitionBefore + 1, $amountOfStatementFormDefinitionAfter);
        static::assertEquals($amountOfStatementFieldDefinitionBefore + $amountOfFieldsPerForm, $amountOfStatementFieldDefinitionAfter);
        static::assertEquals($amountOfProceduresBefore, $amountOfProceduresAfter);

        // test both sites of bidirectional relationship:
        static::assertEquals(
            $testProcedure2->getId(),
            $testProcedure2->getProcedureUiDefinition()->getProcedure()->getId()
        );
        static::assertEquals(
            $testProcedure2->getId(),
            $testProcedure2->getProcedureBehaviorDefinition()->getProcedure()->getId()
        );
        static::assertEquals(
            $testProcedure2->getId(),
            $testProcedure2->getStatementFormDefinition()->getProcedure()->getId()
        );
        static::assertNull($testProcedureType1->getProcedureUiDefinition()->getProcedure());
        static::assertNull($testProcedureType1->getProcedureBehaviorDefinition()->getProcedure());
        static::assertNull($testProcedureType1->getStatementFormDefinition()->getProcedure());

        static::assertNotEquals(
            $testProcedureType1->getStatementFormDefinition()->getId(),
            $testProcedure2->getStatementFormDefinition()->getId()
        );
        static::assertNotEquals(
            $testProcedureType1->getProcedureBehaviorDefinition()->getId(),
            $testProcedure2->getProcedureBehaviorDefinition()->getId()
        );
        static::assertNotEquals(
            $testProcedureType1->getProcedureUiDefinition()->getId(),
            $testProcedure2->getProcedureUiDefinition()->getId()
        );

        static::assertCount($amountOfFieldsPerForm, $testProcedure2->getStatementFormDefinition()->getFieldDefinitions());

        static::assertEquals($testProcedureType1, $testProcedure2->getProcedureType());
        static::assertCount($amountOfFieldsPerForm, $testProcedure2->getStatementFormDefinition()->getFieldDefinitions());
        static::assertNull($testProcedure2->getStatementFormDefinition()->getProcedureType());
        static::assertNull($testProcedure2->getProcedureBehaviorDefinition()->getProcedureType());
        static::assertNull($testProcedure2->getProcedureUiDefinition()->getProcedureType());
        static::assertEquals($testProcedureType1, $testProcedure2->getProcedureType());

        static::assertNotEquals(
            $testProcedureType1->getProcedureBehaviorDefinition()->getId(),
            $testProcedure2->getProcedureBehaviorDefinition()->getId()
        );
        static::assertNotEquals(
            $testProcedureType1->getProcedureUiDefinition()->getId(),
            $testProcedure2->getProcedureUiDefinition()->getId()
        );
        static::assertNotEquals(
            $testProcedureType1->getStatementFormDefinition()->getId(),
            $testProcedure2->getStatementFormDefinition()->getId()
        );
    }

    public function testNotUniqueNameException(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->sut->createProcedureType(
            $this->testProcedureType1->getName(),
            $this->testProcedureType1->getDescription(),
            new StatementFormDefinition(),
            new ProcedureBehaviorDefinition(),
            new ProcedureUiDefinition(),
        );
    }

    public function testMultipleStatementFormDefinitionReferenceException(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->sut->createProcedureType(
            'test',
            'testtest',
            $this->testProcedureType1->getStatementFormDefinition(),
            new ProcedureBehaviorDefinition(),
            new ProcedureUiDefinition()
        );
    }

    public function testMultipleProcedureBehaviorDefinitionReferenceException(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->sut->createProcedureType(
            'test',
            'testtest',
            new StatementFormDefinition(),
            $this->testProcedureType1->getProcedureBehaviorDefinition(),
            new ProcedureUiDefinition(),
        );
    }

    public function testMultipleProcedureUiDefinitionReferenceException(): void
    {
        $this->expectException(UniqueConstraintViolationException::class);
        $this->sut->createProcedureType(
            'test',
            'testtest',
            new StatementFormDefinition(),
            new ProcedureBehaviorDefinition(),
            $this->testProcedureType1->getProcedureUiDefinition()
        );
    }
}
