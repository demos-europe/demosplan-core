<?php

declare(strict_types=1);

/**
 * This file is part of the package demosplan.
 *
 * (c) 2010-present DEMOS plan GmbH, for more information see the license file.
 *
 * All rights reserved
 */

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;

use DateTime;
use DemosEurope\DemosplanAddon\Contracts\CurrentUserInterface;
use DemosEurope\DemosplanAddon\Contracts\Entities\UserInterface;
use DemosEurope\DemosplanAddon\Contracts\MessageBagInterface;
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\SegmentLockedException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\EntityContentChangeService;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\Statement\TagService;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;
use demosplan\DemosPlanCoreBundle\Utils\CustomField\CustomFieldValueCreator;
use Doctrine\ORM\ORMException;

class SegmentBulkEditorService
{
    public function __construct(
        protected UserHandler $userHandler,
        protected CurrentUserInterface $currentUser,
        protected SegmentHandler $segmentHandler,
        protected SegmentValidator $segmentValidator,
        protected TagService $tagService,
        protected TagValidator $tagValidator,
        protected CustomFieldValueCreator $customFieldValueCreator,
        protected SegmentLockEnforcementService $segmentLockEnforcementService,
        protected MessageBagInterface $messageBag,
        protected EntityContentChangeService $entityContentChangeService,
    ) {
    }

    /**
     * Return the subset of segments that are locked for the current user.
     *
     * Exposed so the RPC caller can both validate the batch (via
     * {{ @see SegmentBulkEditorService::assertBatchEditable }}) and re-derive
     * the locked list in its catch block to build a structured error response
     * with external IDs.
     *
     * @param array<int, Segment> $segments
     *
     * @return list<Segment>
     */
    public function findLockedSegments(array $segments): array
    {
        // Short-circuit on the batch-invariant checks (feature flag +
        // administrate permission) so we don't re-ask them per segment.
        if (!$this->segmentLockEnforcementService->isEnforcementApplicable()) {
            return [];
        }

        return array_values(array_filter(
            $segments,
            static fn (Segment $segment): bool => $segment->getPlace()->isLocked(),
        ));
    }

    /**
     * Pre-validate a batch of segments against the workflow-place lock.
     *
     * Runs before any mutations inside the bulk-edit transaction — if any
     * segment in the batch is locked for the current user, throws
     * {{ @see SegmentLockedException }}. The throw is caught by the RPC's
     * access-denied branch which rolls back the whole batch (no partial
     * success) and builds a structured error response for the frontend
     * with the affected external IDs and total count.
     *
     * Admins (holders of feature_administrate_segment_lock) are short-
     * circuited inside the enforcement service and pass through unaffected
     * — enabling the FPA unlock flow (one segment, bulk.edit with target
     * place + assignee).
     *
     * @param array<int, Segment> $segments
     *
     * @throws SegmentLockedException when the batch contains one or more
     *                                segments the current user may not write
     */
    public function assertBatchEditable(array $segments): void
    {
        $lockedSegments = $this->findLockedSegments($segments);
        if ([] === $lockedSegments) {
            return;
        }

        $this->messageBag->add(
            'error',
            'error.segment.bulk.contains.locked',
            ['count' => count($lockedSegments)],
        );

        throw new SegmentLockedException('Bulk edit batch contains segments locked for the current user.');
    }

    public function updateSegments($segments, $addTagIds, $removeTagIds, $assignee, $workflowPlace, $customFields)
    {
        foreach ($segments as $segment) {
            /* @var Segment $segment */
            $segment->addTags($addTagIds);
            $segment->removeTags($removeTagIds);

            if ('UNKNOWN' !== $assignee) {
                $segment->setAssignee($assignee);
            }

            if (null !== $workflowPlace) {
                $oldPlace = $segment->getPlace();
                $segment->setPlace($workflowPlace);
                // Emit Versionsverlauf "Gesperrt" / "Entsperrt" entry when the
                // place change crosses the lock/unlock boundary. Service
                // self-gates on the feature flag and on old/new being equal.
                $this->entityContentChangeService->createSegmentLockedChangeEntryOnPlaceChange(
                    $segment,
                    $oldPlace,
                    $workflowPlace,
                );
            }

            if ([] !== $customFields) {
                $customFieldList = $segment->getCustomFields() ?? new CustomFieldValuesList();
                $customFieldList = $this->customFieldValueCreator->updateOrAddCustomFieldValues(
                    $customFieldList,
                    $customFields,
                    $segment->getProcedure()->getId(),
                    'PROCEDURE',
                    'SEGMENT'
                );
                $segment->setCustomFields($customFieldList);
            }
        }

        return $segments;
    }

    /**
     * @throws UserNotFoundException
     */
    public function detectAssignee($assigneeId): ?User
    {
        if (!$assigneeId) {
            return null;
        }

        $assigneeId = trim((string) $assigneeId);

        if ('' === $assigneeId || '0' === $assigneeId) {
            throw new UserNotFoundException();
        }

        $user = $this->userHandler->getSingleUser($assigneeId);

        if (!$user instanceof UserInterface) {
            throw new UserNotFoundException();
        }

        return $user;
    }

    /**
     * Given an array of segment ids and a procedureId returns the corresponding list of
     * segment entities, validating that every id finds a match in a Segment and that they all
     * belong to the procedure.
     *
     * @param array<int, string> $segmentIds
     * @param string             $procedureId
     *
     * @return array<int, Segment>
     *
     * @throws InvalidArgumentException
     */
    public function getValidSegments(array $segmentIds, $procedureId): array
    {
        $segments = $this->segmentHandler->findByIds($segmentIds);
        $this->segmentValidator->validateSegments($segmentIds, $segments, $procedureId);

        return $segments;
    }

    /**
     * Given an array of tag ids and a procedureId returns the corresponding list of tag
     * entities, validating that every id finds a match in a tag and that they all belong to the
     * procedure.
     *
     * @param array<int, string> $tagIds
     *
     * @return array<int, Tag>
     *
     * @throws InvalidArgumentException
     */
    public function getValidTags(array $tagIds, string $procedureId): array
    {
        $tags = $this->tagService->findByIds($tagIds);
        $this->tagValidator->validateTags($tagIds, $tags, $procedureId);

        return $tags;
    }

    /**
     * Update texts directly in database for performance reasons.
     *
     * @param array<int, Segment> $segments
     *
     * @throws ORMException
     * @throws UserNotFoundException
     */
    public function updateRecommendations(array $segments, ?object $recommendationTextEdit, string $procedureId, string $entityType, DateTime $updateTime): void
    {
        if (null === $recommendationTextEdit) {
            return;
        }

        /** @var string $recommendationText */
        $recommendationText = $recommendationTextEdit->text;
        /** @var bool $attach */
        $attach = $recommendationTextEdit->attach;

        if ($attach && '' === $recommendationText) {
            return;
        }

        $this->segmentHandler->editSegmentRecommendations(
            $segments,
            $procedureId,
            $recommendationText,
            $attach,
            $this->currentUser->getUser(),
            $entityType,
            $updateTime
        );
    }
}
