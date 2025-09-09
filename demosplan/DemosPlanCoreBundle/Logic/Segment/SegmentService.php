<?php

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\Entities\ProcedureInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\SegmentInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\Services\SegmentServiceInterface;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityNotFoundException;
use Doctrine\ORM\ORMException;
use Doctrine\ORM\OptimisticLockException;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\Entity\EntityContentChange;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Exception\ViolationsException;
use demosplan\DemosPlanCoreBundle\Logic\CoreService;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\TransactionService;
use demosplan\DemosPlanCoreBundle\Repository\SegmentRepository;

class SegmentService extends CoreService implements SegmentServiceInterface
{
    public function __construct(
        private readonly EntityContentChangeService $entityContentChangeService,
        private readonly SegmentValidator $segmentValidator,
        private readonly SegmentRepository $segmentRepository,
        private readonly TransactionService $transactionService
    ) {
    }

    /**
     * @return array<Segment>
     */
    public function findByProcedure(ProcedureInterface $procedure): array
    {
        return $this->segmentRepository->findByProcedure($procedure);
    }

    /**
     * @return array<Segment>
     */
    public function findAll(): array
    {
        return $this->segmentRepository->findAll();
    }

    /**
     * Creates {@link EntityContentChange} entries for the given {@link Segment} entities and
     * persists them in the database. By default, both entity types will be flushed into the
     * database.
     *
     * When flushing it is expected that the {@link Segment} entities are already managed by
     * Doctrine. I.e. fetched from Doctrine or manually added to Doctrines context via persist.
     *
     * Please note that this method is used for {@link Segment} bulk edits, meaning performance
     * is of relevance.
     *
     * @param array<int, Segment> $segments
     *
     * @throws ORMException
     * @throws OptimisticLockException
     */
    public function prepareAndSaveWithContentChange(array $segments, DateTime $updateTime): void
    {
        if ([] === $segments) {
            return;
        }

        // Determining the content change happens before preparing the segment, meaning the content
        // change may contain non-obscured text. This seems risky, but I'll leave it as it was
        // before because I'm not sure if this is intentional to keep the original data in the
        // content change.
        $contentChanges = $this->createContentChanges($segments, $updateTime);

        // new entities need to be persisted
        $this->segmentRepository->persistEntities($contentChanges);

        // segments are expected to have already been persisted, no need to do so again
        $this->prepareSegmentsForDatabase($segments);

        $this->segmentRepository->flushEverything();
    }

    /**
     * @throws OptimisticLockException
     * @throws ORMException
     * @throws ViolationsException
     */
    public function addSegments(array $segments): void
    {
        if ([] === $segments) {
            return;
        }

        $this->transactionService->executeAndFlushInTransaction(
            function (EntityManager $entityManager) use ($segments): void {
                // obscure and validate
                $this->prepareSegmentsForDatabase($segments);
                // make the segments known to Doctrine
                array_map($entityManager->persist(...), $segments);
                // flush will be automatically called
            }
        );
    }

    /**
     * @param array<int, Segment> $segments
     *
     * @throws ORMException
     *
     * @see SegmentRepository::editSegmentRecommendations
     */
    public function editSegmentRecommendations(
        array $segments,
        string $procedureId,
        string $recommendationText,
        bool $attach,
        UserInterface $user,
        string $entityType,
        DateTime $updateTime
    ): void {
        // create and persist the content changes
        $contentChanges = $this->createRecommendationEditContentChangeEntries(
            $entityType,
            $segments,
            $recommendationText,
            $attach,
            $user,
            $updateTime
        );
        $this->segmentRepository->persistEntities($contentChanges);

        // do the actual change in the database
        $segmentIds = array_map(static fn(Segment $segment): string => $segment->getId(), $segments);
        $this->segmentRepository->editSegmentRecommendations($segmentIds, $procedureId, $recommendationText, $attach);
    }

