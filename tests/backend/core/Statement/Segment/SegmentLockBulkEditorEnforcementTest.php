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
 * objects (properly constructed, with valid IDs via Doctrine's UUID
 * generator) so the per-segment filter and MessageBag message shape are
 * exercised against real entities.
 *
 * Collaborators unrelated to lock enforcement are mocked with no
 * expectations; the enforcement service is mocked to report enforcement as
 * applicable, so per-segment lock state is driven entirely by the real
 * Place entities attached to each fixture (the feature-off / admin-bypass
 * paths have their own unit test).
 */
class SegmentLockBulkEditorEnforcementTest extends FunctionalTestCase
{
    protected ?SegmentBulkEditorService $sut = null;

    public function testFindLockedSegmentsReturnsEmptyListWhenNoSegmentLocked(): void
    {
        $segment = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => false]),
        ])->_real();

        $this->sut = $this->buildSut();

        self::assertSame([], $this->sut->findLockedSegments([$segment]));
    }

    public function testFindLockedSegmentsReturnsOnlyLockedSubsetForMixedBatch(): void
    {
        $lockedSegment = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => true]),
        ])->_real();
        $unlockedSegment = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => false]),
        ])->_real();

        $this->sut = $this->buildSut();

        self::assertSame(
            [$lockedSegment],
            $this->sut->findLockedSegments([$lockedSegment, $unlockedSegment]),
        );
    }

    public function testAssertBatchEditableIsNoopForFullyUnlockedBatch(): void
    {
        $segment = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => false]),
        ])->_real();

        $messageBag = $this->createMock(MessageBagInterface::class);
        $messageBag->expects(self::never())->method('add');

        $this->sut = $this->buildSut(messageBag: $messageBag);

        $this->sut->assertBatchEditable([$segment]);

        self::addToAssertionCount(1); // documents that no exception was thrown
    }

    public function testAssertBatchEditableThrowsAndRecordsWarningWhenBatchContainsLocked(): void
    {
        $locked1 = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => true]),
        ])->_real();
        $locked2 = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => true]),
        ])->_real();
        $unlocked = SegmentFactory::createOne([
            'place' => PlaceFactory::createOne(['locked' => false]),
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
        $this->sut->assertBatchEditable([$locked1, $locked2, $unlocked]);
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
            $this->createMock(SegmentHandler::class),
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
