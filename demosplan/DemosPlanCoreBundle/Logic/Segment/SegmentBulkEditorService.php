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
use demosplan\DemosPlanCoreBundle\CustomField\CustomFieldValuesList;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Tag;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\EntityValidator\TagValidator;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
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
    ) {
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
                $segment->setPlace($workflowPlace);
            }

            if ([] !== $arr) {
                $customFieldList = $segment->getCustomFields() ?? new CustomFieldValuesList();
                $customFieldList = $this->customFieldValueCreator->updateOrAddCustomFieldValues($customFieldList, $customFields, $segment->getProcedure()->getId(), 'PROCEDURE', 'SEGMENT');
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

        $assigneeId = trim($assigneeId);

        if (!$assigneeId) {
            throw new UserNotFoundException();
        }

        $user = $this->userHandler->getSingleUser($assigneeId);

        if (!$user) {
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