    /**
     * Creates {@link EntityContentChange} entities but does not yet persist or flush them.
     *
     * @param array<int, Segment> $segments
     *
     * @return array<int, EntityContentChange>
     */
    protected function createContentChanges(array $segments, DateTime $updateTime): array
    {
        $segmentChanges = $this->getSegmentChanges($segments);

        $contentChangeLists = array_map(
            fn(Segment $segment): array => $this->entityContentChangeService->createEntityContentChangeEntries(
                $segment,
                $segmentChanges[$segment->getId()],
                false,
                $updateTime
            ),
            $segments
        );

        // return empty array if $contentChangeLists is empty
        return array_merge([], ...$contentChangeLists);
    }

    /**
     * Handles obscuring and validation before persisting and flushing the given {@link Segment} instances.
     *
     * @param array<int, Segment> $segments
     *
     * @throws ViolationsException
     */
    protected function prepareSegmentsForDatabase(array $segments): void
    {
        foreach ($segments as $segment) {
            $this->segmentRepository->obscureText($segment);
            $violations = $this->segmentValidator->validate($segment);
            if (0 !== $violations->count()) {
                throw ViolationsException::fromConstraintViolationList($violations);
            }
        }
    }

    /**
     * @param array<int, string> $ids
     *
     * @return array<int, Segment>
     */
    public function findByIds(array $ids): array
    {
        return $this->segmentRepository->findByIds($ids);
    }

    /**
     * @return array<int, Segment>
     */
    public function findByParentStatementId(string $statementId): array
    {
        return $this->segmentRepository->findBy(['parentStatementOfSegment' => $statementId]);
    }

    /**
     * Given a Procedure Id, returns the next integer to be used in the sorting field for a new
     * segment (if none so far 1, otherwise maximum existing till the moment + 1).
     */
    public function getNextSegmentOrderNumber($procedureId): int
    {
        $lastSortedNumber = $this->segmentRepository->getLastSortedSegmentNumber($procedureId);

        return $lastSortedNumber + 1;
    }

    /**
     * @throws EntityNotFoundException
     */
    public function findByIdWithCertainty(string $id): Segment
    {
        $segment = $this->segmentRepository->find($id);
        if (null === $segment) {
            throw new EntityNotFoundException("No Segment with id: '$id' was found ");
        }

        return $segment;
    }

    /**
     * @param array<int, Segment> $segments
     *
     * @return array<string, array>
     */
    private function getSegmentChanges(array $segments): array
    {
        $result = [];
        foreach ($segments as $segment) {
            $contentChangeDiffs = $this->entityContentChangeService->calculateChanges($segment, Segment::class);
            $result[$segment->getId()] = $contentChangeDiffs;
        }

        return $result;
    }

    private function createRecommendationContentChange(
        Segment $segment,
        string $recommendationText,
        bool $attach,
        string $entityType,
        UserInterface $changer,
        DateTime $creationDate
    ): EntityContentChange {
        $preUpdateValue = $segment->getRecommendation();
        $postUpdateValue = $attach
            ? $preUpdateValue.$recommendationText
            : $recommendationText;
        $contentChange = $this->entityContentChangeService->createContentChangeData(
            $preUpdateValue,
            $postUpdateValue,
            SegmentInterface::RECOMMENDATION_FIELD_NAME,
            Segment::class
        );

        $change = $this->entityContentChangeService->createEntityContentChangeEntity(
            $segment,
            SegmentInterface::RECOMMENDATION_FIELD_NAME,
            $contentChange,
            $changer,
            $entityType,
            $creationDate
        );

        $change->setPreUpdate($preUpdateValue);
        $change->setPostUpdate($postUpdateValue);

        return $change;
    }

    /**
     * Create content changes.
     *
     * @param array<int, Segment> $segments
     *
     * @return array<int, EntityContentChange>
     */
    private function createRecommendationEditContentChangeEntries(
        string $entityType,
        array $segments,
        string $recommendationText,
        bool $attach,
        UserInterface $user,
        DateTime $creationTime
    ): array {
        return array_map(
            fn(Segment $segment): EntityContentChange => $this->createRecommendationContentChange(
                $segment,
                $recommendationText,
                $attach,
                $entityType,
                $user,
                $creationTime
            ),
            $segments
        );
    }
}
