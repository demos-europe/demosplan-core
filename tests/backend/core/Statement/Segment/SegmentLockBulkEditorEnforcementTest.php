<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace Tests\Core\Statement\Segment;

use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Procedure\ProcedureFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Statement\SegmentFactory;
use demosplan\DemosPlanCoreBundle\DataGenerator\Factory\Workflow\PlaceFactory;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Exception\SegmentLockedException;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentBulkEditorService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentLockEnforcementService;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use Tests\Base\FunctionalTestCase;

/**
 * Functional-style test for the segment-lock enforcement surface on
 * SegmentBulkEditorService. Uses Foundry fixtures for the real Segment/Place
 * objects (properly persisted, valid IDs via Doctrine's UUID generator) so
 * the DQL lock filter in {{ @see SegmentRepository::findLockedByIds }} runs
 * against the test DB and returns real results.
 *
 * Collaborators unrelated to lock enforcement are mocked with no
 * expectations; the enforcement service is mocked to report enforcement as
 * applicable; the SegmentHandler is the real one resolved from the
 * container so the layered call chain (handler → service → repository)
 * is exercised end-to-end (the feature-off / admin-bypass paths have their
 * own unit test).
 */
class SegmentLockBulkEditorEnforcementTest extends FunctionalTestCase
{
    protected ?SegmentBulkEditorService $sut = null;

    public function testFindLockedSegmentsReturnsEmptyListWhenNoSegmentLocked(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false]),
        ])->_real();

        $this->sut = $this->buildSut();

        self::assertSame(
            [],
            $this->sut->findLockedSegments([$segment->getId()], $procedure->getId()),
        );
    }

    public function testFindLockedSegmentsReturnsEmptyListForEmptyInput(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();

        $this->sut = $this->buildSut();

        self::assertSame([], $this->sut->findLockedSegments([], $procedure->getId()));
    }

    public function testFindLockedSegmentsReturnsOnlyLockedSubsetForMixedBatch(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $lockedSegment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => true]),
        ])->_real();
        $unlockedSegment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false]),
        ])->_real();

        $this->sut = $this->buildSut();

        $result = $this->sut->findLockedSegments(
            [$lockedSegment->getId(), $unlockedSegment->getId()],
            $procedure->getId(),
        );

        self::assertCount(1, $result);
        self::assertSame($lockedSegment->getId(), $result[0]->getId());
    }

    public function testFindLockedSegmentsIsScopedToGivenProcedure(): void
    {
        // Locked segment lives in procedure A; the query asks for procedure B.
        // The procedure scope must prevent A's lock state from leaking into
        // B's response.
        $procedureA = ProcedureFactory::createOne()->_real();
        $procedureB = ProcedureFactory::createOne()->_real();
        $lockedInA = SegmentFactory::createOne([
            'procedure' => $procedureA,
            'place' => PlaceFactory::createOne(['procedure' => $procedureA, 'locked' => true]),
        ])->_real();

        $this->sut = $this->buildSut();

        self::assertSame(
            [],
            $this->sut->findLockedSegments([$lockedInA->getId()], $procedureB->getId()),
        );
    }

    public function testAssertBatchEditableIsNoopForFullyUnlockedBatch(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $segment = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false]),
        ])->_real();

        $messageBag = $this->createMock(MessageBagInterface::class);
        $messageBag->expects(self::never())->method('add');

        $this->sut = $this->buildSut(messageBag: $messageBag);

        $this->sut->assertBatchEditable([$segment->getId()], $procedure->getId());

        self::addToAssertionCount(1); // documents that no exception was thrown
    }

    public function testAssertBatchEditableThrowsAndRecordsWarningWhenBatchContainsLocked(): void
    {
        $procedure = ProcedureFactory::createOne()->_real();
        $locked1 = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => true]),
        ])->_real();
        $locked2 = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => true]),
        ])->_real();
        $unlocked = SegmentFactory::createOne([
            'procedure' => $procedure,
            'place' => PlaceFactory::createOne(['procedure' => $procedure, 'locked' => false]),
        ])->_real();

        $messageBag = $this->createMock(MessageBagInterface::class);
        $messageBag->expects(self::once())
            ->method('add')
            ->with(
                'error',
                'error.segment.bulk.contains.locked',
                ['count' => 2],
            );

        $this->sut = $this->buildSut(messageBag: $messageBag);

        $this->expectException(SegmentLockedException::class);
        $this->sut->assertBatchEditable(
            [$locked1->getId(), $locked2->getId(), $unlocked->getId()],
            $procedure->getId(),
        );
    }

    /**
     * @param MessageBagInterface&\PHPUnit\Framework\MockObject\MockObject|null $messageBag
     */
    private function buildSut(
        ?MessageBagInterface $messageBag = null,
    ): SegmentBulkEditorService {
        $enforcement = $this->createMock(SegmentLockEnforcementService::class);
        $enforcement->method('isEnforcementApplicable')->willReturn(true);

        return new SegmentBulkEditorService(
            $this->createMock(UserHandler::class),
            $this->createMock(CurrentUserInterface::class),
            $this->getContainer()->get(SegmentHandler::class),
            $this->createMock(SegmentValidator::class),
            $this->createMock(TagService::class),
            $this->createMock(TagValidator::class),
            $this->createMock(CustomFieldValueCreator::class),
            $enforcement,
            $messageBag ?? $this->createMock(MessageBagInterface::class),
            $this->createMock(EntityContentChangeService::class),
        );
    }
}
