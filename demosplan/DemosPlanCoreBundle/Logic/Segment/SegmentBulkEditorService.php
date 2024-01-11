<?php


declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;
use demosplan\DemosPlanCoreBundle\Entity\User\User;
use demosplan\DemosPlanCoreBundle\Exception\UserNotFoundException;
use demosplan\DemosPlanCoreBundle\Logic\User\UserHandler;


class SegmentBulkEditorService
{
    public function __construct(protected UserHandler $userHandler

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


}
