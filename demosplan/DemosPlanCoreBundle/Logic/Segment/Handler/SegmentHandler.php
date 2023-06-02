<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment\Handler;

use DateTime;
use demosplan\DemosPlanCoreBundle\Entity\Procedure\Procedure;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\NotYetImplementedException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces\SegmentHandlerInterface;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentService;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;

class SegmentHandler implements SegmentHandlerInterface
{
    /**
     * @var SegmentService
     */
    private $segmentService;

    public function __construct(SegmentService $segmentService)
    {
        $this->segmentService = $segmentService;
    }

    /**
     * @param array<Segment> $segments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function updateObjects(array $segments, DateTime $updateTime): void
    {
        $this->segmentService->prepareAndSaveWithContentChange($segments, $updateTime);
    }

    public function addSegments(array $segments): void
    {
        $this->segmentService->addSegments($segments);
    }

    public function findById(string $entityId): Segment
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @return array<Segment>
     */
    public function findAll(): array
    {
        return $this->segmentService->findAll();
    }

    /**
     * @return array<Segment>
     */
    public function findByProcedure(Procedure $procedure): array
    {
        return $this->segmentService->findByProcedure($procedure);
    }

    public function delete(string $entityId): bool
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    public function deleteObject(Segment $segment): void
    {
        throw new NotYetImplementedException('Method not yet implemented.');
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, Segment>
     */
    public function findByIds(array $ids): array
    {
        return $this->segmentService->findByIds($ids);
    }

    /**
     * Given a Procedure Id, returns the next integer to be used in the sorting field for a new
     * segment (if none so far 0, otherwise maximum existing till the moment + 1).
     */
    public function getNextSegmentOrderNumber($procedureId): int
    {
        return $this->segmentService->getNextSegmentOrderNumber($procedureId);
    }

    /**
     * @param array<int, Segment> $segments
     *
     * @throws ORMException
     */
    public function editSegmentRecommendations(array $segments, string $procedureId, string $recommendationText, bool $attach, User $user, string $entityType, DateTime $updateTime): void
    {
        $this->segmentService->editSegmentRecommendations($segments, $procedureId, $recommendationText, $attach, $user, $entityType, $updateTime);
    }
}
