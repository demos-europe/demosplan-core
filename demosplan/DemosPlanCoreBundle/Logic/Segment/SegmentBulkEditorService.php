<?php


declare(strict_types=1);

namespace demosplan\DemosPlanCoreBundle\Logic\Segment;
use demosplan\DemosPlanCoreBundle\Entity\Statement\Segment;


class SegmentBulkEditorService
{
    public function __construct(

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


}
