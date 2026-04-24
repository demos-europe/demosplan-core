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

use DemosEurope\DemosplanAddon\Contracts\Events\BeforeResourceUpdateFlushEvent;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Workflow\Place;
use demosplan\DemosPlanCoreBundle\EventDispatcher\SegmentLockEnforcementSubscriber;
use demosplan\DemosPlanCoreBundle\Exception\SegmentLockedException;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentLockEnforcementService;
use demosplan\DemosPlanCoreBundle\ResourceTypes\PlaceResourceType;
use demosplan\DemosPlanCoreBundle\ResourceTypes\StatementSegmentResourceType;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit test for the JSON:API PATCH enforcement path on segment updates.
 *
 * Covers the filter-and-guard behaviour of SegmentLockEnforcementSubscriber:
 *   - ignore events for other ResourceTypes,
 *   - read the *original* place via the UnitOfWork change set so the check
 *     cannot be bypassed by a non-admin PATCH that also moves the segment
 *     to an unlocked place (pre-mutation state wins),
 *   - reject locked writes with a SegmentLockedException and a MessageBag
 *     warning,
 *   - emit a Versionsverlauf audit entry when the place change crosses the
 *     lock/unlock boundary AND the write is allowed.
 */
class SegmentLockEnforcementSubscriberTest extends TestCase
{
    private SegmentLockEnforcementSubscriber $sut;
    private UnitOfWork&MockObject $unitOfWork;
    private SegmentLockEnforcementService&MockObject $enforcement;
    private MessageBagInterface&MockObject $messageBag;
    private EntityContentChangeService&MockObject $contentChanges;

    protected function setUp(): void
    {
        parent::setUp();

        $this->unitOfWork = $this->createMock(UnitOfWork::class);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($this->unitOfWork);

        $this->enforcement = $this->createMock(SegmentLockEnforcementService::class);
        $this->messageBag = $this->createMock(MessageBagInterface::class);
        $this->contentChanges = $this->createMock(EntityContentChangeService::class);

        $this->sut = new SegmentLockEnforcementSubscriber(
            $this->contentChanges,
            $entityManager,
            $this->messageBag,
            $this->enforcement,
        );
    }

    public function testIgnoresEventsForOtherResourceTypes(): void
    {
        $this->enforcement->expects(self::never())->method('isPlaceLockedForCurrentUser');
        $this->messageBag->expects(self::never())->method('add');
        $this->contentChanges->expects(self::never())->method('createSegmentLockedChangeEntryOnPlaceChange');

        $event = new BeforeResourceUpdateFlushEvent(
            $this->bareInstance(PlaceResourceType::class),
            $this->segmentOnPlace(locked: true),
        );

        $this->sut->enforceLock($event);
    }

    public function testRejectsWriteWhenOriginalPlaceIsLockedForCurrentUser(): void
    {
        $segment = $this->segmentOnPlace(locked: true);

        // No place change in this PATCH — change set has no 'place' key,
        // resolver falls back to $segment->getPlace() which is the same
        // original.
        $this->unitOfWork->method('getOriginalEntityData')->willReturn([]);

        $this->enforcement
            ->method('isPlaceLockedForCurrentUser')
            ->willReturn(true);

        $this->messageBag
            ->expects(self::once())
            ->method('add')
            ->with('warning', 'warning.segment.locked.by.place');

        $this->contentChanges
            ->expects(self::never())
            ->method('createSegmentLockedChangeEntryOnPlaceChange');

        $this->expectException(SegmentLockedException::class);

        $this->sut->enforceLock(new BeforeResourceUpdateFlushEvent(
            $this->bareInstance(StatementSegmentResourceType::class),
            $segment,
        ));
    }

    public function testRejectsWriteEvenWhenPatchTriesToEscapeIntoUnlockedPlace(): void
    {
        // Scenario: non-admin PATCHes { place: unlocked } on a locked segment.
        // By the time the event fires EDT has already mutated $segment->getPlace()
        // to the unlocked target — the subscriber MUST consult the UoW change
        // set for the original place so the enforcement still triggers.
        $originalLockedPlace = $this->place(locked: true);
        $newUnlockedPlace = $this->place(locked: false);

        $segment = new Segment();
        $segment->setPlace($newUnlockedPlace); // post-mutation value

        $this->unitOfWork
            ->method('getOriginalEntityData')
            ->willReturn(['place' => $originalLockedPlace]);

        // Service was asked about the ORIGINAL place — assert that.
        $this->enforcement
            ->expects(self::once())
            ->method('isPlaceLockedForCurrentUser')
            ->with($originalLockedPlace)
            ->willReturn(true);

        $this->expectException(SegmentLockedException::class);

        $this->sut->enforceLock(new BeforeResourceUpdateFlushEvent(
            $this->bareInstance(StatementSegmentResourceType::class),
            $segment,
        ));
    }

    public function testEmitsAuditEntryAfterEnforcementPassesWhenLockStateFlipped(): void
    {
        $originalPlace = $this->place(locked: false);
        $newPlace = $this->place(locked: true);

        $segment = new Segment();
        $segment->setPlace($newPlace);

        $this->unitOfWork
            ->method('getOriginalEntityData')
            ->willReturn(['place' => $originalPlace]);

        $this->enforcement
            ->method('isPlaceLockedForCurrentUser')
            ->willReturn(false); // admin or unlocked original — passes

        $this->messageBag->expects(self::never())->method('add');

        $this->contentChanges
            ->expects(self::once())
            ->method('createSegmentLockedChangeEntryOnPlaceChange')
            ->with($segment, $originalPlace, $newPlace);

        $this->sut->enforceLock(new BeforeResourceUpdateFlushEvent(
            $this->bareInstance(StatementSegmentResourceType::class),
            $segment,
        ));
    }

    private function segmentOnPlace(bool $locked): Segment
    {
        $segment = new Segment();
        $segment->setPlace($this->place(locked: $locked));

        return $segment;
    }

    private function place(bool $locked): Place
    {
        $place = new Place(new Procedure(), 'test-place', 0);
        $place->setLocked($locked);

        return $place;
    }

    /**
     * PHPUnit can't mock final classes, but the subscriber only inspects the
     * event type via `instanceof`. An instance-without-constructor gives us
     * a valid object of the requested class for that purpose, no mocked
     * methods needed.
     *
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function bareInstance(string $class): object
    {
        return (new \ReflectionClass($class))->newInstanceWithoutConstructor();
    }
}
