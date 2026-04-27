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
     * Return the subset of given IDs that belong to `$procedureId` AND
     * point to segments locked for the current user.
     *
     * Takes raw IDs (not loaded entities) so the caller can fail-fast on the
     * RPC input before paying the cost of {{ @see
     * SegmentBulkEditorService::getValidSegments }} entity hydration.
     * The procedure scope prevents an out-of-procedure ID from triggering
     * a misleading "locked" response that would also leak the lock state
     * of another procedure's segments.
     * Internally the lookup runs as a single JOINed DQL via
     * {{ @see SegmentRepository::findLockedByIds }}, so there is no N+1 on
     * the place association.
     *
     * @param list<string> $segmentIds
     *
     * @return list<Segment>
     */
    public function findLockedSegments(array $segmentIds, string $procedureId): array
    {
        // Short-circuit on the batch-invariant checks (feature flag +
        // administrate permission) so we don't hit the DB at all when
        // enforcement doesn't apply to this request.
        if (!$this->segmentLockEnforcementService->isEnforcementApplicable()) {
            return [];
        }

        return $this->segmentHandler->findLockedByIds($segmentIds, $procedureId);
    }

    /**
     * Pre-validate a batch of segment IDs against the workflow-place lock.
     *
     * Runs *before* {{ @see SegmentBulkEditorService::getValidSegments }}
     * loads the entities — if any ID in the batch points to a segment in
     * `$procedureId` that's locked for the current user, throws
     * {{ @see SegmentLockedException }}. The throw is caught by the RPC's
     * access-denied branch which rolls back the whole batch (no partial
     * success). The user-facing count reaches the FE via the MessageBag
     * toast channel.
     *
     * Admins (holders of feature_administrate_segment_lock) are short-
     * circuited inside the enforcement service and pass through unaffected
     * — enabling the FPA unlock flow (one segment, bulk.edit with target
     * place + assignee).
     *
     * @param list<string> $segmentIds
     *
     * @throws SegmentLockedException when the batch contains one or more
     *                                segments the current user may not write
     */
    public function assertBatchEditable(array $segmentIds, string $procedureId): void
    {
        $lockedSegments = $this->findLockedSegments($segmentIds, $procedureId);
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
