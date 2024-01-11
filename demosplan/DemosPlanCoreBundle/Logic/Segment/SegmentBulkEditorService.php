<?php


declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\EntityValidator\SegmentValidator;
use demosplan\DemosPlanCoreBundle\Exception\InvalidArgumentException;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\Segment\Handler\SegmentHandler;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;


class SegmentBulkEditorService
{
    public function __construct(protected UserHandler $userHandler, protected SegmentHandler $segmentHandler, protected SegmentValidator $segmentValidator

    ) {

    }

        public function updateSegments($segments, $addTagIds, $removeTagIds, $assignee, $workflowPlace)
        {
            foreach ($segments as $segment) {
                /* @var Segment $segment */
                $segment->addTags($addTagIds);
                $segment->removeTags($removeTagIds);
                $segment->setAssignee($assignee);
                if (null !== $workflowPlace) {
                    $segment->setPlace($workflowPlace);
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


}
