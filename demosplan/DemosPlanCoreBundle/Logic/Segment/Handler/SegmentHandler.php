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
use demosplan\DemosPlanCoreBundle\Logic\Procedure\CurrentProcedureService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Interfaces\SegmentHandlerInterface;
use demosplan\DemosPlanCoreBundle\Logic\Segment\SegmentService;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\OptimisticLockException;
use Doctrine\ORM\ORMException;
use Exception;
use Psr\Log\LoggerInterface;

class SegmentHandler implements SegmentHandlerInterface
{
    public function __construct(
        private readonly SegmentService $segmentService,
        private readonly LoggerInterface $logger,
        private readonly CurrentProcedureService $currentProcedureService,
    ) {
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
        return $this->segmentService->findByIdWithCertainty($entityId);
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
        try {
            $segment = $this->segmentService->findByIdWithCertainty($entityId);

            // Check if segment belongs to the current procedure
            $currentProcedureId = $this->currentProcedureService->getProcedureIdWithCertainty();
            if ($segment->getParentStatementOfSegment()->getProcedure()->getId() !== $currentProcedureId) {
                $this->logger->warning('Segment does not belong to current procedure', [
                    'segmentId'          => $entityId,
                    'currentProcedureId' => $currentProcedureId,
                    'segmentProcedureId' => $segment->getParentStatementOfSegment()->getProcedure()->getId(),
                ]);

                throw new EntityNotFoundException('Segment not available');
            }

            $this->deleteObject($segment);

            return true;
        } catch (EntityNotFoundException $e) {
            $this->logger->warning('Could not find segment for deletion', [
                'segmentId' => $entityId,
                'exception' => $e->getMessage(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Exception occurred while deleting segment', [
                'segmentId' => $entityId,
                'exception' => $e->getMessage(),
                'trace'     => $e->getTraceAsString(),
            ]);
        }

        return false;
    }

    public function deleteObject(Segment $segment): void
    {
        $this->segmentService->deleteSegment($segment);
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
