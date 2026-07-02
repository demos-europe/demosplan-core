<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Logic\Procedure;

use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedurePhaseDefinitionFactory;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\ProcedurePhaseDefinition;
use demosplan\DemosPlanCoreBundle\Entity\Report\ReportEntry;
use demosplan\DemosPlanCoreBundle\Exception\BadRequestException;
use demosplan\DemosPlanCoreBundle\Logic\Procedure\ProcedurePhaseDefinitionEditor;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionReportEntryFactory;
use demosplan\DemosPlanCoreBundle\Logic\Report\ProcedurePhaseDefinitionUpdatableField;
use demosplan\DemosPlanCoreBundle\Logic\Report\ReportService;
use demosplan\DemosPlanCoreBundle\Repository\ProcedurePhaseDefinitionRepository;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Tests\Base\UnitTestCase;

class ProcedurePhaseDefinitionEditorTest extends UnitTestCase
{
    private ?ProcedurePhaseDefinitionEditor $sut = null;
    private ?EventDispatcherInterface $eventDispatcher = null;
    private ?MessageBagInterface $messageBag = null;
    private ?ProcedurePhaseDefinitionReportEntryFactory $reportEntryFactory = null;
    private ?ProcedurePhaseDefinitionRepository $procedurePhaseDefinitionRepository = null;
    private ?ReportService $reportService = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->reportEntryFactory = $this->createMock(ProcedurePhaseDefinitionReportEntryFactory::class);
        $this->procedurePhaseDefinitionRepository = $this->createMock(ProcedurePhaseDefinitionRepository::class);
        $this->reportService = $this->createMock(ReportService::class);

        $this->sut = new ProcedurePhaseDefinitionEditor(
            $this->eventDispatcher,
            $this->messageBag,
            $this->reportEntryFactory,
            $this->procedurePhaseDefinitionRepository,
            $this->reportService,
        );
    }

    public function testGuardConfigurationPhaseNotEditableThrowsForConfigurationPhase(): void
    {
        $phaseDefinition = ProcedurePhaseDefinitionFactory::createOne(['orderInAudience' => 0]);

        $this->expectException(BadRequestException::class);

        $this->sut->guardConfigurationPhaseNotEditable($phaseDefinition);
    }

    public function testGuardConfigurationPhaseNotEditablePassesForRegularPhase(): void
    {
        $phaseDefinition = ProcedurePhaseDefinitionFactory::createOne(['orderInAudience' => 1]);

        $this->sut->guardConfigurationPhaseNotEditable($phaseDefinition);

        $this->expectNotToPerformAssertions();
    }

    public function testAddReportEntryUpdateCreatesEntryWhenValueChanged(): void
    {
        $phaseDefinition = ProcedurePhaseDefinitionFactory::createOne();
        $reportEntry = $this->createMock(ReportEntry::class);

        $this->reportEntryFactory
            ->expects(self::once())
            ->method('createProcedurePhaseDefinitionUpdateEntry')
            ->with($phaseDefinition, ProcedurePhaseDefinitionUpdatableField::NAME, 'old', 'new')
            ->willReturn($reportEntry);

        $this->reportService
            ->expects(self::once())
            ->method('persistAndFlushReportEntry')
            ->with($reportEntry);

        $this->sut->addReportEntryUpdate($phaseDefinition, ProcedurePhaseDefinitionUpdatableField::NAME, 'old', 'new');
    }

    public function testAddReportEntryUpdateSkipsEntryWhenValueUnchanged(): void
    {
        $phaseDefinition = ProcedurePhaseDefinitionFactory::createOne();

        $this->reportEntryFactory->expects(self::never())->method('createProcedurePhaseDefinitionUpdateEntry');
        $this->reportService->expects(self::never())->method('persistAndFlushReportEntry');

        $this->sut->addReportEntryUpdate($phaseDefinition, ProcedurePhaseDefinitionUpdatableField::NAME, 'same', 'same');
    }

    public function testSetDeletedMarksEntityAsDeletedWhenNotReferenced(): void
    {
        $phaseDefinition = new ProcedurePhaseDefinition();

        $this->procedurePhaseDefinitionRepository
            ->method('isReferencedByActiveProcedure')
            ->willReturn(false);

        $this->messageBag->expects(self::never())->method('add');
        $this->eventDispatcher->expects(self::once())->method('dispatch');

        $this->sut->setDeleted($phaseDefinition, true);

        self::assertTrue($phaseDefinition->isDeleted());
        self::assertNotNull($phaseDefinition->getDeletedDate());
    }

    public function testSetDeletedAddsErrorAndThrowsWhenReferenced(): void
    {
        $phaseDefinition = new ProcedurePhaseDefinition();

        $this->procedurePhaseDefinitionRepository
            ->method('isReferencedByActiveProcedure')
            ->willReturn(true);

        $this->messageBag->expects(self::once())->method('add')->with('error', self::anything());
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->expectException(BadRequestException::class);

        $this->sut->setDeleted($phaseDefinition, true);
    }

    public function testSetDeletedToFalseSkipsReferenceCheck(): void
    {
        $phaseDefinition = new ProcedurePhaseDefinition();
        $phaseDefinition->setDeleted(true);

        $this->procedurePhaseDefinitionRepository->expects(self::never())->method('isReferencedByActiveProcedure');
        $this->messageBag->expects(self::never())->method('add');
        $this->eventDispatcher->expects(self::never())->method('dispatch');

        $this->sut->setDeleted($phaseDefinition, false);

        self::assertFalse($phaseDefinition->isDeleted());
        self::assertNull($phaseDefinition->getDeletedDate());
    }

    public function testAddReportEntryUpdateCreatesEntryWhenNullChangesToValue(): void
    {
        $phaseDefinition = ProcedurePhaseDefinitionFactory::createOne();
        $reportEntry = $this->createMock(ReportEntry::class);

        $this->reportEntryFactory
            ->expects(self::once())
            ->method('createProcedurePhaseDefinitionUpdateEntry')
            ->with($phaseDefinition, ProcedurePhaseDefinitionUpdatableField::PARTICIPANT_STATE, null, 'finished')
            ->willReturn($reportEntry);

        $this->reportService->expects(self::once())->method('persistAndFlushReportEntry');

        $this->sut->addReportEntryUpdate($phaseDefinition, ProcedurePhaseDefinitionUpdatableField::PARTICIPANT_STATE, null, 'finished');
    }
}
