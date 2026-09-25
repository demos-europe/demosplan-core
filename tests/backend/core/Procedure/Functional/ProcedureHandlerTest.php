<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Procedure\Functional;

use Carbon\Carbon;
use DateTime;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\BoilerplateFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\StatementFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\User\Orga;
use demosplan\DemosPlanCoreBundle\Logic\ApiRequest\ResourceLinkageFactory;
use demosplan\DemosPlanCoreBundle\Logic\Map\MapService;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureHandler;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedureService;
use demosplan\DemosPlanCoreBundle\Repository\BoilerplateRepository;
use Exception;
use Tests\Base\FunctionalTestCase;

class ProcedureHandlerTest extends FunctionalTestCase
{
    /** @var ProcedureHandler */
    protected $sut;

    /** @var Procedure */
    protected $testProcedure;

    /** @var MapService */
    protected $mapService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sut = self::getContainer()->get('dplan.procedure');
        $this->mapService = self::getContainer()->get(MapService::class);
        $this->testProcedure = $this->fixtures->getReference('testProcedure');
    }

    public function testGetAllProceduresWithSoonEndingPhases(): void
    {
        $procedures = $this->sut->getAllProceduresWithSoonEndingPhases(7);
        static::assertCount(1, $procedures);
    }

    /**
     * DPLAN-18271: called on a recurring tick by
     * {@see \demosplan\DemosPlanCoreBundle\MessageHandler\PurgePendingBoilerplateDeletionsMessageHandler}.
     * Also the first real proof that DI resolves BoilerplateDeletionService into
     * ProcedureHandler's constructor — no prior test exercised that wiring.
     */
    public function testPurgePendingBoilerplateDeletionsProcessesAllFlaggedBoilerplates(): void
    {
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $boilerplateWithUsage = BoilerplateFactory::createOne(['text' => 'Inhalt A'])->_real();
        $boilerplateWithUsageId = $boilerplateWithUsage->getId();
        $boilerplateWithoutUsage = BoilerplateFactory::createOne(['text' => 'Inhalt B'])->_real();
        $boilerplateWithoutUsageId = $boilerplateWithoutUsage->getId();
        $segment = SegmentFactory::createOne([
            'procedure'                => $boilerplateWithUsage->getProcedure(),
            'parentStatementOfSegment' => StatementFactory::new(['procedure' => $boilerplateWithUsage->getProcedure()]),
        ])->_real();
        $entityManager->refresh($segment);
        $segment->setRecommendation("<dp-boilerplate boilerplate-id=\"{$boilerplateWithUsageId}\"></dp-boilerplate>");
        $entityManager->flush();

        $procedureService = self::getContainer()->get(ProcedureService::class);
        static::assertTrue($procedureService->prepareBoilerplateDeletion($boilerplateWithUsageId));
        static::assertTrue($procedureService->prepareBoilerplateDeletion($boilerplateWithoutUsageId));

        $purgedCount = $this->sut->purgePendingBoilerplateDeletions(5);

        static::assertSame(2, $purgedCount);
        static::assertSame('Inhalt A', $segment->getRecommendationEmbedded());
        $boilerplateRepository = self::getContainer()->get(BoilerplateRepository::class);
        static::assertNull($boilerplateRepository->get($boilerplateWithUsageId));
        static::assertNull($boilerplateRepository->get($boilerplateWithoutUsageId));
    }

    public function testPurgePendingBoilerplateDeletionsRespectsTheLimit(): void
    {
        BoilerplateFactory::createMany(3, ['pendingDeletion' => true]);

        $purgedCount = $this->sut->purgePendingBoilerplateDeletions(2);

        static::assertSame(2, $purgedCount);
    }

    /**
     * @throws Exception
     */
    public function testAddInvitedPublicAffairsAgentsFromResourceLinkage(): void
    {
        self::markSkippedForCIIntervention();

        /** @var Procedure $procedure */
        $procedure = $this->fixtures->getReference('testProcedure2');
        /** @var Orga $orga */
        $orga = $this->fixtures->getReference('testOrgaInvitableInstitutionOnly');
        static::assertFalse($procedure->hasOrganisation($orga->getId()));
        $resourceLinkage = (new ResourceLinkageFactory())->createFromJsonRequestString(
            sprintf(
                '{"data": [{ "type": "publicAffairsAgent", "id": "%s" }]}',
                $orga->getId()
            )
        );
        $this->sut->addInvitedPublicAffairsAgents($procedure->getId(), $resourceLinkage);
        static::assertTrue($procedure->hasOrganisation($orga->getId()));
    }

    /**
     * Checks if all relevant procedures will be found and changed.
     *
     * @throws Exception
     */
    public function testSwitchToEvaluationPhasesOnEndOfParticipationPhase(): void
    {
        $currentDate = new DateTime();
        $idsOfEndedInternalParticipation = [];
        $idsOfEndedExternalParticipation = [];

        /** @var Procedure[] $procedures */
        $procedures = $this->getEntries(Procedure::class, ['deleted' => false]);

        foreach ($procedures as $procedure) {
            if ($procedure->getMaster() || $procedure->isMasterTemplate()) {
                continue;
            }
            if ($procedure->getEndDate() < $currentDate
                && 'write' === $procedure->getPhaseObject()->getPhaseDefinition()->getPermissionSet()) {
                $idsOfEndedInternalParticipation[] = $procedure->getId();
            }
            if ($procedure->getPublicParticipationEndDate() < $currentDate
                && 'write' === $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getPermissionSet()) {
                $idsOfEndedExternalParticipation[] = $procedure->getId();
            }
        }

        static::assertNotEmpty($idsOfEndedInternalParticipation);
        static::assertNotEmpty($idsOfEndedExternalParticipation);
        // merge ids, because some of the procedure are changed public dates and internal dates
        $idsOfChangedProcedures = array_merge($idsOfEndedExternalParticipation, $idsOfEndedInternalParticipation);

        $changedProcedures = $this->sut->switchToEvaluationPhasesOnEndOfParticipationPhase();
        static::assertCount(count(array_unique($idsOfChangedProcedures)), $changedProcedures);

        $endedInternalParticipation = [];
        $endedExternalParticipation = [];

        /** @var Procedure[] $procedures */
        $procedures = $this->getEntries(Procedure::class, ['deleted' => false]);

        foreach ($procedures as $procedure) {
            if ($procedure->getMaster() || $procedure->isMasterTemplate()) {
                continue;
            }
            if ($procedure->getEndDate() < $currentDate
                && 'write' === $procedure->getPhaseObject()->getPhaseDefinition()->getPermissionSet()) {
                $endedInternalParticipation[] = $procedure;
            }
            if ($procedure->getPublicParticipationEndDate() < $currentDate
                && 'write' === $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getPermissionSet()) {
                $endedExternalParticipation[] = $procedure;
            }
        }

        static::assertEmpty($endedInternalParticipation);
        static::assertEmpty($endedExternalParticipation);
    }

    /**
     * Checks if correct data of procedures are changed.
     */
    public function testDataOnSwitchToEvaluationPhasesOnEndOfParticipationPhase(): void
    {
        /** @var Procedure[] $procedures */
        $procedures = $this->getEntries(Procedure::class, ['deleted' => false]);
        $currentDate = new DateTime();
        $datesOfEndedInternalParticipationProcedures = [];
        $datesOfEndedExternalParticipationProcedures = [];

        // setup:
        foreach ($procedures as $procedure) {
            if ($procedure->getMaster() || $procedure->isMasterTemplate()) {
                continue;
            }
            if ($procedure->getEndDate() < $currentDate
                && 'write' === $procedure->getPhaseObject()->getPhaseDefinition()->getPermissionSet()) {
                $datesOfEndedInternalParticipationProcedures[$procedure->getId()] = $procedure->getEndDate();
            }
            if ($procedure->getPublicParticipationEndDate() < $currentDate
                && 'write' === $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getPermissionSet()) {
                $datesOfEndedExternalParticipationProcedures[$procedure->getId()] = $procedure->getPublicParticipationEndDate();
            }
        }
        static::assertNotEmpty($datesOfEndedInternalParticipationProcedures);
        static::assertNotEmpty($datesOfEndedExternalParticipationProcedures);

        // execution method of interest:
        $this->sut->switchToEvaluationPhasesOnEndOfParticipationPhase();

        // actual check of result:
        foreach ($datesOfEndedInternalParticipationProcedures as $procedureId => $endDate) {
            /** @var Procedure $procedure */
            $procedure = $this->find(Procedure::class, $procedureId);
            static::assertSame('read', $procedure->getPhaseObject()->getPhaseDefinition()->getPermissionSet());
            static::assertSame('finished', $procedure->getPhaseObject()->getPhaseDefinition()->getParticipationState());
            static::assertEquals(
                Carbon::instance($procedure->getEndDate())->endOfDay(),
                Carbon::instance($endDate)->endOfDay()
            );
        }

        foreach ($datesOfEndedExternalParticipationProcedures as $procedureId => $endDate) {
            /** @var Procedure $procedure */
            $procedure = $this->find(Procedure::class, $procedureId);
            static::assertSame(
                'read',
                $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getPermissionSet()
            );
            static::assertSame(
                'finished',
                $procedure->getPublicParticipationPhaseObject()->getPhaseDefinition()->getParticipationState()
            );
            static::assertEquals(
                Carbon::instance($procedure->getPublicParticipationEndDate())->endOfDay(),
                Carbon::instance($endDate)->endOfDay()
            );
        }
    }
}
